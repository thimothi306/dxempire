<?php

namespace App\Integrations\WhatsApp;

interface WhatsAppProviderInterface
{
    /**
     * Send a free-form text message.
     *
     * @throws \RuntimeException
     */
    public function send(string $phone, string $message): void;

    /**
     * Send a pre-approved template message.
     *
     * @param  array  $params  Template variable substitutions
     * @throws \RuntimeException
     */
    public function sendTemplate(string $phone, string $templateName, array $params = []): void;
}
