<?php

namespace App\Listeners;

use App\Events\ProductReceived;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyQcTeam
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(ProductReceived $event): void
    {
        $product = $event->product;

        $qcEngineers = User::where('role', 'qc_engineer')
            ->where('is_active', true)
            ->with('pushTokens')
            ->get();

        try {
            $this->notifications->notifyMany(
                $qcEngineers,
                'task_assigned',
                'New Item for QC',
                "{$product->brand} {$product->model} ({$product->category}) received and awaiting QC.",
                ['product_id' => (string) $product->id]
            );
        } catch (\Throwable $e) {
            Log::warning("QC push failed for product {$product->id}: " . $e->getMessage());
        }
    }
}
