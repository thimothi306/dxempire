<?php

namespace App\Http\Controllers\Procurement;

use App\Events\ProductReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ReceiveStockRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    use ApiResponse;

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
