<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPaymentOverdueNotifications extends Command
{
    protected $signature   = 'notifications:payment-overdue {--days=30 : Days since order delivery before flagging overdue}';
    protected $description = 'Push payment overdue reminders to partners with outstanding dues';

    public function handle(NotificationService $notifications): int
    {
        $days = (int) $this->option('days');

        $dealers = Dealer::where('credit_used', '>', 0)
            ->whereHas('orders', function ($q) use ($days) {
                $q->where('status', 'delivered')
                  ->where('payment_status', 'unpaid')
                  ->where('delivered_at', '<', now()->subDays($days));
            })
            ->with('user.pushTokens')
            ->get();

        $sent = 0;

        foreach ($dealers as $dealer) {
            if (!$dealer->user) {
                continue;
            }

            $outstanding = number_format((float) $dealer->credit_used, 2);

            try {
                $notifications->notify(
                    $dealer->user,
                    'payment_due',
                    'Payment Overdue',
                    "₹{$outstanding} is overdue. Clear dues to avoid account suspension.",
                    []
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Payment overdue push failed for dealer {$dealer->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent payment overdue notifications to {$sent} partner(s).");

        return self::SUCCESS;
    }
}
