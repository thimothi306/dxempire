<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Msg91Service
{
    public function sendOtp(string $phone, string $otp): void
    {
        $authKey    = config('services.msg91.auth_key');
        $templateId = config('services.msg91.otp_template_id');

        if (empty($authKey) || app()->environment('local', 'testing')) {
            Log::info("MSG91 OTP [{$phone}]: {$otp}");
            return;
        }

        Http::post('https://api.msg91.com/api/v5/otp', [
            'authkey'     => $authKey,
            'mobile'      => '91' . $phone,
            'template_id' => $templateId,
            'otp'         => $otp,
        ]);
    }
}
