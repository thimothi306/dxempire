<?php

namespace App\Integrations\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InteraktService implements WhatsAppProviderInterface
{
    private string $baseUrl = 'https://api.interakt.ai/v1/public/message';

    public function send(string $phone, string $message): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Interakt WhatsApp [{$phone}]: {$message}");
            return;
        }

        $res = Http::withToken(config('services.interakt.api_key'))
            ->post($this->baseUrl, [
                'countryCode' => '+91',
                'phoneNumber' => ltrim($phone, '0+'),
                'callbackData'=> 'dxempire',
                'type'        => 'Text',
                'data'        => ['message' => $message],
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Interakt send failed: ' . $res->body());
        }
    }

    public function sendTemplate(string $phone, string $templateName, array $params = []): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Interakt template [{$phone}] [{$templateName}]: " . json_encode($params));
            return;
        }

        $res = Http::withToken(config('services.interakt.api_key'))
            ->post($this->baseUrl, [
                'countryCode' => '+91',
                'phoneNumber' => ltrim($phone, '0+'),
                'callbackData'=> 'dxempire',
                'type'        => 'Template',
                'template'    => [
                    'name'          => $templateName,
                    'languageCode'  => 'en',
                    'headerValues'  => [],
                    'bodyValues'    => array_values($params),
                ],
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Interakt template send failed: ' . $res->body());
        }
    }
}
