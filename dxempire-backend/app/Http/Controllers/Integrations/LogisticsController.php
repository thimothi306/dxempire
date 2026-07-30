<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Integrations\Logistics\DelhiveryService;
use App\Integrations\Logistics\LogisticsFactory;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogisticsController extends Controller
{
    use ApiResponse;

    /**
     * Create a shipment for a packed order and update AWB.
     */
    public function createShipment(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'weight_kg'   => ['nullable', 'numeric', 'min:0.1'],
            'length_cm'   => ['nullable', 'numeric', 'min:1'],
            'breadth_cm'  => ['nullable', 'numeric', 'min:1'],
            'height_cm'   => ['nullable', 'numeric', 'min:1'],
        ]);

        if (!in_array($order->status, ['approved', 'packed'])) {
            return $this->error("Order must be approved or packed to create a shipment. Status: {$order->status}.", 422);
        }

        $order->loadMissing(['items.product', 'dealer.user']);
        $dealer = $order->dealer;

        $payload = [
            'order_number' => $order->order_number,
            'total'        => $order->total_amount,
            'weight_kg'    => $request->input('weight_kg', 0.5 * $order->items->count()),
            'length_cm'    => $request->input('length_cm', 30),
            'breadth_cm'   => $request->input('breadth_cm', 20),
            'height_cm'    => $request->input('height_cm', 10),
            'address'      => [
                'name'    => $dealer?->user?->name ?? 'Customer',
                'phone'   => $dealer?->user?->phone ?? '',
                'line1'   => $dealer?->business_name ?? 'N/A',
                // Dealer model has no dedicated `city` column today — left blank
                // rather than guessing, since a wrong city can misroute delivery.
                'city'    => '',
                'state'   => $dealer?->state ?? '',
                'pincode' => $dealer?->pincode ?? '',
            ],
            'items' => $order->items->map(fn($i) => [
                'name'     => "{$i->product?->brand} {$i->product?->model}",
                'units'    => 1,
                'selling_price' => $i->line_total,
            ])->toArray(),
        ];

        try {
            $provider = LogisticsFactory::make();
            $result   = $provider->createShipment($payload);
        } catch (\RuntimeException $e) {
            Log::error("Logistics createShipment failed for order {$order->order_number}: " . $e->getMessage());
            return $this->error('Logistics provider error: ' . $e->getMessage(), 502);
        }

        $order->update([
            'awb_number'         => $result['awb'],
            'logistics_provider' => class_basename($provider),
        ]);

        AuditLog::record(
            auth()->id(),
            'order.shipment_created',
            Order::class,
            $order->id,
            [],
            ['awb' => $result['awb']]
        );

        return $this->success([
            'order_number' => $order->order_number,
            'awb'          => $result['awb'],
            'tracking_url' => $result['tracking_url'],
            'label_url'    => $result['label_url'],
        ], 'Shipment created successfully.');
    }

    /**
     * Track a shipment by AWB number.
     */
    public function track(Request $request, string $awb): JsonResponse
    {
        try {
            $provider = LogisticsFactory::make();
            $result   = $provider->trackShipment($awb);
        } catch (\RuntimeException $e) {
            return $this->error('Tracking failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result);
    }

    /**
     * Cancel a shipment by AWB (also checks if order exists).
     */
    public function cancel(Request $request, string $awb): JsonResponse
    {
        try {
            $provider = LogisticsFactory::make();
            $success  = $provider->cancelShipment($awb);
        } catch (\RuntimeException $e) {
            return $this->error('Cancellation failed: ' . $e->getMessage(), 502);
        }

        if (!$success) {
            return $this->error('Logistics provider could not cancel shipment.', 422);
        }

        // Clear AWB from order if found
        $order = Order::where('awb_number', $awb)->first();
        if ($order) {
            $order->update(['awb_number' => null]);
            AuditLog::record(auth()->id(), 'order.shipment_cancelled', Order::class, $order->id, ['awb' => $awb], []);
        }

        return $this->success(null, "Shipment {$awb} cancelled.");
    }

    /**
     * One-time pickup-location registration with Delhivery. super_admin only —
     * this registers real business info with a live courier account.
     */
    public function createWarehouse(Request $request): JsonResponse
    {
        $data = array_merge($this->warehouseFromSettings(), $request->all());

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name'    => ['required', 'string', 'max:100'],
            'phone'   => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'city'    => ['required', 'string', 'max:100'],
            'state'   => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'max:10'],
            'email'   => ['nullable', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->error('Missing warehouse details — fill them in under Settings first.', 422, $validator->errors()->toArray());
        }

        try {
            $result = (new DelhiveryService())->createWarehouse($validator->validated());
        } catch (\RuntimeException $e) {
            Log::error('Delhivery warehouse creation failed: ' . $e->getMessage());
            return $this->error('Warehouse registration failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result, 'Warehouse registered with Delhivery.');
    }

    private function warehouseFromSettings(): array
    {
        return array_filter([
            'name'    => \App\Models\Setting::get('warehouse_name'),
            'phone'   => \App\Models\Setting::get('warehouse_phone'),
            'email'   => \App\Models\Setting::get('warehouse_email'),
            'address' => \App\Models\Setting::get('warehouse_address'),
            'city'    => \App\Models\Setting::get('warehouse_city'),
            'state'   => \App\Models\Setting::get('warehouse_state'),
            'pincode' => \App\Models\Setting::get('warehouse_pincode'),
        ]);
    }

    public function updateWarehouse(Request $request): JsonResponse
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'pin'     => ['required', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $result = (new DelhiveryService())->updateWarehouse($request->all());
        } catch (\RuntimeException $e) {
            Log::error('Delhivery warehouse update failed: ' . $e->getMessage());
            return $this->error('Warehouse update failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result, 'Warehouse updated.');
    }

    /**
     * Edit an already-booked shipment (blocked once Dispatched/Delivered — Delhivery
     * only allows this while Manifested/In Transit/Pending).
     */
    public function updateShipment(Request $request, string $awb): JsonResponse
    {
        $request->validate([
            'name'            => ['nullable', 'string'],
            'phone'           => ['nullable', 'string'],
            'pt'              => ['nullable', 'in:COD,Pre-paid'],
            'add'             => ['nullable', 'string'],
            'products_desc'   => ['nullable', 'string'],
            'gm'              => ['nullable', 'numeric', 'min:1'],
            'shipment_height' => ['nullable', 'numeric'],
            'shipment_width'  => ['nullable', 'numeric'],
            'shipment_length' => ['nullable', 'numeric'],
        ]);

        try {
            $result = (new DelhiveryService())->updateShipment(array_merge(['waybill' => $awb], $request->all()));
        } catch (\RuntimeException $e) {
            return $this->error('Shipment update failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result, 'Shipment updated.');
    }

    /**
     * Attach a GST e-way bill to a shipment — required once shipment value exceeds ₹50,000.
     */
    public function updateEwaybill(Request $request, string $awb): JsonResponse
    {
        $request->validate([
            'invoice_number'  => ['required', 'string'],
            'ewaybill_number' => ['required', 'string'],
        ]);

        try {
            $result = (new DelhiveryService())->updateEwaybill($awb, $request->invoice_number, $request->ewaybill_number);
        } catch (\RuntimeException $e) {
            return $this->error('E-way bill update failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result, 'E-way bill updated.');
    }

    /**
     * Fetch a document Delhivery holds for a shipment (POD, RVP QC image, etc).
     */
    public function fetchDocument(Request $request, string $awb): JsonResponse
    {
        $request->validate([
            'doc_type' => ['required', 'in:SIGNATURE_URL,RVP_QC_IMAGE,EPOD,SELLER_RETURN_IMAGE'],
        ]);

        try {
            $result = (new DelhiveryService())->fetchDocument($awb, $request->doc_type);
        } catch (\RuntimeException $e) {
            return $this->error('Document fetch failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result);
    }

    public function calculateShippingCost(Request $request): JsonResponse
    {
        $request->validate([
            'origin_pincode' => ['required', 'string'],
            'dest_pincode'   => ['required', 'string'],
            'weight_grams'   => ['nullable', 'numeric', 'min:1'],
            'payment_mode'   => ['nullable', 'in:Pre-paid,COD'],
        ]);

        try {
            $result = (new DelhiveryService())->calculateShippingCost($request->all());
        } catch (\RuntimeException $e) {
            return $this->error('Shipping cost calculation failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result);
    }

    public function fetchWaybill(): JsonResponse
    {
        try {
            $waybill = (new DelhiveryService())->fetchWaybill();
        } catch (\RuntimeException $e) {
            return $this->error('Fetch waybill failed: ' . $e->getMessage(), 502);
        }

        return $this->success(['waybill' => $waybill]);
    }

    public function generateLabel(string $awb): JsonResponse
    {
        try {
            $result = (new DelhiveryService())->generateShippingLabel($awb);
        } catch (\RuntimeException $e) {
            return $this->error('Label generation failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result);
    }

    public function raisePickupRequest(Request $request): JsonResponse
    {
        $request->validate([
            'pickup_date'            => ['nullable', 'date'],
            'pickup_time'            => ['nullable', 'string'],
            'pickup_location'        => ['nullable', 'string'],
            'expected_package_count' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = (new DelhiveryService())->raisePickupRequest($request->all());
        } catch (\RuntimeException $e) {
            return $this->error('Pickup request failed: ' . $e->getMessage(), 502);
        }

        return $this->success($result, 'Pickup requested.');
    }
}
