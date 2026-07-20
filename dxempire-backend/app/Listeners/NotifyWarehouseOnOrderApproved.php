<?php

namespace App\Listeners;

use App\Events\OrderApproved;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyWarehouseOnOrderApproved
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(OrderApproved $event): void
    {
        $order = $event->order;

        $staff = User::where('role', 'warehouse_staff')
            ->where('is_active', true)
            ->with('pushTokens')
            ->get();

        try {
            $this->notifications->notifyMany(
                $staff,
                'task_assigned',
                'New Order to Pick',
                "Order {$order->order_number} approved. Start picking now.",
                ['order_id' => (string) $order->id]
            );
        } catch (\Throwable $e) {
            Log::warning("Warehouse push failed for order {$order->id}: " . $e->getMessage());
        }

        AuditLog::record(null, 'order.approved', \App\Models\Order::class, $order->id, [], ['status' => 'approved']);
    }
}
