<?php

namespace App\Integrations\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/api/v2/push/send';
    private const BATCH_SIZE    = 100;

    public function send(string $expoPushToken, string $title, string $body, array $data = []): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Expo push [{$expoPushToken}]: {$title} — {$body}");
            return;
        }

        Http::withHeaders(['Content-Type' => 'application/json'])
            ->post(self::EXPO_PUSH_URL, [
                'to'    => $expoPushToken,
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'data'  => $data,
            ]);
    }

    /**
     * Send an array of pre-built Expo message objects in chunks of 100.
     * Each message must have: to, title, body, sound, data (all string values).
     */
    public function sendBatch(array $messages): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info('Expo batch push: ' . count($messages) . ' message(s)');
            return;
        }

        foreach (array_chunk($messages, self::BATCH_SIZE) as $chunk) {
            Http::withHeaders(['Content-Type' => 'application/json'])
                ->post(self::EXPO_PUSH_URL, $chunk);
        }
    }

    public function sendToMany(array $tokens, string $title, string $body, array $data = []): void
    {
        $messages = array_map(fn($token) => [
            'to'    => $token,
            'title' => $title,
            'body'  => $body,
            'sound' => 'default',
            'data'  => $data,
        ], $tokens);

        $this->sendBatch($messages);
    }
}
