<?php

namespace App\Integrations\WhatsApp;

use App\Models\Setting;

class WhatsAppFactory
{
    public static function make(): WhatsAppProviderInterface
    {
        $provider = Setting::get('whatsapp_provider', 'interakt');

        return match ($provider) {
            'twilio' => new TwilioWhatsAppService(),
            default  => new InteraktService(),
        };
    }
}
