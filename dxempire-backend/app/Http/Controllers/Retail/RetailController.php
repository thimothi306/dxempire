<?php

namespace App\Http\Controllers\Retail;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Models\Product;
use App\Models\RetailCartItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetailController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $orderService) {}

    // ── Catalog ──────────────────────────────────────────────────────────────

    public function catalog(Request $request): JsonResponse
    {
        $products = Product::where('status', 'in_stock')
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->grade, fn($q) => $q->where('grade', $request->grade))
            ->when($request->brand, fn($q) => $q->where('brand', 'like', "%{$request->brand}%"))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('brand', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%");
            }))
            ->select(['id', 'brand', 'model', 'category', 'grade', 'retail_price', 'color', 'storage', 'ram'])
            ->orderBy('brand')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($products);
    }

    public function productDetail(Product $product): JsonResponse
    {
        if ($product->status !== 'in_stock') {
            return $this->error('Product not available.', 404);
        }

        return $this->success($product->only([
            'id', 'brand', 'model', 'category', 'grade', 'retail_price',
            'color', 'storage', 'ram', 'condition_notes',
        ]));
    }

    // ── Cart ─────────────────────────────────────────────────────────────────

    public function cartView(Request $request): JsonResponse
    {
        $items = RetailCartItem::with('product:id,brand,model,grade,category,retail_price,status')
            ->where('customer_id', $request->customer->id)
            ->get();

        // Remove stale items (product no longer in_stock)
        $stale = $items->filter(fn($i) => $i->product?->status !== 'in_stock');
        if ($stale->isNotEmpty()) {
            RetailCartItem::whereIn('id', $stale->pluck('id'))->delete();
            $items = $items->diff($stale);
        }

        $total = $items->sum(fn($i) => (float) ($i->product->retail_price ?? 0));

        return $this->success([
            'items' => $items->values(),
            'count' => $items->count(),
            'total' => round($total * 1.18, 2),
        ]);
    }

    public function cartAdd(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        $product = Product::find($request->product_id);

        if ($product->status !== 'in_stock') {
            return $this->error('Product is not available.', 422);
        }

        RetailCartItem::firstOrCreate([
            'customer_id' => $request->customer->id,
            'product_id'  => $request->product_id,
        ]);

        return $this->success(null, 'Added to cart.');
    }

    public function cartRemove(Request $request, int $productId): JsonResponse
    {
        RetailCartItem::where('customer_id', $request->customer->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->success(null, 'Removed from cart.');
    }

    public function cartClear(Request $request): JsonResponse
    {
        RetailCartItem::where('customer_id', $request->customer->id)->delete();

        return $this->success(null, 'Cart cleared.');
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    public function ordersList(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product'])
            ->where('retail_customer_id', $request->customer->id)
            ->latest()
            ->paginate(10);

        return $this->paginated($orders);
    }

    public function orderShow(Request $request, Order $order): JsonResponse
    {
        if ($order->retail_customer_id !== $request->customer->id) {
            return $this->error('Order not found.', 404);
        }

        return $this->success($order->load(['items.product', 'invoice']));
    }

    public function orderPlace(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids'     => ['required', 'array', 'min:1'],
            'product_ids.*'   => ['integer', 'exists:products,id'],
            'shipping_state'  => ['nullable', 'string', 'max:60'],
            'shipping_address'=> ['nullable', 'string'],
        ]);

        $productIds = array_unique($request->product_ids);

        DB::beginTransaction();
        try {
            $products = $this->orderService->validateAndLockStock($productIds);
            $totals   = $this->orderService->calculateRetailTotals($products);

            $customer      = $request->customer;
            $buyerState    = $request->shipping_state ?? $customer->state;

            $order = Order::create([
                'order_number'        => $this->orderService->generateOrderNumber(),
                'retail_customer_id'  => $customer->id,
                'order_channel'       => 'retail',
                'status'              => 'pending',
                'payment_status'      => 'unpaid',
                'subtotal'            => $totals['subtotal'],
                'gst_amount'          => $totals['gst_amount'],
                'total_amount'        => $totals['total'],
                'billing_state'       => $buyerState,
                'shipping_state'      => $buyerState,
                'notes'               => $request->shipping_address,
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create($item);
            }

            // Reserve products
            Product::whereIn('id', $productIds)->update(['status' => 'reserved']);

            // Clear ordered items from cart
            RetailCartItem::where('customer_id', $customer->id)
                ->whereIn('product_id', $productIds)
                ->delete();

            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->created($order->load('items'), 'Order placed successfully.');
    }
}
