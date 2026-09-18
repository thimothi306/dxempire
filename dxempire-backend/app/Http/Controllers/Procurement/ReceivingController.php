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
     * Bulk version of store() for a spreadsheet of 50-200+ units instead of
     * typing each row into the UI. Deliberately does NOT roll back the whole
     * batch on one bad row (store() does, which is fine for a handful of
     * manually-typed rows but would be brutal here) -- each row succeeds or
     * fails on its own, and every failure is reported back with its row
     * number and reason so the admin can fix just those and re-import.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'               => ['required', 'file', 'mimes:csv,txt'],
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'purchase_order_id'  => ['nullable', 'exists:purchase_orders,id'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle) ?: []);
        $col    = array_flip($header);

        $required = ['category', 'brand', 'model', 'purchase_price'];
        foreach ($required as $col_name) {
            if (!isset($col[$col_name])) {
                fclose($handle);
                return $this->error("Template is missing the required '{$col_name}' column.", 422);
            }
        }

        $created = [];
        $failed  = [];
        $rowNum  = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $category = strtolower(trim($row[$col['category']] ?? ''));
            $brand    = trim($row[$col['brand']] ?? '');
            $model    = trim($row[$col['model']] ?? '');
            $price    = trim($row[$col['purchase_price']] ?? '');
            $qty      = max(1, (int) ($row[$col['quantity'] ?? -1] ?? 1));
            $imei     = trim($row[$col['imei'] ?? -1] ?? '');

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
                    'supplier_id'       => $request->supplier_id,
                    'purchase_order_id' => $request->purchase_order_id,
                ]);

                $created[] = $product->id;
            }
        }
        fclose($handle);

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
