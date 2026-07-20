<?php

namespace App\Console;

use App\Console\Commands\SendPaymentOverdueNotifications;
use App\Jobs\LowStockCheckJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Every 30 minutes: check if any category falls below threshold
        $schedule->job(new LowStockCheckJob)->everyThirtyMinutes();

        // Every morning at 9am: push payment overdue reminders to partners
        $schedule->command(SendPaymentOverdueNotifications::class)->dailyAt('09:00');

        // Nightly: recalculate dealer credit_used from unpaid orders (Phase 5+)
        // $schedule->job(new DealerCreditSyncJob)->dailyAt('00:00');

        // Nightly: recalculate demand forecasts (Phase 9)
        // $schedule->job(new DemandForecastJob)->dailyAt('00:30');

        // Sunday 23:00: weekly P&L summary email (Phase 7)
        // $schedule->job(new WeeklyReportJob)->weeklyOn(0, '23:00');

        // 1st of month: GST summary to S3 (Phase 7)
        // $schedule->job(new MonthlyStatementJob)->monthlyOn(1, '01:00');

        // Every 5 minutes: retry failed notifications (Phase 10)
        // $schedule->job(new FailedNotificationRetry)->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
