<?php

namespace App\Integrations\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    private string $keyId;
    private string $keySecret;

    public function __construct()
    {
        $this->keyId     = config('services.razorpay.key_id', '');
        $this->keySecret = config('services.razorpay.key_secret', '');
    }

    public function createOrder(int $amountInPaise, int $orderId): ?array
    {
        if (empty($this->keyId) || app()->environment('local', 'testing')) {
            Log::info("Razorpay mock order for order_id={$orderId}, amount={$amountInPaise}");
            return [
                'id'       => 'order_mock_' . $orderId . '_' . time(),
                'amount'   => $amountInPaise,
                'currency' => 'INR',
                'status'   => 'created',
            ];
        }

        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount'          => $amountInPaise,
                'currency'        => 'INR',
                'receipt'         => 'DX-' . $orderId,
                'payment_capture' => 1,
            ]);

        if ($response->failed()) {
            Log::error('Razorpay createOrder failed: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $webhookSecret = config('services.razorpay.webhook_secret', '');

        if (empty($webhookSecret)) {
            return true; // Skip in local/dev with no secret configured
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function createRefund(string $paymentId, int $amountInPaise): ?array
    {
        if (empty($this->keyId) || app()->environment('local', 'testing')) {
            Log::info("Razorpay mock refund for payment={$paymentId}, amount={$amountInPaise}");
            return ['id' => 'refund_mock_' . time(), 'status' => 'processed'];
        }

        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                'amount' => $amountInPaise,
            ]);

        if ($response->failed()) {
            Log::error('Razorpay refund failed: ' . $response->body());
            return null;
        }

        return $response->json();
    }
}
