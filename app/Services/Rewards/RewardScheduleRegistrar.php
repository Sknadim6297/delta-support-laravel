<?php

namespace App\Services\Rewards;

use Illuminate\Console\Scheduling\Schedule;

class RewardScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        $schedule->command('rewards:evaluate')
            ->name('rewards-evaluate')
            ->everyTwoMinutes()
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/rewards-evaluate.log'));
    }
}
