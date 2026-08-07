<?php

namespace App\Jobs;

use App\Events\LowStockAlert;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LowStockCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notifier): void
    {
        $thresholds = Setting::getJson('low_stock_threshold', [
            'phone'  => 10,
            'laptop' => 5,
        ]);

        // Per-grade, not just per-category — "phones are low" is far less
        // actionable than "S2 phones are low" for a grade-driven business.
        $rows = Product::inStock()
            ->selectRaw('category, grade, count(*) as count')
            ->groupBy('category', 'grade')
            ->get();

        $alerts = [];

        foreach ($rows as $row) {
            $threshold = $thresholds[$row->category] ?? null;
            if ($threshold === null || !$row->grade) {
                continue;
            }
            if ($row->count < $threshold) {
                $alerts[] = ['category' => $row->category, 'grade' => $row->grade, 'count' => $row->count, 'threshold' => $threshold];
                event(new LowStockAlert($row->category, $row->count, $threshold));
            }
        }

        if (empty($alerts)) {
            return;
        }

        $recipients = User::whereIn('role', ['super_admin', 'warehouse_staff', 'warehouse_manager'])
            ->where('is_active', true)
            ->with('pushTokens')
            ->get();

        $message = collect($alerts)
            ->map(fn($a) => ucfirst($a['category']) . ' ' . $a['grade'] . ': ' . $a['count'] . ' left')
            ->join(', ');

        // Keep push-payload data flat (NotificationService stringifies each
        // top-level value for the push provider) — pass a count, not the
        // nested $alerts array; the full detail is available on-screen via
        // the /inventory/low-stock endpoint the dashboard widget calls.
        $notifier->notifyMany($recipients, 'stock_alert', 'Low Stock Alert', $message, ['alert_count' => count($alerts)]);

        Log::info('Low stock check completed. Alerts: ' . $message);
    }
}
