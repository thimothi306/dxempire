<?php

namespace App\Listeners;

use App\Events\OrderApproved;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyPartnerOnOrderApproved
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(OrderApproved $event): void
    {
        $order = $event->order->load('dealer.user.pushTokens');

        if (!$order->dealer || !$order->dealer->user) {
            return;
        }

        try {
            $this->notifications->notify(
                $order->dealer->user,
                'order_update',
                'Order Confirmed',
                "Your order {$order->order_number} has been confirmed.",
                ['order_id' => (string) $order->id]
            );
        } catch (\Throwable $e) {
            Log::warning("Order confirmed push failed for order {$order->id}: " . $e->getMessage());
        }
    }
}
