<?php

namespace App\Services\Rewards;

use App\Services\ScheduleLockSupport;
use Illuminate\Console\Scheduling\Schedule;

class RewardScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        $event = $schedule->command('rewards:evaluate')
            ->name('rewards-evaluate')
            ->everyTwoMinutes()
            ->timezone('Asia/Kolkata')
            ->appendOutputTo(storage_path('logs/rewards-evaluate.log'));
        app(ScheduleLockSupport::class)->apply($event, 20);
    }
}
