<?php

namespace App\Listeners;

use App\Events\OrderDispatched;
use App\Integrations\WhatsApp\WhatsAppFactory;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyDealerOnOrderDispatched
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(OrderDispatched $event): void
    {
        $order = $event->order->load('dealer.user.pushTokens');

        if (!$order->dealer || !$order->dealer->user) {
            return;
        }

        $user = $order->dealer->user;

        try {
            $this->notifications->notify(
                $user,
                'order_update',
                'Order Dispatched',
                "Order {$order->order_number} dispatched via {$order->logistics_provider}. AWB: {$order->awb_number}",
                ['order_id' => (string) $order->id, 'awb' => (string) ($order->awb_number ?? '')]
            );
        } catch (\Throwable $e) {
            Log::warning("Dispatch push failed for dealer user {$user->id}: " . $e->getMessage());
        }

        if ($user->phone) {
            try {
                WhatsAppFactory::make()->sendTemplate(
                    $user->phone,
                    'order_dispatched',
                    [
                        'order_number' => $order->order_number,
                        'provider'     => $order->logistics_provider ?? 'courier',
                        'awb'          => $order->awb_number ?? 'N/A',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("Dispatch WhatsApp failed for dealer user {$user->id}: " . $e->getMessage());
            }
        }
    }
}
