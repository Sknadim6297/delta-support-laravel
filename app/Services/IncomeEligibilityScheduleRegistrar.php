<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class IncomeEligibilityScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        $event = $schedule->command('income:eligibility')
            ->name('income-eligibility')
            ->everyTwoMinutes()
            ->timezone('Asia/Kolkata')
            ->appendOutputTo(storage_path('logs/income-eligibility.log'));
        app(ScheduleLockSupport::class)->apply($event, 10);
    }
}
