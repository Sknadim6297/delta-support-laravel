<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\FinancialActivationGuard;
use App\Services\FinancialControls;
use App\Services\PackageIncomeAccrual;
use App\Services\WalletLedgerPoster;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FinancialAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_the_schedule_is_present_and_an_unarmed_run_posts_nothing(): void
    {
        $commands = [];
        foreach (app(Schedule::class)->events() as $event) {
            $command = (string) $event->command;
            $commands[$command] = $event->expression.' '.$event->timezone;
        }

        $daily = $this->scheduled($commands, 'financial:daily');
        $this->assertStringContainsString('10 0 * * *', $daily);
        $this->assertStringContainsString('Asia/Kolkata', $daily);
        $this->assertStringContainsString('*/2 * * * *', $this->scheduled($commands, 'income:eligibility'));
        $this->assertStringContainsString('*/2 * * * *', $this->scheduled($commands, 'rewards:evaluate'));
        $this->assertStringContainsString('20 0 * * *', $this->scheduled($commands, 'ranks:evaluate'));

        $this->artisan('financial:daily')->assertSuccessful()->expectsOutputToContain('not armed');
        $this->artisan('financial:recover')->assertSuccessful()->expectsOutputToContain('not posting');
        $this->artisan('ranks:evaluate')->assertSuccessful()->expectsOutputToContain('not armed');
        $this->artisan('rewards:evaluate')->assertSuccessful()->expectsOutputToContain('disabled');
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_arming_refuses_the_development_database_and_an_active_emergency_stop(): void
    {
        $this->artisan('financial:arm', ['--confirm' => 'arm-financial-automation', '--database' => 'delta_support'])
            ->assertFailed()
            ->expectsOutputToContain('not the approved target');
        $this->assertFalse(FinancialControl::current()->financial_automation_armed);

        $this->arm();
        $this->artisan('financial:stop', ['--confirm' => 'stop-financial-posting'])->assertSuccessful();
        config(['deltasupport.financial_approved_database' => (string) DB::connection()->getDatabaseName()]);
        app(FinancialActivationGuard::class)->recordInitialization((string) DB::getDefaultConnection());
        $this->artisan('financial:arm', [
            '--confirm' => 'arm-financial-automation',
            '--database' => (string) DB::connection()->getDatabaseName(),
        ])->assertFailed()->expectsOutputToContain('emergency stop');
    }

    public function test_the_first_and_last_coastal_days_credit_once_and_the_edges_do_not(): void
    {
        $this->arm();
        $member = $this->member('ST100010', 'ST666666', 'coast@example.test');
        $package = $this->package('DEMO-COAST', 'Coastal Weekend');
        $this->purchase($member, $package, '2026-10-01 02:00:00', 'COAST-1');
        $first = CarbonImmutable::parse('2026-10-01', 'Asia/Kolkata')->startOfDay()->addDays(10);
        $last = $first->addDays(799);

        CarbonImmutable::setTestNow($first->subDay()->setTime(1, 0));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        CarbonImmutable::setTestNow($first->setTime(0, 30));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful();
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame('2026-10-11', PackageRoiAccrual::query()->first()->business_date->toDateString());
        $this->assertSame('0.2500', $member->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('member_id', 'ST100010')->where('wallet', 'roi')->count());

        CarbonImmutable::setTestNow($last->setTime(0, 30));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame('0.5000', $member->fresh()->roi_wallet_amount);

        CarbonImmutable::setTestNow($last->addDay()->setTime(0, 30));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame('0.5000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->where('closing_balance', '<', 0)->count());
    }

    public function test_hill_station_credits_its_daily_amount_on_the_first_eligible_day(): void
    {
        $this->arm();
        $member = $this->member('ST100011', 'ST666666', 'hill@example.test');
        $this->purchase($member, $this->package('DEMO-HILLS', 'Hill Station Retreat'), '2026-10-01 02:00:00', 'HILL-1');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();

        $this->assertSame('2.5000', PackageRoiAccrual::query()->value('amount'));
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);
    }

    public function test_level_commission_pays_an_unlocked_sponsor_and_never_pays_the_root(): void
    {
        $this->arm();
        $root = $this->member('ST666666', 'ST666666', 'root@example.test');
        $locked = $this->member('ST100005', 'ST666666', 'locked@example.test');
        $fourth = $this->member('ST100004', 'ST100005', 'fourth@example.test');
        $third = $this->member('ST100003', 'ST100004', 'third@example.test');
        $second = $this->member('ST100002', 'ST100003', 'second@example.test');
        $sponsor = $this->member('ST100001', 'ST100002', 'sponsor@example.test');
        $buyer = $this->member('ST100000', 'ST100001', 'buyer@example.test');
        $package = $this->package('DEMO-COAST', 'Coastal Weekend');

        foreach ([$locked, $fourth, $third, $second, $sponsor] as $index => $upline) {
            $this->purchase($upline, $package, '2024-01-01 02:00:00', 'OLD-'.$index);
        }
        $this->purchase($buyer, $package, '2026-10-01 02:00:00', 'BUYER-1');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful();

        $this->assertSame('0.0600', PackageLevelAccrual::query()->where('member_id', 'ST100001')->value('amount'));
        $this->assertSame('0.0600', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame(0, PackageLevelAccrual::query()->where('member_id', 'ST100005')->count());
        $this->assertSame('0.0000', $locked->fresh()->working_wallet_amount);
        $this->assertSame(0, PackageLevelAccrual::query()->where('member_id', 'ST666666')->count());
        $this->assertSame('0.0000', $root->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $root->fresh()->roi_wallet_amount);
        $this->assertSame(1, PackageLevelAccrual::query()->where('member_id', 'ST100001')->count());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata')->addDays(400));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->assertSame(1, PackageLevelAccrual::query()->where('member_id', 'ST100001')->count());
        $this->assertSame('0.5000', $buyer->fresh()->roi_wallet_amount);
    }

    public function test_a_held_lock_refuses_a_second_posting(): void
    {
        $this->arm();
        $lock = Cache::lock('package-income-2026-10-11', 120);
        $this->assertTrue($lock->get());

        try {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('already running');
            app(PackageIncomeAccrual::class)->runControlled('2026-10-11');
        } finally {
            $lock->release();
        }
    }

    public function test_a_ledger_failure_rolls_the_credit_back(): void
    {
        $this->arm();
        $member = $this->member('ST100012', 'ST666666', 'rollback@example.test');
        $this->purchase($member, $this->package('DEMO-COAST', 'Coastal Weekend'), '2026-10-01 02:00:00', 'ROLL-1');
        $poster = Mockery::mock(WalletLedgerPoster::class);
        $poster->shouldReceive('append')->andThrow(new RuntimeException('ledger failed'));
        $this->app->instance(WalletLedgerPoster::class, $poster);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        try {
            app(PackageIncomeAccrual::class)->runControlled('2026-10-11');
            $this->fail('The ledger failure should stop the credit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ledger failed', $exception->getMessage());
        }

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_recovery_retries_a_failed_day_and_does_not_repeat_a_success(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 01:00:00', 'Asia/Kolkata'));
        \App\Models\PackageIncomeRun::query()->create([
            'business_date' => '2026-10-10',
            'status' => 'failed',
            'credited_roi' => 0,
            'credited_commissions' => 0,
            'message' => 'The previous run failed.',
        ]);

        $this->artisan('financial:recover')->assertSuccessful();
        $this->assertSame('succeeded', \App\Models\PackageIncomeRun::query()->whereDate('business_date', '2026-10-10')->latest('id')->value('status'));
        $this->assertSame('succeeded', \App\Models\PackageIncomeRun::query()->whereDate('business_date', '2026-10-11')->latest('id')->value('status'));
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        $before = \App\Models\PackageIncomeRun::query()->count();
        $this->artisan('financial:recover')->assertSuccessful()->expectsOutputToContain('already succeeded');
        $this->assertSame($before, \App\Models\PackageIncomeRun::query()->count());
    }

    public function test_the_emergency_stop_blocks_posting_and_the_dashboard_cannot_credit_a_wallet(): void
    {
        $this->arm();
        $member = $this->member('ST100013', 'ST666666', 'stop@example.test');
        $this->purchase($member, $this->package('DEMO-COAST', 'Coastal Weekend'), '2026-10-01 02:00:00', 'STOP-1');
        $admin = User::factory()->create(['is_admin' => true, 'must_change_password' => false]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:stop', ['--confirm' => 'stop-financial-posting'])->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful()->expectsOutputToContain('Emergency stop');
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertFalse(app(FinancialControls::class)->automaticPostingAllowed());

        $this->actingAs($admin)->get(route('admin.settings.financial'))
            ->assertOk()
            ->assertSeeText('Financial Automation')
            ->assertSeeText('Emergency stop')
            ->assertDontSeeText('Credit wallet');

        $this->actingAs($admin)->put(route('admin.settings.financial.update'), [
            'financial_emergency_stop' => '0',
        ])->assertRedirect();
        $this->assertTrue(FinancialControl::current()->financial_automation_armed);
        $this->assertFalse(FinancialControl::current()->financial_emergency_stop);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
        $this->assertFalse(FinancialControl::current()->withdrawal_requests_enabled);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_an_empty_database_run_records_success_without_a_credit(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));

        $this->artisan('financial:daily')->assertSuccessful();

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame('succeeded', \App\Models\PackageIncomeRun::query()->value('status'));
    }

    /**
     * @param  array<string, string>  $commands
     */
    private function scheduled(array $commands, string $name): string
    {
        foreach ($commands as $command => $expression) {
            if (str_contains($command, $name)) {
                return $expression;
            }
        }

        $this->fail($name.' is not scheduled.');
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function member(string $memberId, ?string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId === null ? null : 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $name): Package
    {
        return Package::query()->firstOrCreate(['code' => $code], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $name,
            'price_usd' => $code === 'DEMO-HILLS' ? '1000.0000' : '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $reviewedAt, string $reference): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => $reference,
            'status' => 'approved',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
