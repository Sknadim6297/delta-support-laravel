<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class FinancialScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        // Legacy ROI, rank advancement, the accelerated payout cycle, and
        // package-income:accrue stay off the scheduler. preparePackageIncome()
        // is not called from here. schedule:run must not start them.
    }

    /**
     * Prepared daily definition. Call this only after an explicit approval to register it.
     */
    public function preparePackageIncome(Schedule $schedule): void
    {
        $schedule->command('package-income:accrue')
            ->name('package-income-daily')
            ->dailyAt((string) config('deltasupport.package_income.schedule_time', '00:10'))
            ->timezone((string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'))
            ->withoutOverlapping(120)
            ->onOneServer();
    }

    /**
     * One coordinated cycle; overlap is prevented by the cycle's own OS-level file lock, which (unlike a
     * cache mutex) is released automatically if the process dies.
     */
    private function registerAcceleratedCycle(Schedule $schedule): void
    {
        $schedule->command('financial:cycle')
            ->timezone('Asia/Kolkata')
            ->cron('*/' . (int) config('financial.accelerated.interval_minutes') . ' * * * *');
    }

    private function registerProductionCommands(Schedule $schedule): void
    {
        // Each command appends to its own file: on Windows a second `>>` redirect to a file that a
        // long-running command still holds open fails with exit code 1 before the command ever starts.
        foreach (['roi:generate' => 'roi', 'rank:advance' => 'rank'] as $command => $name) {
            $schedule->command($command)
                ->timezone('Asia/Kolkata')
                ->onOneServer()
                ->withoutOverlapping(120)
                ->everyMinute()
                ->appendOutputTo(storage_path("logs/financial-scheduler-{$name}.log"));
        }
    }
}