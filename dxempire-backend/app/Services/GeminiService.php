<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Send a prompt to Gemini and return the plain-text reply, or null if
     * the API key isn't configured or the call fails — callers must treat
     * AI text as optional narration, never a source of truth.
     */
    public function generate(string $prompt): ?string
    {
        $key   = config('services.gemini.key');
        $model = config('services.gemini.model');

        if (!$key) {
            return null;
        }

        try {
            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]
            );

            if (!$response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');
        } catch (\Throwable $e) {
            Log::warning('Gemini request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Multi-turn call with optional function-calling tools. Returns the raw
     * `content` object from Gemini's response (role + parts, where a part
     * may be plain text or a functionCall the caller must execute and
     * report back) — or null on any failure. Callers own the conversation
     * loop; this method just makes one request.
     */
    public function chat(array $contents, array $tools = [], ?string $systemInstruction = null, ?string $model = null): ?array
    {
        $key   = config('services.gemini.key');
        $model = $model ?? config('services.gemini.model');

        if (!$key) {
            return null;
        }

        $body = ['contents' => $contents];
        if (!empty($tools)) {
            $body['tools'] = [['functionDeclarations' => $tools]];
        }
        if ($systemInstruction) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                $body
            );

            if (!$response->successful()) {
                Log::warning('Gemini chat API error: ' . $response->body());
                return null;
            }

            return $response->json('candidates.0.content');
        } catch (\Throwable $e) {
            Log::warning('Gemini chat request failed: ' . $e->getMessage());
            return null;
        }
    }
}
