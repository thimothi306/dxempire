<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key'        => 'grade_price_rules',
                'value'      => json_encode(['S1' => 0.85, 'S2' => 0.75, 'S3' => 0.65, 'S4' => 0.50, 'S5' => 0.30]),
                'updated_at' => now(),
            ],
            [
                'key'        => 'low_stock_threshold',
                'value'      => json_encode(['phone' => 10, 'laptop' => 5, 'accessory' => 20]),
                'updated_at' => now(),
            ],
            [
                'key'        => 'logistics_provider',
                'value'      => 'delhivery',
                'updated_at' => now(),
            ],
            [
                'key'        => 'whatsapp_provider',
                'value'      => 'twilio',
                'updated_at' => now(),
            ],
            [
                'key'        => 'company_name',
                'value'      => 'DXEMPIRE',
                'updated_at' => now(),
            ],
            [
                'key'        => 'company_gstin',
                'value'      => '',
                'updated_at' => now(),
            ],
            [
                'key'        => 'company_address',
                'value'      => '',
                'updated_at' => now(),
            ],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->updateOrInsert(['key' => $setting['key']], $setting);
        }
    }
}
