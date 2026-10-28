<?php

namespace Tests\Feature;

use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeIsolatedTest;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class PackageIncomeManualTestCommandTest extends TestCase
{
    public function test_the_manual_command_refuses_the_main_database_and_stays_unscheduled(): void
    {
        config([
            'database.connections.income_test.database' => 'delta_support',
            'database.connections.mysql.database' => 'delta_support',
            'deltasupport.package_income.enabled' => false,
        ]);

        $this->artisan('package-income:test', [
            'action' => 'run',
            '--date' => '2026-11-06',
            '--confirm' => 'isolated-test',
        ])->assertExitCode(1);

        config([
            'database.connections.income_test.database' => 'delta_support_income_test',
            'database.connections.mysql.database' => 'delta_support',
        ]);
        $this->artisan('package-income:test', [
            'action' => 'run',
            '--date' => '2026-11-06',
        ])->assertExitCode(1);

        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertSame('delta_support_income_test', PackageIncomeIsolatedTest::DATABASE);
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }
}
