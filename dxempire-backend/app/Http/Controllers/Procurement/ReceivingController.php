<?php

namespace App\Http\Controllers\Procurement;

use App\Events\ProductReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ReceiveStockRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    use ApiResponse, Exportable;

    public function store(ReceiveStockRequest $request): JsonResponse
    {
        $created = [];
        $failed  = [];

        DB::beginTransaction();

        try {
            foreach ($request->items as $index => $item) {
                // IMEI uniqueness check (including soft-deleted)
                if (!empty($item['imei'])) {
                    $exists = Product::withTrashed()->where('imei', $item['imei'])->exists();
                    if ($exists) {
                        $failed[] = [
                            'index'  => $index,
                            'imei'   => $item['imei'],
                            'reason' => 'IMEI already exists in the system.',
                        ];
                        DB::rollBack();
                        return $this->error(
                            'Batch receive failed due to duplicate IMEI.',
                            422,
                            ['failed' => $failed]
                        );
                    }
                }

                $product = Product::create([
                    'imei'              => $item['imei'] ?? null,
                    'serial_number'     => $item['serial_number'] ?? null,
                    'category'          => $item['category'],
                    'brand'             => $item['brand'],
                    'model'             => $item['model'],
                    'purchase_price'    => $item['purchase_price'],
                    'status'            => 'received',
                    'supplier_id'       => $request->supplier_id,
                    'purchase_order_id' => $request->purchase_order_id,
                ]);

                $created[] = $product->id;
            }

            // Update PO received count if linked
            if ($request->purchase_order_id) {
                PurchaseOrder::where('id', $request->purchase_order_id)
                    ->increment('received_count', count($created));

                // Mark PO as received if all items arrived
                $po = PurchaseOrder::find($request->purchase_order_id);
                if ($po && $po->received_count >= $po->expected_count) {
                    $po->update(['status' => 'received', 'received_at' => now()]);
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Batch receive failed: ' . $e->getMessage(), 500);
        }

        // Fire events after transaction commits
        foreach ($created as $productId) {
            $product = Product::find($productId);
            if ($product) {
                event(new ProductReceived($product));
            }
        }

        return $this->created([
            'created_count' => count($created),
            'created_ids'   => $created,
            'failed'        => $failed,
        ], count($created) . ' item(s) received successfully.');
    }

    /**
     * Downloadable starting point for the bulk-import CSV. Columns mirror
     * exactly what the manual "Receive Items" form already sends per row —
     * quantity > 1 means "create this many units, no shared IMEI" (IMEIs
     * must be unique), same rule the manual form already enforces.
     */
    public function importTemplate()
    {
        $headers = ['category', 'brand', 'model', 'purchase_price', 'quantity', 'imei'];
        $sample = [
            ['phone', 'Samsung', 'Galaxy A14', '8000', '5', ''],
            ['laptop', 'Dell', 'Inspiron 15', '25000', '1', '123456789012345'],
        ];

        return $this->exportCsv('receiving_template.csv', $headers, $sample);
    }

    /**
     * Same template, real Excel this time -- with an actual in-cell dropdown
     * on the category column (data validation), so a typo like "Phone" or
     * "moblie" is rejected by Excel itself before the file is ever uploaded,
     * instead of silently failing at import time.
     */
    public function importTemplateExcel()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Receiving');

        $sheet->fromArray(['category', 'brand', 'model', 'purchase_price', 'quantity', 'imei'], null, 'A1');
        $sheet->fromArray(['phone', 'Samsung', 'Galaxy A14', 8000, 5, ''], null, 'A2');
        $sheet->fromArray(['laptop', 'Dell', 'Inspiron 15', 25000, 1, '123456789012345'], null, 'A3');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        for ($row = 2; $row <= 500; $row++) {
            $validation = $sheet->getCell("A{$row}")->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid category');
            $validation->setError('Pick "phone" or "laptop" from the dropdown — no other value is accepted.');
            $validation->setFormula1('"phone,laptop"');
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, 'receiving_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Bulk version of store() for a spreadsheet of 50-200+ units instead of
     * typing each row into the UI. Accepts CSV or Excel — same columns, same
     * quantity/IMEI rule either way. Deliberately does NOT roll back the
     * whole batch on one bad row (store() does, fine for a handful of
     * manually-typed rows but brutal here) -- each row succeeds or fails on
     * its own, reported back with its row number and reason.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'               => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'purchase_order_id'  => ['nullable', 'exists:purchase_orders,id'],
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $parsed = in_array($ext, ['xlsx', 'xls'], true)
            ? $this->readExcelRows($request->file('file')->getRealPath())
            : $this->readCsvRows($request->file('file')->getRealPath());

        if ($parsed === null) {
            return $this->error("Could not read the uploaded file. Make sure it's a CSV or Excel file with the template's columns.", 422);
        }

        $requiredCols = ['category', 'brand', 'model', 'purchase_price'];
        foreach ($requiredCols as $name) {
            if (!isset($parsed['header'][$name])) {
                return $this->error("Template is missing the required '{$name}' column.", 422);
            }
        }

        [$created, $failed] = $this->processReceiveRows(
            $parsed['header'],
            $parsed['rows'],
            (int) $request->supplier_id,
            $request->purchase_order_id ? (int) $request->purchase_order_id : null
        );

        if ($request->purchase_order_id && count($created)) {
            PurchaseOrder::where('id', $request->purchase_order_id)->increment('received_count', count($created));
            $po = PurchaseOrder::find($request->purchase_order_id);
            if ($po && $po->received_count >= $po->expected_count) {
                $po->update(['status' => 'received', 'received_at' => now()]);
            }
        }

        foreach ($created as $productId) {
            $product = Product::find($productId);
            if ($product) {
                event(new ProductReceived($product));
            }
        }

        return $this->created([
            'created_count' => count($created),
            'failed_count'  => count($failed),
            'failed'        => $failed,
        ], count($created) . ' item(s) imported' . (count($failed) ? ', ' . count($failed) . ' row(s) skipped — see details.' : '.'));
    }

    /** @return array{header: array<string,int>, rows: array[]}|null */
    private function readCsvRows(string $path): ?array
    {
        $handle = fopen($path, 'r');
        $headerRow = fgetcsv($handle);
        if (!$headerRow) {
            fclose($handle);
            return null;
        }
        $header = array_flip(array_map(fn($h) => strtolower(trim((string) $h)), $headerRow));

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    /** @return array{header: array<string,int>, rows: array[]}|null */
    private function readExcelRows(string $path): ?array
    {
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($data)) {
            return null;
        }

        $headerRow = array_shift($data);
        $header    = array_flip(array_map(fn($h) => strtolower(trim((string) $h)), $headerRow));

        return ['header' => $header, 'rows' => $data];
    }

    /**
     * @param array<string,int> $col column name => index, from either reader
     * @param array[] $rows
     * @return array{0: int[], 1: array} [created product IDs, failed row reports]
     */
    private function processReceiveRows(array $col, array $rows, int $supplierId, ?int $purchaseOrderId): array
    {
        $created = [];
        $failed  = [];
        $rowNum  = 1;

        foreach ($rows as $row) {
            $rowNum++;
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $category = strtolower(trim((string) ($row[$col['category']] ?? '')));
            $brand    = trim((string) ($row[$col['brand']] ?? ''));
            $model    = trim((string) ($row[$col['model']] ?? ''));
            $price    = trim((string) ($row[$col['purchase_price']] ?? ''));
            $qty      = max(1, (int) ($row[$col['quantity'] ?? -1] ?? 1));
            $imei     = trim((string) ($row[$col['imei'] ?? -1] ?? ''));

            if (!in_array($category, ['phone', 'laptop'], true) || $brand === '' || $model === '' || !is_numeric($price)) {
                $failed[] = ['row' => $rowNum, 'reason' => 'Missing or invalid category/brand/model/purchase_price'];
                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                $unitImei = ($qty === 1 && $imei !== '') ? $imei : null;

                if ($unitImei && Product::withTrashed()->where('imei', $unitImei)->exists()) {
                    $failed[] = ['row' => $rowNum, 'reason' => "IMEI {$unitImei} already exists in the system"];
                    continue;
                }

                $product = Product::create([
                    'imei'              => $unitImei,
                    'category'          => $category,
                    'brand'             => $brand,
                    'model'             => $model,
                    'purchase_price'    => $price,
                    'status'            => 'received',
                    'supplier_id'       => $supplierId,
                    'purchase_order_id' => $purchaseOrderId,
                ]);

                $created[] = $product->id;
            }
        }

        return [$created, $failed];
    }

    public function storeForPo(\Illuminate\Http\Request $request, \App\Models\PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->merge(['purchase_order_id' => $purchaseOrder->id]);
        return $this->store(app(\App\Http\Requests\Procurement\ReceiveStockRequest::class));
    }

    public function history(): JsonResponse
    {
        $products = Product::with(['supplier', 'purchaseOrder'])
            ->whereIn('status', ['received', 'qc_pending', 'in_stock', 'sold', 'returned', 'rejected'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->paginated($products);
    }
}
