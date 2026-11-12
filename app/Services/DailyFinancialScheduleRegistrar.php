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
        $log = $this->outputLogPath();

        $daily = $schedule->command('financial:daily')
            ->name('financial-daily')
            ->dailyAt((string) config('deltasupport.package_income.schedule_time', '00:10'))
            ->timezone($timezone)
            ->appendOutputTo($log);
        $this->locks->apply($daily, 120);

        $recover = $schedule->command('financial:recover')
            ->name('financial-recover')
            ->hourlyAt(25)
            ->timezone($timezone)
            ->appendOutputTo($log);
        $this->locks->apply($recover, 30);

        $ranks = $schedule->command('ranks:evaluate')
            ->name('ranks-evaluate')
            ->dailyAt('00:20')
            ->timezone($timezone)
            ->appendOutputTo($log);
        $this->locks->apply($ranks, 30);
    }

    /**
     * Scheduled output only appears when schedule:run fires these events.
     * Manual artisan invocations do not write this file.
     */
    public function outputLogPath(): string
    {
        $path = storage_path('logs/financial-daily.log');
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $path;
    }
}
