<?php

namespace Tests\Unit;

use App\Services\FinancialScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class FinancialScheduleRegistrarTest extends TestCase
{
    public function test_scheduler_does_not_register_legacy_financial_commands(): void
    {
        config(['financial.accelerated.enabled' => false]);
        $schedule = new Schedule;

        app(FinancialScheduleRegistrar::class)->register($schedule);

        $this->assertSame([], $this->scheduledCommands($schedule));
    }

    public function test_accelerated_mode_does_not_register_the_financial_cycle(): void
    {
        config([
            'financial.accelerated.enabled' => true,
            'financial.accelerated.interval_minutes' => 2,
        ]);
        $schedule = new Schedule;

        app(FinancialScheduleRegistrar::class)->register($schedule);

        $this->assertSame([], $this->scheduledCommands($schedule));
    }

    public function test_package_income_schedule_is_prepared_and_not_registered(): void
    {
        $registered = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($registered);
        $this->assertSame([], $registered->events());

        $prepared = new Schedule;
        app(FinancialScheduleRegistrar::class)->preparePackageIncome($prepared);
        $event = $prepared->events()[0];

        $this->assertStringContainsString('package-income:accrue', (string) $event->command);
        $this->assertSame('10 0 * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(
            in_array((string) config('cache.default'), ['redis', 'memcached', 'dynamodb', 'database'], true),
            $event->onOneServer
        );
        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    /**
     * @return list<string>
     */
    private function scheduledCommands(Schedule $schedule): array
    {
        return array_map(fn ($event): string => (string) $event->command, $schedule->events());
    }
}
