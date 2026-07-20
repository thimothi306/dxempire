<?php

namespace App\Integrations\Logistics;

use App\Models\Setting;

class LogisticsFactory
{
    public static function make(): LogisticsProviderInterface
    {
        $provider = Setting::get('logistics_provider', 'shiprocket');

        return match ($provider) {
            'delhivery' => new DelhiveryService(),
            'dtdc'      => new DtdcService(),
            default     => new ShiprocketService(),
        };
    }
}
