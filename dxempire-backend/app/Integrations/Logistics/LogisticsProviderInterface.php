<?php

namespace App\Integrations\Logistics;

interface LogisticsProviderInterface
{
    /**
     * Create a shipment and return AWB number + provider tracking URL.
     *
     * @param  array  $payload  { order_number, weight_kg, address, items[] }
     * @return array  { awb, tracking_url, label_url }
     * @throws \RuntimeException
     */
    public function createShipment(array $payload): array;

    /**
     * Track a shipment by AWB.
     *
     * @return array  { awb, status, events[], estimated_delivery }
     * @throws \RuntimeException
     */
    public function trackShipment(string $awb): array;

    /**
     * Cancel a shipment by AWB.
     *
     * @throws \RuntimeException
     */
    public function cancelShipment(string $awb): bool;
}
