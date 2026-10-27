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

    /**
     * @return list<string>
     */
    private function scheduledCommands(Schedule $schedule): array
    {
        return array_map(fn ($event): string => (string) $event->command, $schedule->events());
    }
}
