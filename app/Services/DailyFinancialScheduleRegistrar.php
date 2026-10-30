<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class DailyFinancialScheduleRegistrar
{
    public function __construct(private readonly ScheduleLockSupport $locks)
    {
    }

    public function register(Schedule $schedule): void
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $daily = $schedule->command('financial:daily')
            ->name('financial-daily')
            ->dailyAt((string) config('deltasupport.package_income.schedule_time', '00:10'))
            ->timezone($timezone)
            ->appendOutputTo(storage_path('logs/financial-daily.log'));
        $this->locks->apply($daily, 120);

        $recover = $schedule->command('financial:recover')
            ->name('financial-recover')
            ->hourlyAt(25)
            ->timezone($timezone)
            ->appendOutputTo(storage_path('logs/financial-daily.log'));
        $this->locks->apply($recover, 30);

        $ranks = $schedule->command('ranks:evaluate')
            ->name('ranks-evaluate')
            ->dailyAt('00:20')
            ->timezone($timezone)
            ->appendOutputTo(storage_path('logs/financial-daily.log'));
        $this->locks->apply($ranks, 30);
    }
}
