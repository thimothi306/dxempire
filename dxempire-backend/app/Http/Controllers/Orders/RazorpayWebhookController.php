<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Integrations\Payment\RazorpayService;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RazorpayService $razorpay,
        private OrderService $orderService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');
        $payload   = $request->getContent();

        if (!$this->razorpay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook signature mismatch.');
            return $this->error('Invalid signature.', 401);
        }

        $event = $request->input('event');
        $data  = $request->input('payload', []);

        try {
            match ($event) {
                'payment.captured' => $this->handlePaymentCaptured($data),
                'refund.processed' => $this->handleRefundProcessed($data),
                default            => null,
            };
        } catch (\Throwable $e) {
            Log::error("Razorpay webhook [{$event}] failed: " . $e->getMessage());
            return $this->error('Webhook processing failed.', 500);
        }

        return $this->success(null, 'Webhook received.');
    }

    private function handlePaymentCaptured(array $data): void
    {
        $paymentData = $data['payment']['entity'] ?? [];
        $rpPaymentId = $paymentData['id'] ?? null;
        $rpOrderId   = $paymentData['order_id'] ?? null;

        if (!$rpPaymentId || !$rpOrderId) {
            Log::warning('payment.captured missing payment/order id.', $data);
            return;
        }

        // Idempotency: skip if already recorded
        if (Payment::where('razorpay_payment_id', $rpPaymentId)->exists()) {
            return;
        }

        $order = Order::where('order_number', 'like', '%')
            ->whereHas('payments', fn($q) => $q->where('razorpay_order_id', $rpOrderId))
            ->first();

        // Fallback: try matching via notes on the razorpay order (we store order_id in notes)
        if (!$order) {
            $orderId = $paymentData['notes']['order_id'] ?? null;
            $order   = $orderId ? Order::find($orderId) : null;
        }

        if (!$order) {
            Log::warning("payment.captured: no order found for razorpay_order_id={$rpOrderId}");
            return;
        }

        DB::beginTransaction();
        try {
            Payment::create([
                'order_id'           => $order->id,
                'razorpay_order_id'  => $rpOrderId,
                'razorpay_payment_id'=> $rpPaymentId,
                'amount'             => ($paymentData['amount'] ?? 0) / 100,
                'status'             => 'captured',
                'method'             => $paymentData['method'] ?? 'unknown',
                'paid_at'            => now(),
            ]);

            $order->update(['payment_status' => 'paid']);

            // Auto-approve if still pending
            if ($order->status === 'pending') {
                $this->orderService->approve($order->fresh());
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleRefundProcessed(array $data): void
    {
        $refundData  = $data['refund']['entity'] ?? [];
        $rpPaymentId = $refundData['payment_id'] ?? null;
        $refundId    = $refundData['id'] ?? null;

        if (!$rpPaymentId) {
            return;
        }

        $payment = Payment::where('razorpay_payment_id', $rpPaymentId)->first();

        if (!$payment) {
            Log::warning("refund.processed: no payment found for razorpay_payment_id={$rpPaymentId}");
            return;
        }

        $payment->update([
            'status'    => 'refunded',
            'refund_id' => $refundId,
        ]);

        $order = $payment->order;
        if ($order && $order->payment_status !== 'refunded') {
            $order->update(['payment_status' => 'refunded']);
        }
    }
}
