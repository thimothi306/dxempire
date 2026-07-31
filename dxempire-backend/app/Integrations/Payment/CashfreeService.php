<?php

namespace App\Integrations\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashfreeService
{
    private string $appId;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->appId     = config('services.cashfree.app_id', '');
        $this->secretKey = config('services.cashfree.secret_key', '');
        $this->baseUrl   = config('services.cashfree.mode', 'sandbox') === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    /**
     * Create a Cashfree order. Returns cf_order_id + payment_session_id
     * (the session id is what the frontend's Cashfree JS SDK uses to open checkout).
     */
    public function createOrder(float $amount, string $orderNumber, array $customer): ?array
    {
        if (empty($this->appId) || app()->environment('local', 'testing')) {
            Log::info("Cashfree mock order for {$orderNumber}, amount={$amount}");
            return [
                'cf_order_id'        => 'mock_cf_' . time(),
                'order_id'           => $orderNumber,
                'payment_session_id' => 'mock_session_' . time(),
                'order_status'       => 'ACTIVE',
            ];
        }

        $response = Http::withHeaders([
            'x-client-id'     => $this->appId,
            'x-client-secret' => $this->secretKey,
            'x-api-version'   => '2023-08-01',
            'Content-Type'    => 'application/json',
        ])->post("{$this->baseUrl}/orders", [
            'order_id'       => $orderNumber,
            'order_amount'   => round($amount, 2),
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id'    => (string) ($customer['id'] ?? $orderNumber),
                'customer_name'  => $customer['name'] ?? 'Customer',
                'customer_phone' => $customer['phone'] ?? '9999999999',
                'customer_email' => $customer['email'] ?? 'noreply@dxempire.in',
            ],
            'order_meta' => [
                // Partner portal is a static single-page site (no real client-side
                // router), so a query param is safer than a path segment here.
                'return_url' => rtrim(config('services.cashfree.return_url'), '/') . '/?order={order_id}',
                'notify_url' => rtrim(config('app.url'), '/') . '/api/v1/webhooks/cashfree',
            ],
        ]);

        if ($response->failed()) {
            Log::error('Cashfree createOrder failed: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Fetch current status/payments for an order (poll fallback if webhook is delayed).
     */
    public function getOrderPayments(string $orderNumber): ?array
    {
        if (app()->environment('local', 'testing')) {
            return [];
        }

        $response = Http::withHeaders([
            'x-client-id'     => $this->appId,
            'x-client-secret' => $this->secretKey,
            'x-api-version'   => '2023-08-01',
        ])->get("{$this->baseUrl}/orders/{$orderNumber}/payments");

        if ($response->failed()) {
            Log::error('Cashfree getOrderPayments failed: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Cashfree webhook signature verification.
     * Signature = base64(HMAC-SHA256(secretKey, timestamp + rawBody))
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, string $timestamp): bool
    {
        if (empty($this->secretKey)) {
            return true; // no secret configured yet — skip in dev
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $this->secretKey, true));

        return hash_equals($expected, $signature);
    }

    public function createRefund(string $orderNumber, float $refundAmount, string $refundId, string $note = ''): ?array
    {
        if (empty($this->appId) || app()->environment('local', 'testing')) {
            Log::info("Cashfree mock refund for order={$orderNumber}, amount={$refundAmount}");
            return ['refund_id' => $refundId, 'refund_status' => 'SUCCESS'];
        }

        $response = Http::withHeaders([
            'x-client-id'     => $this->appId,
            'x-client-secret' => $this->secretKey,
            'x-api-version'   => '2023-08-01',
            'Content-Type'    => 'application/json',
        ])->post("{$this->baseUrl}/orders/{$orderNumber}/refunds", [
            'refund_amount' => round($refundAmount, 2),
            'refund_id'     => $refundId,
            'refund_note'   => $note ?: 'Order refund',
        ]);

        if ($response->failed()) {
            Log::error('Cashfree refund failed: ' . $response->body());
            return null;
        }

        return $response->json();
    }
}
