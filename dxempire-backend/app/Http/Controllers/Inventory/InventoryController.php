<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $perPage  = min((int) ($request->per_page ?? 50), 100);
        $isPartner = $request->user()?->role === 'b2b_partner';

        $products = Product::with(['bin', 'supplier'])
            ->when($isPartner, fn($q) => $q->where('status', 'in_stock'))
            ->filter($request)
            ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($perPage);

        return $this->paginated($products);
    }

    public function lookupByImei(string $imei): JsonResponse
    {
        $product = Product::with(['bin', 'supplier', 'qcRecords'])
            ->where('imei', $imei)
            ->first();

        if (!$product) {
            return $this->error("No product found with IMEI: {$imei}", 404);
        }

        return $this->success($product);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'bin',
            'supplier',
            'purchaseOrder',
            'qcRecords.engineer',
            'binMovements.fromBin',
            'binMovements.toBin',
            'binMovements.mover',
        ]);

        return $this->success($product->toArray());
    }

    public function availability(): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::remember('inventory:availability', 60, function () {
            $rows = Product::inStock()
                ->selectRaw('category, grade, count(*) as count')
                ->groupBy('category', 'grade')
                ->get();

            $result = ['phones' => [], 'laptops' => []];
            $map    = ['phone' => 'phones', 'laptop' => 'laptops'];

            foreach ($rows as $row) {
                $key = $map[$row->category] ?? null;
                if ($key && $row->grade) {
                    $result[$key][$row->grade] = $row->count;
                }
            }

            // Add totals
            foreach ($result as $cat => $grades) {
                $result[$cat]['total'] = array_sum($grades);
            }

            return $result;
        });

        return $this->success($data);
    }

    public function export(Request $request)
    {
        $filename = 'inventory_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new InventoryExport($request), $filename);
    }
}
