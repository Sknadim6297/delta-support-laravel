<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;

class FinancialScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        // Legacy ROI, level commission, rank advancement, and the accelerated
        // payout cycle stay off the scheduler. The command classes and the
        // definitions below remain for a later review. schedule:run must not start them.
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
        foreach (['roi:generate' => 'roi', 'commission:generate-level' => 'commission', 'rank:advance' => 'rank'] as $command => $name) {
            $schedule->command($command)
                ->timezone('Asia/Kolkata')
                ->onOneServer()
                ->withoutOverlapping(120)
                ->everyMinute()
                ->appendOutputTo(storage_path("logs/financial-scheduler-{$name}.log"));
        }
    }
}