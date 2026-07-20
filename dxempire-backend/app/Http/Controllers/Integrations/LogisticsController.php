<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
                'city'    => $dealer?->state ?? '',
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
}
