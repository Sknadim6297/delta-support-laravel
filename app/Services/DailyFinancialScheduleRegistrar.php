<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class DailyFinancialScheduleRegistrar
{
    public function __construct(private readonly FinancialControls $controls, private readonly ScheduleLockSupport $locks)
    {
    }

    public function register(Schedule $schedule): void
    {
        if (! $this->controls->enabled('daily_financial_cycle_enabled')) {
            return;
        }

        $event = $schedule->command('financial:daily')
            ->name('financial-daily')
            ->dailyAt((string) config('deltasupport.package_income.schedule_time', '00:10'))
            ->timezone((string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'))
            ->appendOutputTo(storage_path('logs/financial-daily.log'));
        $this->locks->apply($event, 120);
    }
}
