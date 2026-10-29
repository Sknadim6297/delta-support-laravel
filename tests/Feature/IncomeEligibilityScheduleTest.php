<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\DevelopmentMemberRoster;
use App\Services\FinancialScheduleRegistrar;
use App\Services\IncomeEligibilityScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IncomeEligibilityScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_eligibility_runs_every_two_minutes_and_crediting_commands_stay_off(): void
    {
        config(['deltasupport.package_income.enabled' => false]);
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        app(IncomeEligibilityScheduleRegistrar::class)->register($schedule);

        $this->assertCount(1, $schedule->events());
        $event = $schedule->events()[0];
        $this->assertStringContainsString('income:eligibility', (string) $event->command);
        $this->assertStringNotContainsString('package-income:accrue', (string) $event->command);
        $this->assertStringNotContainsString('roi:generate', (string) $event->command);
        $this->assertSame('*/2 * * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(
            in_array((string) config('cache.default'), ['redis', 'memcached', 'dynamodb', 'database'], true),
            $event->onOneServer
        );
        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    public function test_repeated_eligibility_checks_do_not_write_income_or_wallets(): void
    {
        config([
            'deltasupport.package_income.enabled' => false,
            'deltasupport.wallet_ledger.enabled' => false,
        ]);
        Member::query()->create([
            'member_id' => 'STELIG001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Eligibility Member',
            'mobile_no' => '9000000191',
            'email' => 'eligibility@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '4.0000',
            'working_wallet_amount' => '5.0000',
        ]);

        $this->artisan('income:eligibility', ['--date' => '2026-10-29'])->assertSuccessful();
        $this->artisan('income:eligibility', ['--date' => '2026-10-29'])->assertSuccessful();

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $member = Member::query()->where('member_id', 'STELIG001')->firstOrFail();
        $this->assertSame('4.0000', $member->roi_wallet_amount);
        $this->assertSame('5.0000', $member->working_wallet_amount);

        $lock = Cache::lock('income-eligibility', 90);
        $this->assertTrue($lock->get());
        $this->artisan('income:eligibility', ['--date' => '2026-10-29'])->assertSuccessful();
        $lock->release();
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_development_roster_is_twenty_members_without_a_cycle(): void
    {
        $parents = DevelopmentMemberRoster::parents();
        $this->assertCount(20, $parents);
        $this->assertSame(array_unique(array_keys($parents)), array_keys($parents));

        foreach ($parents as $name => $parent) {
            $seen = [$name => true];
            $cursor = $parent;
            $guard = 0;
            while ($cursor !== null) {
                $this->assertArrayHasKey($cursor, $parents);
                $this->assertArrayNotHasKey($cursor, $seen);
                $seen[$cursor] = true;
                $cursor = $parents[$cursor];
                $guard++;
                $this->assertLessThan(20, $guard);
            }
        }
    }

    public function test_development_seed_requires_the_local_confirmation(): void
    {
        $this->artisan('development:roster', ['action' => 'seed'])->assertExitCode(1);
    }
}
