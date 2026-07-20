<?php

namespace App\Integrations\Logistics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService implements LogisticsProviderInterface
{
    private string $baseUrl = 'https://apiv2.shiprocket.in/v1/external';

    public function createShipment(array $payload): array
    {
        if (app()->environment('local', 'testing')) {
            $fakeAwb = 'SR-MOCK-' . strtoupper(substr(md5($payload['order_number']), 0, 8));
            Log::info("Shiprocket createShipment mock: {$payload['order_number']} → AWB {$fakeAwb}");
            return [
                'awb'          => $fakeAwb,
                'tracking_url' => "https://shiprocket.co/tracking/{$fakeAwb}",
                'label_url'    => null,
            ];
        }

        $token = $this->getToken();

        // Create order
        $orderRes = Http::withToken($token)
            ->post("{$this->baseUrl}/orders/create/adhoc", [
                'order_id'           => $payload['order_number'],
                'order_date'         => now()->format('Y-m-d H:i'),
                'pickup_location'    => config('services.shiprocket.pickup_location', 'Primary'),
                'billing_customer_name' => $payload['address']['name'] ?? '',
                'billing_address'    => $payload['address']['line1'] ?? '',
                'billing_city'       => $payload['address']['city'] ?? '',
                'billing_pincode'    => $payload['address']['pincode'] ?? '',
                'billing_state'      => $payload['address']['state'] ?? '',
                'billing_country'    => 'India',
                'billing_phone'      => $payload['address']['phone'] ?? '',
                'order_items'        => $payload['items'] ?? [],
                'payment_method'     => 'Prepaid',
                'sub_total'          => $payload['total'] ?? 0,
                'length'             => $payload['length_cm'] ?? 20,
                'breadth'            => $payload['breadth_cm'] ?? 15,
                'height'             => $payload['height_cm'] ?? 5,
                'weight'             => $payload['weight_kg'] ?? 0.5,
            ]);

        if (!$orderRes->successful()) {
            throw new \RuntimeException('Shiprocket order creation failed: ' . $orderRes->body());
        }

        $shipmentId = $orderRes->json('shipment_id');

        // Generate AWB
        $awbRes = Http::withToken($token)
            ->post("{$this->baseUrl}/courier/assign/awb", [
                'shipment_id' => $shipmentId,
            ]);

        $awb = $awbRes->json('response.data.awb_code');

        if (!$awb) {
            throw new \RuntimeException('Shiprocket AWB generation failed: ' . $awbRes->body());
        }

        return [
            'awb'          => $awb,
            'tracking_url' => "https://shiprocket.co/tracking/{$awb}",
            'label_url'    => $awbRes->json('response.data.label'),
        ];
    }

    public function trackShipment(string $awb): array
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Shiprocket trackShipment mock: {$awb}");
            return $this->mockTrackingResponse($awb);
        }

        $res = Http::withToken($this->getToken())
            ->get("{$this->baseUrl}/courier/track/awb/{$awb}");

        if (!$res->successful()) {
            throw new \RuntimeException('Shiprocket tracking failed: ' . $res->body());
        }

        $data = $res->json('tracking_data');

        return [
            'awb'               => $awb,
            'status'            => $data['shipment_track'][0]['current_status'] ?? 'unknown',
            'estimated_delivery'=> $data['shipment_track'][0]['edd'] ?? null,
            'events'            => $data['shipment_track_activities'] ?? [],
            'provider'          => 'shiprocket',
        ];
    }

    public function cancelShipment(string $awb): bool
    {
        if (app()->environment('local', 'testing')) {
            Log::info("Shiprocket cancelShipment mock: {$awb}");
            return true;
        }

        $res = Http::withToken($this->getToken())
            ->post("{$this->baseUrl}/orders/cancel", ['awbs' => [$awb]]);

        return $res->successful();
    }

    private function getToken(): string
    {
        return Cache::remember('shiprocket:token', 1200, function () {
            $res = Http::post("{$this->baseUrl}/auth/login", [
                'email'    => config('services.shiprocket.email'),
                'password' => config('services.shiprocket.password'),
            ]);

            if (!$res->successful()) {
                throw new \RuntimeException('Shiprocket auth failed.');
            }

            return $res->json('token');
        });
    }

    private function mockTrackingResponse(string $awb): array
    {
        return [
            'awb'               => $awb,
            'status'            => 'In Transit',
            'estimated_delivery'=> now()->addDays(2)->toDateString(),
            'events'            => [
                ['date' => now()->subDay()->toDateTimeString(), 'activity' => 'Shipment picked up'],
                ['date' => now()->toDateTimeString(), 'activity' => 'In transit to hub'],
            ],
            'provider' => 'shiprocket',
        ];
    }
}
