<?php

namespace App\Integrations\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    // Official documented endpoint (docs.expo.dev) — the "--/" segment is
    // required, it is not a typo or an old/deprecated form.
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    private const BATCH_SIZE    = 100;

    public function send(string $expoPushToken, string $title, string $body, array $data = []): void
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Expo push [{$expoPushToken}]: {$title} — {$body}");
            return;
        }

        $response = Http::withHeaders($this->headers())
            ->post(self::EXPO_PUSH_URL, [
                'to'    => $expoPushToken,
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'data'  => $data,
            ]);

        $this->logTicketErrors($response, [$expoPushToken]);
    }

    /**
     * Send an array of pre-built Expo message objects in chunks of 100.
     * Each message must have: to, title, body, sound, data (all string values).
     */
    public function sendBatch(array $messages): void
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Expo batch push: ' . count($messages) . ' message(s)');
            return;
        }

        foreach (array_chunk($messages, self::BATCH_SIZE) as $chunk) {
            $response = Http::withHeaders($this->headers())
                ->post(self::EXPO_PUSH_URL, $chunk);

            $this->logTicketErrors($response, array_column($chunk, 'to'));
        }
    }

    /**
     * Expo returns HTTP 200 even when individual messages fail (e.g. a
     * stale/uninstalled token gives "DeviceNotRegistered") — the failure is
     * only visible in each response ticket, never the HTTP status, so it
     * was previously invisible unless someone went looking at Expo's own
     * dashboard. Logs it here instead.
     */
    private function logTicketErrors($response, array $tokens): void
    {
        if (!$response->successful()) {
            Log::warning('Expo push HTTP error: ' . $response->status() . ' ' . $response->body());
            return;
        }

        $tickets = $response->json('data', []);
        // A single-message send returns one ticket object, not an array of them.
        if (isset($tickets['status'])) {
            $tickets = [$tickets];
        }

        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) === 'error') {
                Log::warning('Expo push ticket error for token ' . ($tokens[$i] ?? '?') . ': '
                    . ($ticket['message'] ?? 'unknown') . ' ('
                    . ($ticket['details']['error'] ?? 'no error code') . ')');
            }
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

    /**
     * Expo works without an access token, but Expo recommends one (enhanced
     * security — prevents anyone else from pushing to your project) once
     * EXPO_ACCESS_TOKEN is set in config/services.php ('expo.access_token').
     */
    private function headers(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($token = config('services.expo.access_token')) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
