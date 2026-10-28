<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeAccrualTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_without_an_approved_eligible_package_earn_nothing(): void
    {
        $active = $this->member('STNONE001', 'ST666666');
        $pending = $this->member('STPEND001', 'ST666666');
        $rejected = $this->member('STREJ0001', 'ST666666');
        $verify = $this->member('STVER0001', 'ST666666');
        $coast = $this->package('DEMO-COAST', true, true);
        $verification = $this->package('VERIFY-1', false, false);
        $this->purchase($pending, $coast, 'pending', '2026-10-01 06:00:00');
        $this->purchase($rejected, $coast, 'rejected', '2026-10-01 06:00:00');
        $this->purchase($verify, $verification, 'approved', '2026-10-01 06:00:00');

        $preview = app(PackageIncomeAccrual::class)->preview('2026-10-11');

        $this->assertSame([], $preview['roi']);
        $this->assertSame([], $preview['commissions']);
        try {
            app(PackageIncomeAccrual::class)->accrue('2026-10-11');
            $this->fail('Disabled package income credited a wallet.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('disabled', $exception->getMessage());
        }
        $this->assertSame('0.0000', $active->fresh()->roi_wallet_amount);
    }

    public function test_coastal_roi_and_level_one_credit_the_correct_wallets_once(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $sponsor = $this->member('STSPON001', 'ST666666');
        $buyer = $this->member('STBUY0001', $sponsor->member_id);
        $coast = $this->package('DEMO-COAST', true, true);
        $this->purchase($sponsor, $coast, 'approved', '2026-09-01 06:00:00');
        $this->purchase($buyer, $coast, 'approved', '2026-10-01 06:00:00');

        $first = app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $again = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(2, $first['credited_roi']);
        $this->assertSame(1, $first['credited_commissions']);
        $this->assertSame(0, $again['credited_roi']);
        $this->assertSame(0, $again['credited_commissions']);
        $this->assertSame('0.2500', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.2500', $sponsor->fresh()->roi_wallet_amount);
        $this->assertSame('0.0600', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame(1, PackageLevelAccrual::query()->count());
        $this->assertSame(1, (int) PackageLevelAccrual::query()->value('level'));
    }

    public function test_day_before_the_tenth_calendar_day_and_an_unqualified_sponsor_pay_nothing(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $sponsor = $this->member('STSPON002', 'ST666666');
        $buyer = $this->member('STBUY0002', $sponsor->member_id);
        $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-10-01 06:00:00');

        $early = app(PackageIncomeAccrual::class)->accrue('2026-10-10');
        $due = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(0, $early['credited_roi']);
        $this->assertSame(1, $due['credited_roi']);
        $this->assertSame(0, $due['credited_commissions']);
        $this->assertSame('0.0000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.2500', $buyer->fresh()->roi_wallet_amount);
    }

    public function test_hill_station_roi_and_level_one_use_the_vip_pool(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $sponsor = $this->member('STSPON003', 'ST666666');
        $buyer = $this->member('STBUY0003', $sponsor->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($sponsor, $hills, 'approved', '2026-09-01 06:00:00');
        $this->purchase($buyer, $hills, 'approved', '2026-10-01 06:00:00');

        $result = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(2, $result['credited_roi']);
        $this->assertSame(1, $result['credited_commissions']);
        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $sponsor->fresh()->roi_wallet_amount);
        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
        $this->assertSame('2.4000', PackageLevelAccrual::query()->value('pool_amount'));
        $this->assertSame('25.0000', PackageLevelAccrual::query()->value('rate_percentage'));
    }

    public function test_locked_level_missing_sponsor_and_admin_root_do_not_redirect_payment(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $admin = $this->member('ST666666', 'ST666666');
        $grand = $this->member('STGRAN001', $admin->member_id);
        $sponsor = $this->member('STSPON004', $grand->member_id);
        $buyer = $this->member('STBUY0004', $sponsor->member_id);
        $coast = $this->package('DEMO-COAST', true, true);
        foreach ([$grand, $sponsor, $buyer] as $member) {
            $this->purchase($member, $coast, 'approved', '2026-10-01 06:00:00');
        }

        $result = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame('0.0600', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0900', $grand->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $admin->fresh()->working_wallet_amount);
        $this->assertSame(0, PackageLevelAccrual::query()->where('member_id', $admin->member_id)->count());
        $this->assertTrue(collect($result['skipped'])->contains(fn (string $line): bool => str_contains($line, 'Admin root')));
    }

    public function test_one_date_does_not_backfill_and_a_duplicate_row_does_not_credit_again(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $buyer = $this->member('STBUY0005', 'ST666666');
        $purchase = $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-09-01 06:00:00');
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-'.$purchase->id.'-20261011',
            'idempotency_key' => $purchase->id.'|2026-10-11',
            'package_purchase_id' => $purchase->id,
            'member_id' => $buyer->member_id,
            'member_name' => $buyer->member_name,
            'package_code' => 'DEMO-COAST',
            'package_name' => 'DEMO-COAST',
            'business_date' => '2026-10-11',
            'amount' => '0.2500',
            'status' => 'accrued',
        ]);

        $result = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(0, $result['credited_roi']);
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame([], (new Schedule)->events());
        app(FinancialScheduleRegistrar::class)->register(new Schedule);
    }

    public function test_disabled_command_and_scheduler_do_not_write(): void
    {
        $buyer = $this->member('STBUY0006', 'ST666666');
        $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-10-01 06:00:00');

        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(1);

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_package_history_shows_the_package_and_legacy_rows_stay_unlinked(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $buyer = $this->member('STBUY0007', 'ST666666');
        $purchase = $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-10-01 06:00:00');
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-HIST-1',
            'idempotency_key' => 'hist-1',
            'package_purchase_id' => $purchase->id,
            'member_id' => $buyer->member_id,
            'member_name' => $buyer->member_name,
            'package_code' => 'DEMO-COAST',
            'package_name' => 'Coastal Weekend',
            'business_date' => '2026-10-11',
            'amount' => '0.2500',
            'status' => 'accrued',
        ]);

        $this->actingAs($admin)->get(route('admin.finance.roi-history'))
            ->assertOk()
            ->assertSeeText('Coastal Weekend')
            ->assertSeeText('PROI-HIST-1');
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, bool $roi, bool $level): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $roi || $level ? Package::TYPE_TOUR_ROI : Package::TYPE_TOUR,
            'name' => $code === 'DEMO-COAST' ? 'Coastal Weekend' : ($code === 'DEMO-HILLS' ? 'Hill Station Retreat' : $code),
            'price_usd' => $code === 'DEMO-HILLS' ? '1000.0000' : '100.0000',
            'denomination' => $code === 'VERIFY-1' ? 'USD' : 'USDT',
            'roi_eligible' => $roi,
            'level_commission_eligible' => $level,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $reviewedAt): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '49.0000',
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === 'approved' ? $reviewedAt : null,
        ]);
    }
}
