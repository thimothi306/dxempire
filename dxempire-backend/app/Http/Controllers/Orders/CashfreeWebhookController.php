<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Integrations\Payment\CashfreeService;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashfreeWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CashfreeService $cashfree,
        private OrderService $orderService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('x-webhook-signature', '');
        $timestamp = $request->header('x-webhook-timestamp', '');
        $payload   = $request->getContent();

        if (!$this->cashfree->verifyWebhookSignature($payload, $signature, $timestamp)) {
            Log::warning('Cashfree webhook signature mismatch.');
            return $this->error('Invalid signature.', 401);
        }

        $type = $request->input('type');
        $data = $request->input('data', []);

        try {
            match ($type) {
                'PAYMENT_SUCCESS_WEBHOOK' => $this->handlePaymentSuccess($data),
                'REFUND_STATUS_WEBHOOK'   => $this->handleRefundStatus($data),
                default                   => null,
            };
        } catch (\Throwable $e) {
            Log::error("Cashfree webhook [{$type}] failed: " . $e->getMessage());
            return $this->error('Webhook processing failed.', 500);
        }

        return $this->success(null, 'Webhook received.');
    }

    private function handlePaymentSuccess(array $data): void
    {
        $orderData   = $data['order'] ?? [];
        $paymentData = $data['payment'] ?? [];

        $orderNumber = $orderData['order_id'] ?? null;
        $cfPaymentId = $paymentData['cf_payment_id'] ?? null;

        if (!$orderNumber || !$cfPaymentId) {
            Log::warning('PAYMENT_SUCCESS_WEBHOOK missing order_id/cf_payment_id.', $data);
            return;
        }

        if (Payment::where('razorpay_payment_id', (string) $cfPaymentId)->exists()) {
            return; // already recorded
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            Log::warning("PAYMENT_SUCCESS_WEBHOOK: no order found for order_number={$orderNumber}");
            return;
        }

        DB::beginTransaction();
        try {
            Payment::create([
                'order_id'            => $order->id,
                'razorpay_order_id'   => $orderNumber,
                'razorpay_payment_id' => (string) $cfPaymentId,
                'amount'              => $paymentData['payment_amount'] ?? $order->total_amount,
                'status'              => 'captured',
                'method'              => $paymentData['payment_method'] ?? 'cashfree',
                'paid_at'             => now(),
            ]);

            $order->update(['payment_status' => 'paid']);

            if ($order->status === 'pending') {
                $this->orderService->approve($order->fresh());
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleRefundStatus(array $data): void
    {
        $refundData  = $data['refund'] ?? [];
        $orderNumber = $data['order']['order_id'] ?? null;
        $refundId    = $refundData['refund_id'] ?? null;
        $status      = $refundData['refund_status'] ?? null;

        if (!$orderNumber || $status !== 'SUCCESS') {
            return;
        }

        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            return;
        }

        $payment = Payment::where('order_id', $order->id)->latest()->first();
        if ($payment) {
            $payment->update(['status' => 'refunded', 'refund_id' => $refundId]);
        }

        if ($order->payment_status !== 'refunded') {
            $order->update(['payment_status' => 'refunded']);
        }
    }
}
