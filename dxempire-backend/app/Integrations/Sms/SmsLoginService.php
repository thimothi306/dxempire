<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * smslogin.co transactional SMS — DLT-registered templates only. The
 * message text sent must match an approved template's wording EXACTLY
 * (only the {#var#} slots differ) or the telecom operator silently blocks
 * it even if smslogin.co itself accepts the request.
 */
class SmsLoginService
{
    private const BASE_URL = 'https://smslogin.co/v3/api.php';

    /**
     * @param string $message Must match a DLT-approved template's wording exactly.
     * @param string|null $templateId The DLT Template ID for that exact wording.
     */
    public function send(string $phone, string $message, ?string $templateId): void
    {
        $mobile = $this->normalizePhone($phone);

        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("SMS [{$mobile}] (template {$templateId}): {$message}");
            return;
        }

        $params = [
            'username' => config('services.smslogin.username'),
            'apikey'   => config('services.smslogin.api_key'),
            'senderid' => config('services.smslogin.sender_id'),
            'mobile'   => $mobile,
            'message'  => $message,
        ];

        if ($templateId) {
            $params['templateid'] = $templateId;
        }

        $response = Http::get(self::BASE_URL, $params);

        if (!$response->successful()) {
            Log::warning("SMS send failed [{$mobile}]: HTTP {$response->status()} " . $response->body());
            return;
        }

        // smslogin.co returns 200 with an error string in the body on
        // rejection (e.g. bad template match, insufficient credits) rather
        // than a non-2xx status, so the body is worth logging either way.
        Log::info("SMS sent [{$mobile}]: " . $response->body());
    }

    public function sendPartnerOtp(string $phone, string $otp): void
    {
        $this->send(
            $phone,
            "Your DXEMPIRE Partner login OTP is {$otp}. Valid for 10 minutes. Do not share this code with anyone.",
            config('services.smslogin.template_partner_otp')
        );
    }

    public function sendRetailOtp(string $phone, string $otp): void
    {
        $this->send(
            $phone,
            "Your DXEMPIRE OTP is {$otp}. Valid for 10 minutes. Do not share this code with anyone.",
            config('services.smslogin.template_retail_otp')
        );
    }

    public function sendPaymentReceived(string $phone, float $amount, string $orderNumber): void
    {
        $formattedAmount = number_format($amount, 2, '.', '');

        $this->send(
            $phone,
            "Payment of Rs.{$formattedAmount} received for order {$orderNumber}. Thank you! - DXEMPIRE",
            config('services.smslogin.template_payment_received')
        );
    }

    /**
     * Our stored numbers are plain 10-digit (e.g. "9111111101"). The API
     * needs country code with no + or leading 0 (e.g. "919111111101").
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        return $digits; // already has country code, or an unexpected format we pass through as-is
    }
}
