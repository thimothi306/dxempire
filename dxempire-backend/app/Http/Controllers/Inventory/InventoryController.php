<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\Setting;
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

    /**
     * Manually add a unit outside the receiving/PO flow — e.g. a
     * correction or a unit that arrived through an untracked channel.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'imei'               => ['nullable', 'string', 'max:20', 'unique:products,imei'],
            'serial_number'      => ['nullable', 'string', 'max:100'],
            'category'           => ['required', 'in:phone,laptop,accessory'],
            'brand'              => ['required', 'string', 'max:100'],
            'model'              => ['required', 'string', 'max:200'],
            'grade'              => ['nullable', 'in:S1,S2,S3,S4,S5'],
            'status'             => ['nullable', 'in:received,qc_pending,in_stock,sold,returned,rejected,refurbishment'],
            'bin_id'             => ['nullable', 'exists:bins,id'],
            'purchase_price'     => ['required', 'numeric', 'min:0'],
            'selling_price'      => ['nullable', 'numeric', 'min:0'],
            'supplier_id'        => ['required', 'exists:suppliers,id'],
        ]);

        $data['status'] = $data['status'] ?? 'received';

        $product = Product::create($data);

        return $this->created($product->load(['bin', 'supplier']), 'Product created.');
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'imei'               => ['nullable', 'string', 'max:20', 'unique:products,imei,' . $product->id],
            'serial_number'      => ['nullable', 'string', 'max:100'],
            'category'           => ['sometimes', 'in:phone,laptop,accessory'],
            'brand'              => ['sometimes', 'string', 'max:100'],
            'model'              => ['sometimes', 'string', 'max:200'],
            'grade'              => ['nullable', 'in:S1,S2,S3,S4,S5'],
            'status'             => ['sometimes', 'in:received,qc_pending,in_stock,sold,returned,rejected,refurbishment'],
            'bin_id'             => ['nullable', 'exists:bins,id'],
            'purchase_price'     => ['sometimes', 'numeric', 'min:0'],
            'selling_price'      => ['nullable', 'numeric', 'min:0'],
            'supplier_id'        => ['sometimes', 'exists:suppliers,id'],
        ]);

        $product->update($data);

        return $this->success($product->fresh()->load(['bin', 'supplier']), 'Product updated.');
    }

    /**
     * Soft delete only — Product uses SoftDeletes, so this is fully
     * reversible (row stays, just excluded from default queries). Sold
     * units are blocked here to keep order history resolvable; use
     * deactivate() instead to pull a sold/listed unit from view.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->status === 'sold') {
            return $this->error('Cannot delete a sold product — it is linked to order history. Use deactivate instead.', 422);
        }

        $product->delete();

        return $this->success(null, 'Product deleted.');
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

    /**
     * Current per-grade stock shortfalls against the admin-configured
     * threshold (Settings → low_stock_threshold, per category). Computed
     * live so the dashboard reflects right-now stock, not the last
     * 30-minute background check.
     */
    public function lowStock(): JsonResponse
    {
        $thresholds = Setting::getJson('low_stock_threshold', [
            'phone'  => 10,
            'laptop' => 5,
        ]);

        $rows = Product::inStock()
            ->selectRaw('category, grade, count(*) as count')
            ->groupBy('category', 'grade')
            ->get();

        $alerts = [];

        foreach ($rows as $row) {
            $threshold = $thresholds[$row->category] ?? null;
            if ($threshold === null || !$row->grade || $row->count >= $threshold) {
                continue;
            }

            $alerts[] = [
                'category'  => $row->category,
                'grade'     => $row->grade,
                'count'     => $row->count,
                'threshold' => $threshold,
                'severity'  => $row->count <= max(1, (int) floor($threshold / 3)) ? 'critical' : 'warning',
            ];
        }

        usort($alerts, fn($a, $b) => $a['count'] <=> $b['count']);

        return $this->success($alerts);
    }

    public function export(Request $request)
    {
        $filename = 'inventory_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new InventoryExport($request), $filename);
    }

    /**
     * Manually hide a product from the retail catalog, independent of its
     * inventory status — e.g. pull a listing without touching stock state.
     */
    public function deactivate(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return $this->success($product->fresh(), 'Product deactivated.');
    }

    public function activate(Product $product): JsonResponse
    {
        $product->update(['is_active' => true]);

        return $this->success($product->fresh(), 'Product activated.');
    }
}
