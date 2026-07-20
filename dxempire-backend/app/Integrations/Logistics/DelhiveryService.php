<?php

namespace App\Integrations\Logistics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelhiveryService implements LogisticsProviderInterface
{
    private string $baseUrl = 'https://track.delhivery.com/api';

    public function createShipment(array $payload): array
    {
        if (app()->environment('local', 'testing')) {
            $fakeAwb = 'DL-MOCK-' . strtoupper(substr(md5($payload['order_number']), 0, 8));
            Log::info("Delhivery createShipment mock: {$payload['order_number']} → AWB {$fakeAwb}");
            return [
                'awb'          => $fakeAwb,
                'tracking_url' => "https://www.delhivery.com/track/package/{$fakeAwb}",
                'label_url'    => null,
            ];
        }

        $token   = config('services.delhivery.token');
        $payload = $this->buildDelhiveryPayload($payload);

        $res = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post("{$this->baseUrl}/cmu/create.json", $payload);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery shipment creation failed: ' . $res->body());
        }

        $packages = $res->json('packages') ?? [];
        if (empty($packages)) {
            throw new \RuntimeException('Delhivery returned no packages: ' . $res->body());
        }

        $awb = $packages[0]['waybill'] ?? null;
        if (!$awb) {
            throw new \RuntimeException('Delhivery AWB not returned: ' . $res->body());
        }

        return [
            'awb'          => $awb,
            'tracking_url' => "https://www.delhivery.com/track/package/{$awb}",
            'label_url'    => null,
        ];
    }

    public function trackShipment(string $awb): array
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Delhivery trackShipment mock: {$awb}");
            return [
                'awb'               => $awb,
                'status'            => 'Transit',
                'estimated_delivery'=> now()->addDays(3)->toDateString(),
                'events'            => [
                    ['date' => now()->subDay()->toDateTimeString(), 'activity' => 'Package received at origin'],
                ],
                'provider' => 'delhivery',
            ];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get("{$this->baseUrl}/v1/packages/json/", ['waybill' => $awb]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery tracking failed: ' . $res->body());
        }

        $shipmentInfo = $res->json('ShipmentData.0.Shipment') ?? [];

        return [
            'awb'               => $awb,
            'status'            => $shipmentInfo['Status']['Status'] ?? 'unknown',
            'estimated_delivery'=> $shipmentInfo['PromisedDeliveryDate'] ?? null,
            'events'            => $shipmentInfo['Scans'] ?? [],
            'provider'          => 'delhivery',
        ];
    }

    public function cancelShipment(string $awb): bool
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Delhivery cancelShipment mock: {$awb}");
            return true;
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post("{$this->baseUrl}/p/edit", [
                'waybill' => $awb,
                'cancellation' => true,
            ]);

        return $res->successful();
    }

    private function buildDelhiveryPayload(array $payload): array
    {
        $addr = $payload['address'] ?? [];
        $data = [
            'shipments' => [[
                'name'       => $addr['name'] ?? '',
                'add'        => $addr['line1'] ?? '',
                'city'       => $addr['city'] ?? '',
                'state'      => $addr['state'] ?? '',
                'country'    => 'India',
                'pin'        => $addr['pincode'] ?? '',
                'phone'      => $addr['phone'] ?? '',
                'order'      => $payload['order_number'],
                'payment_mode' => 'Prepaid',
                'cod_amount' => 0,
                'total_amount' => $payload['total'] ?? 0,
                'weight'     => $payload['weight_kg'] ?? 0.5,
                'seller_name' => config('services.delhivery.seller_name', 'DXEMPIRE'),
            ]],
        ];

        return ['format' => 'json', 'data' => json_encode($data)];
    }
}
