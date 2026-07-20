<?php

namespace App\Integrations\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppService implements WhatsAppProviderInterface
{
    private string $baseUrl;
    private string $from;

    public function __construct()
    {
        $sid         = config('services.twilio.account_sid');
        $this->baseUrl = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $this->from    = 'whatsapp:' . config('services.twilio.whatsapp_from');
    }

    public function send(string $phone, string $message): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Twilio WhatsApp [{$phone}]: {$message}");
            return;
        }

        $res = Http::withBasicAuth(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        )->asForm()->post($this->baseUrl, [
            'From' => $this->from,
            'To'   => 'whatsapp:+91' . ltrim($phone, '0+'),
            'Body' => $message,
        ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Twilio WhatsApp send failed: ' . $res->body());
        }
    }

    public function sendTemplate(string $phone, string $templateName, array $params = []): void
    {
        // Twilio uses Content Templates (Content SID approach)
        // For simplicity, render the template locally and send as free-form
        $body = $this->renderTemplate($templateName, $params);
        $this->send($phone, $body);
    }

    private function renderTemplate(string $name, array $params): string
    {
        return match ($name) {
            'order_dispatched' => "Your DXEMPIRE order {$params['order_number']} has been dispatched via {$params['provider']}. Track with AWB: {$params['awb']}.",
            'order_approved'   => "Your order {$params['order_number']} has been approved and is being prepared.",
            'otp'              => "Your DXEMPIRE OTP is {$params['otp']}. Valid for 10 minutes.",
            default            => implode(' ', $params),
        };
    }
}
