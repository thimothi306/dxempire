<?php

namespace App\Listeners;

use App\Events\StockAdded;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyPartnersOnStockAdded
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(StockAdded $event): void
    {
        $product = $event->product;

        $partners = User::where('role', 'b2b_partner')
            ->where('is_active', true)
            ->with('pushTokens')
            ->get();

        if ($partners->isEmpty()) {
            return;
        }

        try {
            $this->notifications->notifyMany(
                $partners,
                'stock_alert',
                'New Stock Available',
                "{$product->brand} {$product->model} (Grade {$product->grade}) is now available.",
                ['product_id' => (string) $product->id]
            );
        } catch (\Throwable $e) {
            Log::warning("Stock alert push failed for product {$product->id}: " . $e->getMessage());
        }
    }
}
