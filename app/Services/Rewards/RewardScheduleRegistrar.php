<?php

namespace App\Services\Rewards;

use Illuminate\Console\Scheduling\Schedule;

class RewardScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        $schedule->command('rewards:evaluate')
            ->name('rewards-evaluate')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(20)
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/rewards-evaluate.log'));
    }
}
