<?php

namespace App\Http\Controllers\Orders;

use App\Events\OrderDispatched;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\DispatchOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $orderService,
        private NotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['dealer.user', 'items'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->dealer_id, fn($q) => $q->where('dealer_id', $request->dealer_id))
            ->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->latest();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $productIds = array_unique($request->product_ids);

        DB::beginTransaction();

        try {
            $products = $this->orderService->validateAndLockStock($productIds);

            $dealer = $request->dealer_id ? Dealer::lockForUpdate()->find($request->dealer_id) : null;

            $totals = $this->orderService->calculateTotals($products, $dealer);

            // Dealer credit check
            $creditUsed = 0.0;
            if ($dealer) {
                if (!$dealer->canPlaceOrder($totals['total'])) {
                    DB::rollBack();
                    return $this->error(
                        'Insufficient credit or KYC not verified. Available: ₹' . number_format($dealer->availableCredit(), 2),
                        422
                    );
                }
                $creditUsed = $totals['total'];
            }

            $billingState = $dealer?->state ?? null;

            $order = Order::create([
                'order_number'   => $this->orderService->generateOrderNumber(),
                'dealer_id'      => $dealer?->id,
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'subtotal'       => $totals['subtotal'],
                'gst_amount'     => $totals['gst_amount'],
                'total_amount'   => $totals['total'],
                'credit_used'    => $creditUsed,
                'billing_state'  => $billingState,
                'shipping_state' => $request->shipping_state ?? $billingState,
                'notes'          => $request->notes,
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create($item);
            }

            // STOCK LOCK: reserve all products so they cannot be added to another order
            Product::whereIn('id', $productIds)->update(['status' => 'reserved']);

            AuditLog::record(
                auth()->id(),
                'order.created',
                Order::class,
                $order->id,
                [],
                ['order_number' => $order->order_number, 'total' => $totals['total']]
            );

            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->created($order->load('items'), 'Order created successfully.');
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success($order->load(['dealer.user', 'items.product', 'payments', 'invoice']));
    }

    public function approve(Order $order): JsonResponse
    {
        try {
            $this->orderService->approve($order);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        AuditLog::record(auth()->id(), 'order.approved', Order::class, $order->id, ['status' => 'pending'], ['status' => 'approved']);

        return $this->success($order->fresh(['items']), 'Order approved.');
    }

    public function startPicking(Order $order): JsonResponse
    {
        if ($order->status !== 'approved') {
            return $this->error("Order must be approved before picking. Current status: {$order->status}.", 422);
        }

        $order->update(['status' => 'picking']);

        AuditLog::record(auth()->id(), 'order.picking', Order::class, $order->id, ['status' => 'approved'], ['status' => 'picking']);

        return $this->success($order->fresh(), 'Picking started.');
    }

    public function completePacking(Order $order): JsonResponse
    {
        if ($order->status !== 'picking') {
            return $this->error("Order must be in picking state. Current status: {$order->status}.", 422);
        }

        $order->update(['status' => 'packed']);

        AuditLog::record(auth()->id(), 'order.packed', Order::class, $order->id, ['status' => 'picking'], ['status' => 'packed']);

        if ($order->dealer_id) {
            $order->load('dealer.user.pushTokens');
            if ($order->dealer?->user) {
                $this->notifications->notify(
                    $order->dealer->user,
                    'order_update',
                    'Order Packed',
                    "Your order {$order->order_number} has been packed and is ready to ship.",
                    ['order_id' => (string) $order->id]
                );
            }
        }

        return $this->success($order->fresh(), 'Packing completed.');
    }

    public function createShipment(Order $order): JsonResponse
    {
        if ($order->status !== 'packed') {
            return $this->error("Order must be packed before creating a shipment. Current status: {$order->status}.", 422);
        }

        // Razorpay order creation for online payment
        $razorpayOrderId = null;
        if ($order->dealer_id) {
            try {
                $rpService = app(\App\Integrations\Payment\RazorpayService::class);
                $rpOrder = $rpService->createOrder(
                    (int) round($order->total_amount * 100),
                    'INR',
                    ['order_number' => $order->order_number, 'order_id' => $order->id]
                );
                $razorpayOrderId = $rpOrder['id'] ?? null;
            } catch (\Throwable $e) {
                // Non-fatal: log and proceed; payment can be collected later
                \Illuminate\Support\Facades\Log::warning('Razorpay order creation failed: ' . $e->getMessage());
            }
        }

        return $this->success([
            'order'             => $order,
            'razorpay_order_id' => $razorpayOrderId,
        ], 'Shipment ready. Proceed to dispatch.');
    }

    public function dispatchOrder(DispatchOrderRequest $request, Order $order): JsonResponse
    {
        if (!in_array($order->status, ['packed', 'approved'])) {
            return $this->error("Order cannot be dispatched at status: {$order->status}.", 422);
        }

        $order->update([
            'status'             => 'dispatched',
            'logistics_provider' => $request->logistics_provider,
            'awb_number'         => $request->awb_number,
            'dispatched_at'      => now(),
        ]);

        AuditLog::record(
            auth()->id(),
            'order.dispatched',
            Order::class,
            $order->id,
            [],
            ['awb' => $request->awb_number, 'provider' => $request->logistics_provider]
        );

        event(new OrderDispatched($order->fresh()));

        return $this->success($order->fresh(), 'Order dispatched.');
    }

    public function deliver(Order $order): JsonResponse
    {
        if ($order->status !== 'dispatched') {
            return $this->error("Order must be dispatched before marking delivered. Current status: {$order->status}.", 422);
        }

        $order->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        AuditLog::record(auth()->id(), 'order.delivered', Order::class, $order->id, ['status' => 'dispatched'], ['status' => 'delivered']);

        if ($order->dealer_id) {
            $order->load('dealer.user.pushTokens');
            if ($order->dealer?->user) {
                $this->notifications->notify(
                    $order->dealer->user,
                    'order_update',
                    'Order Delivered',
                    "Your order {$order->order_number} has been delivered.",
                    ['order_id' => (string) $order->id]
                );
            }
        }

        return $this->success($order->fresh(), 'Order marked as delivered.');
    }

    public function cancel(Order $order): JsonResponse
    {
        try {
            $this->orderService->cancel($order);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        AuditLog::record(auth()->id(), 'order.cancelled', Order::class, $order->id, ['status' => $order->getOriginal('status')], ['status' => 'cancelled']);

        return $this->success($order->fresh(), 'Order cancelled.');
    }

    public function processReturn(Order $order): JsonResponse
    {
        try {
            $this->orderService->processReturn($order);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        AuditLog::record(auth()->id(), 'order.returned', Order::class, $order->id, ['status' => 'delivered'], ['status' => 'returned']);

        return $this->success($order->fresh(), 'Return processed. Products re-entered for QC.');
    }

    public function downloadInvoice(Order $order): JsonResponse
    {
        $invoice = $order->invoice;

        if (!$invoice) {
            return $this->error('Invoice not yet generated for this order.', 404);
        }

        return $this->success([
            'invoice_number' => $invoice->invoice_number,
            'pdf_path'       => $invoice->pdf_path,
            'download_url'   => url("storage/{$invoice->pdf_path}"),
            'issued_at'      => $invoice->issued_at,
        ]);
    }

    public function payments(Order $order): JsonResponse
    {
        return $this->success($order->payments()->latest()->get());
    }
}
