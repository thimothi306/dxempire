<?php

namespace App\Integrations\Logistics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DtdcService implements LogisticsProviderInterface
{
    private string $baseUrl = 'http://www.dtdc.in/dtdc-api';

    public function createShipment(array $payload): array
    {
        if (app()->environment('local', 'testing')) {
            $fakeAwb = 'DT-MOCK-' . strtoupper(substr(md5($payload['order_number']), 0, 8));
            Log::info("DTDC createShipment mock: {$payload['order_number']} → AWB {$fakeAwb}");
            return [
                'awb'          => $fakeAwb,
                'tracking_url' => "https://www.dtdc.in/tracking/tracking_results.asp?Ttype=awbno&strCnno={$fakeAwb}",
                'label_url'    => null,
            ];
        }

        $apiKey    = config('services.dtdc.api_key');
        $customerId= config('services.dtdc.customer_id');
        $addr      = $payload['address'] ?? [];

        $res = Http::withHeaders(['x-access-token' => $apiKey])
            ->post("{$this->baseUrl}/express/consignment", [
                'customer_code'     => $customerId,
                'reference_number'  => $payload['order_number'],
                'product_type'      => 'E',
                'pickup_name'       => config('services.dtdc.pickup_name', 'DXEMPIRE'),
                'pickup_address'    => config('services.dtdc.pickup_address', ''),
                'pickup_pincode'    => config('services.dtdc.pickup_pincode', ''),
                'delivery_name'     => $addr['name'] ?? '',
                'delivery_address'  => $addr['line1'] ?? '',
                'delivery_pincode'  => $addr['pincode'] ?? '',
                'delivery_phone'    => $addr['phone'] ?? '',
                'cod_amount'        => 0,
                'declared_value'    => $payload['total'] ?? 0,
                'weight'            => $payload['weight_kg'] ?? 0.5,
                'pieces'            => count($payload['items'] ?? [1]),
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('DTDC shipment creation failed: ' . $res->body());
        }

        $awb = $res->json('consignment_number') ?? $res->json('AWBNo');

        if (!$awb) {
            throw new \RuntimeException('DTDC AWB not returned: ' . $res->body());
        }

        return [
            'awb'          => $awb,
            'tracking_url' => "https://www.dtdc.in/tracking/tracking_results.asp?Ttype=awbno&strCnno={$awb}",
            'label_url'    => null,
        ];
    }

    public function trackShipment(string $awb): array
    {
        if (app()->environment('local', 'testing')) {
            Log::info("DTDC trackShipment mock: {$awb}");
            return [
                'awb'               => $awb,
                'status'            => 'In Transit',
                'estimated_delivery'=> now()->addDays(4)->toDateString(),
                'events'            => [
                    ['date' => now()->subDay()->toDateTimeString(), 'activity' => 'Booked at origin'],
                ],
                'provider' => 'dtdc',
            ];
        }

        $apiKey = config('services.dtdc.api_key');
        $res    = Http::withHeaders(['x-access-token' => $apiKey])
            ->get("{$this->baseUrl}/tracking/{$awb}");

        if (!$res->successful()) {
            throw new \RuntimeException('DTDC tracking failed: ' . $res->body());
        }

        $data = $res->json();

        return [
            'awb'               => $awb,
            'status'            => $data['status'] ?? 'unknown',
            'estimated_delivery'=> $data['expected_delivery_date'] ?? null,
            'events'            => $data['tracking_details'] ?? [],
            'provider'          => 'dtdc',
        ];
    }

    public function cancelShipment(string $awb): bool
    {
        if (app()->environment('local', 'testing')) {
            Log::info("DTDC cancelShipment mock: {$awb}");
            return true;
        }

        $apiKey = config('services.dtdc.api_key');
        $res    = Http::withHeaders(['x-access-token' => $apiKey])
            ->delete("{$this->baseUrl}/consignment/{$awb}");

        return $res->successful();
    }
}
