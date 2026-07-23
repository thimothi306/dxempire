<?php

namespace App\Jobs;

use App\Events\LowStockAlert;
use App\Integrations\Notifications\ExpoNotificationService;
use App\Models\Product;
use App\Models\PushToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LowStockCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpoNotificationService $expo): void
    {
        $thresholds = Setting::getJson('low_stock_threshold', [
            'phone'  => 10,
            'laptop' => 5,
        ]);

        $counts = Product::inStock()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $alerts = [];

        foreach ($thresholds as $category => $threshold) {
            $current = $counts[$category] ?? 0;
            if ($current < $threshold) {
                $alerts[] = ['category' => $category, 'count' => $current, 'threshold' => $threshold];
                event(new LowStockAlert($category, $current, $threshold));
            }
        }

        if (empty($alerts)) {
            return;
        }

        // Push alert to all super_admin users
        $admins = User::where('role', 'super_admin')
            ->where('is_active', true)
            ->with('pushTokens')
            ->get();

        $message = collect($alerts)
            ->map(fn($a) => ucfirst($a['category']) . ': ' . $a['count'] . ' left')
            ->join(', ');

        foreach ($admins as $admin) {
            foreach ($admin->pushTokens as $pt) {
                try {
                    $expo->send(
                        $pt->token,
                        'Low Stock Alert',
                        $message,
                        ['screen' => 'Inventory', 'alerts' => $alerts]
                    );
                } catch (\Throwable $e) {
                    Log::warning("Low stock push failed for user {$admin->id}: " . $e->getMessage());
                }
            }
        }

        Log::info('Low stock check completed. Alerts: ' . $message);
    }
}
