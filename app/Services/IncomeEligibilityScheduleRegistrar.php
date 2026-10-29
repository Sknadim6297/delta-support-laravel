<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class IncomeEligibilityScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        $schedule->command('income:eligibility')
            ->name('income-eligibility')
            ->everyTwoMinutes()
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/income-eligibility.log'));
    }
}
