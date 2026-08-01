<?php

namespace App\Integrations\Logistics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelhiveryService implements LogisticsProviderInterface
{
    private string $baseUrl = 'https://track.delhivery.com/api';

    public function createShipment(array $payload): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            $fakeAwb = 'DL-MOCK-' . strtoupper(substr(md5($payload['order_number']), 0, 8));
            Log::info("Delhivery createShipment mock: {$payload['order_number']} → AWB {$fakeAwb}");
            return [
                'awb'          => $fakeAwb,
                'tracking_url' => "https://www.delhivery.com/track/package/{$fakeAwb}",
                'label_url'    => null,
            ];
        }

        $token = config('services.delhivery.token');
        $body  = $this->buildDelhiveryPayload($payload);

        // Delhivery's create.json expects a literal form-urlencoded body
        // ("format=json&data={...}"), NOT a JSON content-type request.
        $res = Http::asForm()->withHeaders(['Authorization' => "Token {$token}"])
            ->post("{$this->baseUrl}/cmu/create.json", $body);

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

    public function checkPincodeServiceability(string $pincode): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Delhivery pincode check mock: {$pincode}");
            return ['pincode' => $pincode, 'serviceable' => true, 'cod' => true, 'prepaid' => true];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get('https://track.delhivery.com/c/api/pin-codes/json/', ['filter_codes' => $pincode]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery pincode check failed: ' . $res->body());
        }

        $codes = $res->json('delivery_codes') ?? [];
        if (empty($codes)) {
            return ['pincode' => $pincode, 'serviceable' => false, 'cod' => false, 'prepaid' => false];
        }

        $postal = $codes[0]['postal_code'] ?? [];

        return [
            'pincode'     => $pincode,
            'serviceable' => true,
            'cod'         => ($postal['cod'] ?? 'N') === 'Y',
            'prepaid'     => ($postal['pre_paid'] ?? 'N') === 'Y',
        ];
    }

    public function trackShipment(string $awb): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
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
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Delhivery cancelShipment mock: {$awb}");
            return true;
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post("{$this->baseUrl}/p/edit", [
                'waybill'      => $awb,
                'cancellation' => 'true',
            ]);

        return $res->successful();
    }

    /**
     * Edit a shipment already booked (only while Manifested/In Transit/Pending —
     * Delhivery blocks edits once Dispatched or in a terminal status).
     * $data: waybill (required) + any of name, phone, pt, add, products_desc, gm, shipment_height/width/length
     */
    public function updateShipment(array $data): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Delhivery updateShipment mock: ' . json_encode($data));
            return ['success' => true];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post("{$this->baseUrl}/p/edit", $data);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery shipment update failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * Attach/update the GST e-way bill number on a shipment — required by Indian
     * law once shipment value exceeds ₹50,000.
     */
    public function updateEwaybill(string $awb, string $invoiceNumber, string $ewaybillNumber): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Delhivery updateEwaybill mock: {$awb}");
            return ['success' => true];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->put("https://track.delhivery.com/api/rest/ewaybill/{$awb}/", [
                'data' => [[
                    'dcn'  => $invoiceNumber,
                    'ewbn' => $ewaybillNumber,
                ]],
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery e-way bill update failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * Fetch a document Delhivery holds for a shipment (proof of delivery, RVP QC image, etc).
     * $docType: SIGNATURE_URL | RVP_QC_IMAGE | EPOD | SELLER_RETURN_IMAGE
     */
    public function fetchDocument(string $awb, string $docType): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Delhivery fetchDocument mock: {$awb} / {$docType}");
            return ['awb' => $awb, 'doc_type' => $docType, 'url' => "https://mock.local/docs/{$awb}.pdf"];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get('https://track.delhivery.com/api/rest/fetch/pkg/document/', [
                'doc_type' => $docType,
                'waybill'  => $awb,
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery document fetch failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * One-time setup — registers a pickup location with Delhivery.
     * NOTE: endpoint/field names below are the commonly-documented Delhivery
     * "Client Warehouse" API — verify against your account's actual developer
     * portal docs before relying on this in production; some accounts get a
     * slightly different contract from Delhivery's onboarding team.
     */
    public function createWarehouse(array $data): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Delhivery createWarehouse mock: ' . ($data['name'] ?? ''));
            return ['success' => true, 'name' => $data['name'] ?? null];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post('https://track.delhivery.com/api/backend/clientwarehouse/create/', [
                'name'            => $data['name'],
                'email'           => $data['email'] ?? '',
                'phone'           => $data['phone'],
                'address'         => $data['address'],
                'city'            => $data['city'],
                'state'           => $data['state'],
                'country'         => 'India',
                'pin'             => $data['pincode'],
                'registered_name' => $data['registered_name'] ?? $data['name'],
                'return_address'  => $data['return_address'] ?? $data['address'],
                'return_pin'      => $data['return_pincode'] ?? $data['pincode'],
                'return_city'     => $data['return_city'] ?? $data['city'],
                'return_state'    => $data['return_state'] ?? $data['state'],
                'return_country'  => 'India',
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery warehouse creation failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    public function updateWarehouse(array $data): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Delhivery updateWarehouse mock: ' . ($data['name'] ?? ''));
            return ['success' => true, 'name' => $data['name'] ?? null];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post('https://track.delhivery.com/api/backend/clientwarehouse/edit/', $data);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery warehouse update failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    /**
     * Estimate freight cost before checkout/dispatch.
     * $params: origin_pincode, dest_pincode, weight_grams, payment_mode (Pre-paid|COD)
     */
    public function calculateShippingCost(array $params): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Delhivery calculateShippingCost mock: ' . json_encode($params));
            return ['total_amount' => 65.0, 'currency' => 'INR'];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get('https://track.delhivery.com/api/kinko/v1/invoice/charges/.json', [
                'md'    => 'E',
                'ss'    => 'Delivered',
                'o_pin' => $params['origin_pincode'],
                'd_pin' => $params['dest_pincode'],
                'cgm'   => $params['weight_grams'] ?? 500,
                'pt'    => $params['payment_mode'] ?? 'Pre-paid',
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery shipping cost calculation failed: ' . $res->body());
        }

        $data = $res->json();
        $first = is_array($data) ? ($data[0] ?? []) : [];

        return [
            'total_amount' => $first['total_amount'] ?? null,
            'raw'          => $first,
        ];
    }

    /**
     * Pre-generate a waybill number before booking a shipment with it (optional —
     * createShipment() already gets one back inline in most cases).
     */
    public function fetchWaybill(): string
    {
        if (app()->environment('local', 'testing', 'staging')) {
            $wb = 'MOCKWB' . rand(100000, 999999);
            Log::info("Delhivery fetchWaybill mock: {$wb}");
            return $wb;
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get('https://track.delhivery.com/waybill/api/bulk/json/', ['count' => 1]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery fetch waybill failed: ' . $res->body());
        }

        $body = trim($res->body(), "[]\" \n\t");
        if (empty($body)) {
            throw new \RuntimeException('Delhivery returned no waybill: ' . $res->body());
        }

        return $body;
    }

    /**
     * Printable shipping label / packing slip for an already-booked AWB.
     */
    public function generateShippingLabel(string $awb): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info("Delhivery generateShippingLabel mock: {$awb}");
            return ['awb' => $awb, 'label_url' => "https://mock.local/labels/{$awb}.pdf"];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->get('https://track.delhivery.com/api/p/packing_slip', ['wbns' => $awb, 'pdf' => 'true', 'pdf_size' => 'A4']);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery label generation failed: ' . $res->body());
        }

        // Delhivery's docs don't spell out the exact JSON shape for pdf=true —
        // checking a few plausible field names defensively until confirmed live.
        $lines = $res->json('packages_data') ?? $res->json('package_lines') ?? $res->json('packages') ?? [];
        $first = is_array($lines) ? ($lines[0] ?? []) : [];

        return [
            'awb'       => $awb,
            'label_url' => $first['pdf_download_link'] ?? $res->json('pdf_download_link') ?? null,
        ];
    }

    /**
     * Tell Delhivery to actually come collect the packages (without this,
     * a booked shipment just sits there — nothing tells the courier to pick up).
     */
    public function raisePickupRequest(array $data): array
    {
        if (app()->environment('local', 'testing', 'staging')) {
            Log::info('Delhivery raisePickupRequest mock: ' . json_encode($data));
            return ['success' => true, 'pickup_date' => $data['pickup_date'] ?? now()->toDateString()];
        }

        $token = config('services.delhivery.token');
        $res   = Http::withHeaders(['Authorization' => "Token {$token}"])
            ->post('https://track.delhivery.com/fm/request/new/', [
                'pickup_time'             => $data['pickup_time'] ?? '18:00:00',
                'pickup_date'             => $data['pickup_date'] ?? now()->toDateString(),
                'pickup_location'         => $data['pickup_location'] ?? config('services.delhivery.seller_name', 'DXEMPIRE'),
                'expected_package_count'  => $data['expected_package_count'] ?? 1,
            ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Delhivery pickup request failed: ' . $res->body());
        }

        return $res->json() ?? [];
    }

    private function buildDelhiveryPayload(array $payload): array
    {
        $addr        = $payload['address'] ?? [];
        $paymentMode = $payload['payment_mode'] ?? 'Prepaid'; // 'Prepaid' | 'COD' | 'Pickup' | 'REPL'
        $weightGrams = isset($payload['weight_kg']) ? $payload['weight_kg'] * 1000 : 500;

        $data = [
            'shipments' => [[
                'name'          => $addr['name'] ?? '',
                'add'           => $addr['line1'] ?? '',
                'city'          => $addr['city'] ?? '',
                'state'         => $addr['state'] ?? '',
                'country'       => 'India',
                'pin'           => $addr['pincode'] ?? '',
                'phone'         => $addr['phone'] ?? '',
                'order'         => $payload['order_number'],
                'payment_mode'  => $paymentMode,
                'cod_amount'    => $paymentMode === 'COD' ? ($payload['total'] ?? 0) : 0,
                'total_amount'  => $payload['total'] ?? 0,
                'weight'        => $weightGrams,
                'shipment_height' => $payload['height_cm'] ?? 10,
                'shipment_width'  => $payload['breadth_cm'] ?? 20,
                'shipment_length' => $payload['length_cm'] ?? 30,
                'shipping_mode' => 'Surface',
                'products_desc' => $payload['products_desc'] ?? '',
                'seller_name'   => config('services.delhivery.seller_name', 'DXEMPIRE'),
            ]],
            'pickup_location' => [
                'name' => \App\Models\Setting::get('warehouse_name', config('services.delhivery.seller_name', 'DXEMPIRE')),
            ],
        ];

        return ['format' => 'json', 'data' => json_encode($data)];
    }
}
