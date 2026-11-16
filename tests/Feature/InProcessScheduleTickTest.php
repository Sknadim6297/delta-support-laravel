<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\PackageRoiAccrual;
use App\Services\InProcessScheduleTick;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InProcessScheduleTickTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_dry_run_marks_financial_daily_due_at_schedule_time_without_executing(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $daily = collect($results)->firstWhere('command', 'financial:daily');
        $this->assertTrue($daily['due']);
        $this->assertFalse($daily['ran']);
        $this->assertSame('dry-run preview only', $daily['skipped']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_recover_is_due_at_minute_25_kolkata_not_utc_55(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 13:25:00', 'Asia/Kolkata'));
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertTrue(collect($results)->firstWhere('command', 'financial:recover')['due']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 13:55:00', 'Asia/Kolkata'));
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertFalse(collect($results)->firstWhere('command', 'financial:recover')['due']);
    }

    public function test_verify_only_never_executes_due_jobs_including_financial(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        $this->arm();
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_VERIFY);
        foreach ($results as $row) {
            $this->assertFalse($row['ran']);
            if ($row['due']) {
                $this->assertSame('verify-only: execution disabled', $row['skipped']);
            }
        }
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_live_mode_blocks_financial_jobs_without_allow_flag(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        config(['deltasupport.scheduler.allow_financial' => false]);
        putenv('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=false');

        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_LIVE);
        $daily = collect($results)->firstWhere('command', 'financial:daily');
        $this->assertTrue($daily['due']);
        $this->assertFalse($daily['ran']);
        $this->assertStringContainsString('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', (string) $daily['skipped']);
    }

    public function test_overlapping_lock_skips_second_execution(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:20:00', 'Asia/Kolkata'));
        config(['deltasupport.scheduler.allow_financial' => true]);
        putenv('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=true');

        $lock = Cache::lock('inprocess-ranks-evaluate', 1800);
        $this->assertTrue($lock->get());
        try {
            $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_LIVE);
            $ranks = collect($results)->firstWhere('command', 'ranks:evaluate');
            $this->assertTrue($ranks['due']);
            $this->assertFalse($ranks['ran']);
            $this->assertSame('overlap lock held', $ranks['skipped']);
        } finally {
            $lock->release();
        }
    }

    public function test_duplicate_tick_same_minute_is_skipped(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:20:00', 'Asia/Kolkata'));
        config(['deltasupport.scheduler.allow_financial' => true]);
        putenv('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=true');

        $first = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_LIVE);
        $second = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_LIVE);

        $this->assertTrue(collect($first)->firstWhere('command', 'ranks:evaluate')['ran']);
        $dup = collect($second)->firstWhere('command', 'ranks:evaluate');
        $this->assertFalse($dup['ran']);
        $this->assertSame('duplicate tick for this Asia/Kolkata minute', $dup['skipped']);
    }

    public function test_command_failure_is_caught_and_other_due_jobs_continue(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:20:00', 'Asia/Kolkata'));
        config(['deltasupport.scheduler.allow_financial' => true]);
        putenv('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=true');

        Artisan::shouldReceive('call')
            ->once()
            ->with('ranks:evaluate', [])
            ->andThrow(new RuntimeException('forced ranks failure'));
        Artisan::shouldReceive('call')->with('income:eligibility', [])->andReturn(0);
        Artisan::shouldReceive('call')->with('rewards:evaluate', [])->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_LIVE);
        $ranks = collect($results)->firstWhere('command', 'ranks:evaluate');
        $this->assertFalse($ranks['ran']);
        $this->assertSame(1, $ranks['exit_code']);
        $this->assertSame('forced ranks failure', $ranks['error']);
        $this->assertStringContainsString('exception caught', (string) $ranks['skipped']);
        $this->assertTrue(collect($results)->firstWhere('command', 'income:eligibility')['ran']);
    }

    public function test_midnight_rollover_uses_kolkata_schedule_time(): void
    {
        config(['deltasupport.package_income.schedule_time' => '00:10']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 23:59:00', 'Asia/Kolkata'));
        $before = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertFalse(collect($before)->firstWhere('command', 'financial:daily')['due']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 00:00:00', 'Asia/Kolkata'));
        $midnight = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertFalse(collect($midnight)->firstWhere('command', 'financial:daily')['due']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 00:10:00', 'Asia/Kolkata'));
        $due = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertTrue(collect($due)->firstWhere('command', 'financial:daily')['due']);
        $this->assertTrue(collect($due)->firstWhere('command', 'income:eligibility')['due']);
    }

    public function test_missed_tick_at_0010_is_not_replayed_later_same_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:11:00', 'Asia/Kolkata'));
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertFalse(collect($results)->firstWhere('command', 'financial:daily')['due']);
        $this->assertFalse(collect($results)->firstWhere('command', 'financial:recover')['due']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:25:00', 'Asia/Kolkata'));
        $recoverDue = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_DRY_RUN);
        $this->assertTrue(collect($recoverDue)->firstWhere('command', 'financial:recover')['due']);
        $this->assertFalse(collect($recoverDue)->firstWhere('command', 'financial:daily')['due']);
    }

    public function test_missed_date_effect_summary_documents_no_old_backfill(): void
    {
        $summary = app(InProcessScheduleTick::class)->missedDateEffectSummary();
        $this->assertStringContainsString('current Asia/Kolkata business date', $summary['daily_posts']);
        $this->assertStringContainsString('Yesterday', $summary['recover_scope']);
        $this->assertStringContainsString('Not processed', $summary['older_gaps']);
    }

    public function test_artisan_verify_only_and_force_guards(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        $this->assertNotSame('Delta_support_db', (string) DB::connection()->getDatabaseName());

        $this->artisan('deltasupport:scheduler-tick', ['--verify-only' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('verify')
            ->expectsOutputToContain('does not use Symfony Process');

        $this->artisan('deltasupport:scheduler-tick', ['--force' => 'financial:daily'])
            ->assertFailed()
            ->expectsOutputToContain('cannot run financial posting commands');
    }

    public function test_default_cli_mode_without_flags_does_not_post(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        $this->arm();
        putenv('DELTASUPPORT_SCHEDULER_MODE=dry-run');
        putenv('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL=false');

        $this->artisan('deltasupport:scheduler-tick')->assertSuccessful();
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }
}
