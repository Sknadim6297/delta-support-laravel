<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeHistoryPreview;
use App\Services\PackageIncomePolicy;
use App\Services\WalletBalanceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeVipCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vip_duration_is_400_days_and_generation_stays_disabled(): void
    {
        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertSame(400, config('deltasupport.package_income.vip_commission_days'));
        $this->assertSame(400, config('deltasupport.package_income.packages.DEMO-COAST.commission_days'));
        $this->assertSame(400, config('deltasupport.package_income.packages.DEMO-HILLS.commission_days'));
        $this->assertSame('0.2400', config('deltasupport.package_income.packages.DEMO-COAST.commission_pool'));
        $this->assertSame('2.4000', config('deltasupport.package_income.packages.DEMO-HILLS.commission_pool'));

        $policy = app(PackageIncomePolicy::class);
        $this->assertSame('2.4000', $this->sumLevels($policy, '2.4000'));
        $this->assertSame('0.2400', $this->sumLevels($policy, '0.2400'));
        $this->assertSame('960.0000', bcmul('2.4000', '400', 4));
        $this->assertSame('96.0000', bcmul('0.2400', '400', 4));
        $this->assertSame([
            1 => '0.6000',
            2 => '0.3000',
            3 => '0.1800',
            4 => '0.1200',
            5 => '0.0600',
            17 => '0.0600',
            18 => '0.1200',
            19 => '0.1200',
            20 => '0.1800',
        ], [
            1 => $policy->levelAmount('2.4000', 1),
            2 => $policy->levelAmount('2.4000', 2),
            3 => $policy->levelAmount('2.4000', 3),
            4 => $policy->levelAmount('2.4000', 4),
            5 => $policy->levelAmount('2.4000', 5),
            17 => $policy->levelAmount('2.4000', 17),
            18 => $policy->levelAmount('2.4000', 18),
            19 => $policy->levelAmount('2.4000', 19),
            20 => $policy->levelAmount('2.4000', 20),
        ]);

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_commission_window_starts_on_day_ten_and_ends_on_day_400(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $first = CarbonImmutable::parse('2026-10-11');
        $last = $first->addDays(399);
        $this->assertSame('2027-11-14', $last->toDateString());

        $coastSponsor = $this->member('STCSP400', 'ST666666');
        $coastBuyer = $this->member('STCBY400', $coastSponsor->member_id);
        $coast = $this->package('DEMO-COAST', true, true);
        $this->purchase($coastSponsor, $coast, 'approved', '2026-10-01 06:00:00', '100.0000');
        $coastPurchase = $this->purchase($coastBuyer, $coast, 'approved', '2026-10-01 06:00:00', '49.0000');

        $hillSponsor = $this->member('STHSP400', 'ST666666');
        $hillBuyer = $this->member('STHBY400', $hillSponsor->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($hillSponsor, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        $hillPurchase = $this->purchase($hillBuyer, $hills, 'approved', '2026-10-01 06:00:00', '99.0000');

        $early = app(PackageIncomeAccrual::class)->accrue('2026-10-10');
        $opening = app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $closing = app(PackageIncomeAccrual::class)->accrue('2027-11-14');
        $after = app(PackageIncomeAccrual::class)->accrue('2027-11-15');
        $repeat = app(PackageIncomeAccrual::class)->accrue('2027-11-14');

        $this->assertSame(0, $early['credited_roi']);
        $this->assertSame(0, $early['credited_commissions']);
        $this->assertSame(4, $opening['credited_roi']);
        $this->assertSame(2, $opening['credited_commissions']);
        $this->assertSame(4, $closing['credited_roi']);
        $this->assertSame(2, $closing['credited_commissions']);
        $this->assertSame(4, $after['credited_roi']);
        $this->assertSame(0, $after['credited_commissions']);
        $this->assertSame(0, $repeat['credited_roi']);
        $this->assertSame(0, $repeat['credited_commissions']);
        $this->assertSame('0.0600', PackageLevelAccrual::query()->where('package_purchase_id', $coastPurchase->id)->value('amount'));
        $this->assertSame('0.6000', PackageLevelAccrual::query()->where('package_purchase_id', $hillPurchase->id)->value('amount'));
        $this->assertSame('49.0000', $coastPurchase->fresh()->price_usd);
        $this->assertSame('99.0000', $hillPurchase->fresh()->price_usd);
        $this->assertSame(2, PackageLevelAccrual::query()->where('package_purchase_id', $hillPurchase->id)->count());
        $this->assertSame(0, PackageLevelAccrual::query()->where('business_date', '2027-11-15')->count());
    }

    public function test_vip_pays_all_twenty_levels_and_skips_admin_root_missing_and_locked_sponsors(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $admin = $this->member('ST666666', 'ST666666');
        $uplines = [];
        $sponsorId = $admin->member_id;
        for ($level = 20; $level >= 1; $level--) {
            $uplines[$level] = $this->member('STV'.str_pad((string) $level, 2, '0', STR_PAD_LEFT), $sponsorId);
            $sponsorId = $uplines[$level]->member_id;
        }
        $buyer = $this->member('STVBUY01', $uplines[1]->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $buyerPurchase = $this->purchase($buyer, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        foreach ($uplines as $level => $upline) {
            $this->purchase($upline, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
            $extras = match (true) {
                $level <= 4 => 0,
                $level <= 8 => 1,
                $level <= 12 => 2,
                $level <= 16 => 3,
                default => 4,
            };
            for ($extra = 1; $extra <= $extras; $extra++) {
                $direct = $this->member('STX'.$level.$extra, $upline->member_id);
                $this->purchase($direct, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
            }
        }

        $unqualified = $this->member('STNOPKG1', 'ST666666');
        $unpaidBuyer = $this->member('STNOPAY1', $unqualified->member_id);
        $this->purchase($unpaidBuyer, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($this->member('STPENDV1', 'ST666666'), $hills, 'pending', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($this->member('STREJV01', 'ST666666'), $hills, 'rejected', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($this->member('STVERV01', 'ST666666'), $this->package('VERIFY-1', false, false), 'approved', '2026-10-01 06:00:00', '100.0000');
        $missingBuyer = $this->member('STMISSV1', 'STABSENT');
        $this->purchase($missingBuyer, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');

        $result = app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $rows = PackageLevelAccrual::query()->where('package_purchase_id', $buyerPurchase->id)->orderBy('level')->get();
        $sum = '0.0000';
        $expected = [
            1 => '0.6000', 2 => '0.3000', 3 => '0.1800', 4 => '0.1200',
            18 => '0.1200', 19 => '0.1200', 20 => '0.1800',
        ];
        $this->assertCount(20, $rows);
        foreach ($rows as $row) {
            $amount = $expected[$row->level] ?? '0.0600';
            $this->assertSame($amount, $row->amount);
            $sum = bcadd($sum, $row->amount, 4);
        }
        $this->assertSame('2.4000', $sum);
        $this->assertSame('0.0000', $admin->fresh()->working_wallet_amount);
        $this->assertSame(0, PackageLevelAccrual::query()->where('member_id', $admin->member_id)->count());
        $this->assertSame('0.0000', $unqualified->fresh()->working_wallet_amount);
        $this->assertSame('2.5000', $unpaidBuyer->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $missingBuyer->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageLevelAccrual::query()->where('from_member_id', 'STNOPAY1')->count());
        $this->assertSame(0, PackageLevelAccrual::query()->where('from_member_id', 'STMISSV1')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->whereIn('member_id', ['STPENDV1', 'STREJV01', 'STVERV01'])->count());
        $this->assertTrue(collect($result['skipped'])->contains(fn (string $line): bool => str_contains($line, 'Admin root')));
        $this->assertTrue(collect($result['skipped'])->contains(fn (string $line): bool => str_contains($line, 'STABSENT')));
    }

    public function test_multiple_packages_credit_once_and_a_second_run_credits_nothing(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $sponsor = $this->member('STMULTI1', 'ST666666');
        $buyer = $this->member('STMULTI2', $sponsor->member_id);
        $this->purchase($sponsor, $this->package('DEMO-HILLS', true, true), 'approved', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-10-01 06:00:00', '49.0000');
        $this->purchase($buyer, Package::query()->where('code', 'DEMO-HILLS')->firstOrFail(), 'approved', '2026-10-01 06:00:00', '99.0000');

        $first = app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $second = app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(3, $first['credited_roi']);
        $this->assertSame(2, $first['credited_commissions']);
        $this->assertSame(0, $second['credited_roi']);
        $this->assertSame(0, $second['credited_commissions']);
        $this->assertSame('2.7500', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.6600', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
    }

    public function test_a_held_lock_and_a_failed_wallet_update_do_not_leave_credits(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $buyer = $this->member('STLOCKV1', 'ST666666');
        $this->purchase($buyer, $this->package('DEMO-HILLS', true, true), 'approved', '2026-10-01 06:00:00', '1000.0000');
        $lock = Cache::lock('package-income-2026-10-11', 120);
        $this->assertTrue($lock->get());
        try {
            app(PackageIncomeAccrual::class)->accrue('2026-10-11');
            $this->fail('A second accrual ran while the date lock was held.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already running', $exception->getMessage());
        } finally {
            $lock->release();
        }
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && preg_match('/update\s+[`"]?members[`"]?/i', $query->sql)) {
                $armed = false;
                throw new \RuntimeException('forced wallet failure');
            }
        });
        try {
            app(PackageIncomeAccrual::class)->accrue('2026-10-11');
            $this->fail('A failed wallet update was committed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('forced wallet failure', $exception->getMessage());
        }
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
    }

    public function test_wallet_reconciliation_matches_package_ledger_rows(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $sponsor = $this->member('STREC001', 'ST666666');
        $buyer = $this->member('STREC002', $sponsor->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($sponsor, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($buyer, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');

        app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $report = app(WalletBalanceReconciliation::class)->inspect($sponsor->fresh());

        $this->assertSame('0.0000', $report['roi']['variance']);
        $this->assertSame('2.5000', $report['roi']['credits']);
        $this->assertSame('0.0000', $report['working']['variance']);
        $this->assertSame('0.6000', $report['working']['credits']);
        $this->assertSame(1, PackageRoiAccrual::query()->where('member_id', $sponsor->member_id)->count());
        $this->assertSame(1, PackageLevelAccrual::query()->where('member_id', $sponsor->member_id)->count());
    }

    public function test_historical_preview_uses_original_prices_and_writes_nothing(): void
    {
        $sponsor = $this->member('STHIST01', 'ST666666');
        $buyer = $this->member('STHIST02', $sponsor->member_id);
        $coast = $this->purchase($buyer, $this->package('DEMO-COAST', true, true), 'approved', '2026-10-01 06:00:00', '49.0000');
        $hills = $this->purchase($buyer, $this->package('DEMO-HILLS', true, true), 'approved', '2026-10-01 06:00:00', '99.0000');
        $this->purchase($buyer, $this->package('VERIFY-1', false, false), 'approved', '2026-10-01 06:00:00', '100.0000');
        $this->purchase($this->member('STHIST03', 'ST666666'), Package::query()->where('code', 'DEMO-HILLS')->firstOrFail(), 'pending', '2026-10-01 06:00:00', '1000.0000');
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-HIST-49',
            'idempotency_key' => $coast->id.'|2026-10-11',
            'package_purchase_id' => $coast->id,
            'member_id' => $buyer->member_id,
            'member_name' => $buyer->member_name,
            'package_code' => 'DEMO-COAST',
            'package_name' => 'Coastal Weekend',
            'business_date' => '2026-10-11',
            'amount' => '0.2500',
            'status' => 'accrued',
        ]);

        $rows = collect(app(PackageIncomeHistoryPreview::class)->rows('2026-10-11'))->keyBy('package_purchase_id');
        $coastRow = app(PackageIncomeHistoryPreview::class)->rows('2026-10-11');
        $coastPreview = collect($coastRow)->firstWhere('purchase_price', '49.0000');
        $hillPreview = collect($coastRow)->firstWhere('purchase_price', '99.0000');
        $verifyPreview = collect($coastRow)->firstWhere('package_code', 'VERIFY-1');
        $pendingPreview = collect($coastRow)->firstWhere('purchase_price', '1000.0000');

        $this->assertSame('STHIST02', $coastPreview['member_id']);
        $this->assertSame('USDT', $coastPreview['currency']);
        $this->assertSame('2026-10-01', $coastPreview['approval_date']);
        $this->assertSame('2026-10-11', $coastPreview['first_income_date']);
        $this->assertSame('2027-11-14', $coastPreview['last_commission_date']);
        $this->assertSame(CarbonImmutable::parse('2026-10-11')->addDays(799)->toDateString(), $coastPreview['last_roi_date']);
        $this->assertSame(1, $coastPreview['roi_elapsed_days']);
        $this->assertSame(1, $coastPreview['roi_credited_days']);
        $this->assertSame(0, $coastPreview['roi_missing_days']);
        $this->assertSame('0.2500', $coastPreview['roi_daily']);
        $this->assertSame('0.2500', $coastPreview['roi_credited_amount']);
        $this->assertSame('0.0000', $coastPreview['roi_missing_amount']);
        $this->assertSame('49.0000', $coast->fresh()->price_usd);

        $this->assertSame('2.5000', $hillPreview['roi_daily']);
        $this->assertSame('2.4000', $hillPreview['commission_pool']);
        $this->assertSame('2027-11-14', $hillPreview['last_commission_date']);
        $this->assertSame(CarbonImmutable::parse('2026-10-11')->addDays(999)->toDateString(), $hillPreview['last_roi_date']);
        $this->assertSame(1, $hillPreview['roi_missing_days']);
        $this->assertSame('2.5000', $hillPreview['roi_missing_amount']);
        $this->assertSame('0.0000', $hillPreview['level_credited_amount']);
        $this->assertSame('2.4000', $hillPreview['commission_potential_pool']);
        $this->assertSame('99.0000', $hills->fresh()->price_usd);
        $this->assertNotEmpty($hillPreview['blockers']);

        $this->assertContains('Not eligible for ROI.', $verifyPreview['blockers']);
        $this->assertContains('Not eligible for level commission.', $verifyPreview['blockers']);
        $this->assertSame('0.0000', $verifyPreview['roi_potential_amount']);
        $this->assertStringContainsString('pending', implode(' ', $pendingPreview['blockers']));
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertArrayNotHasKey('unused', $rows->all());
    }

    public function test_history_reports_and_wallets_show_only_stored_package_income(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sponsor = $this->member('STUI0001', 'ST666666');
        $buyer = $this->member('STUI0002', $sponsor->member_id);
        $purchase = $this->purchase($buyer, $this->package('DEMO-HILLS', true, true), 'approved', '2026-10-01 06:00:00', '99.0000');
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-VIP-UI',
            'idempotency_key' => 'ui-roi',
            'package_purchase_id' => $purchase->id,
            'member_id' => $buyer->member_id,
            'member_name' => $buyer->member_name,
            'package_code' => 'DEMO-HILLS',
            'package_name' => 'Hill Station Retreat',
            'business_date' => '2026-10-11',
            'amount' => '2.5000',
            'status' => 'accrued',
        ]);
        PackageLevelAccrual::query()->create([
            'reference' => 'PLVL-VIP-UI',
            'idempotency_key' => 'ui-level',
            'package_purchase_id' => $purchase->id,
            'member_id' => $sponsor->member_id,
            'member_name' => $sponsor->member_name,
            'from_member_id' => $buyer->member_id,
            'from_member_name' => $buyer->member_name,
            'level' => 1,
            'package_code' => 'DEMO-HILLS',
            'package_name' => 'Hill Station Retreat',
            'business_date' => '2026-10-11',
            'pool_amount' => '2.4000',
            'rate_percentage' => '25.0000',
            'amount' => '0.6000',
            'status' => 'accrued',
        ]);
        $buyer->forceFill(['roi_wallet_amount' => '2.5000'])->save();
        $sponsor->forceFill(['working_wallet_amount' => '0.6000'])->save();

        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))
            ->assertOk()
            ->assertSeeText('PROI-VIP-UI')
            ->assertSeeText('PLVL-VIP-UI')
            ->assertSeeText('Package purchase')
            ->assertSeeText('USDT')
            ->assertSeeText('not calculated');

        $export = $this->actingAs($admin)->get(route('admin.catalog-reports.export', [
            'report' => 'earnings',
            'format' => 'csv',
            'scope' => 'filtered',
            'q' => 'PROI-VIP-UI',
        ]));
        $export->assertOk();
        $this->assertStringContainsString('PROI-VIP-UI', $export->streamedContent());
        $this->assertStringNotContainsString('PLVL-VIP-UI', $export->streamedContent());

        $this->actingAs($admin)->get(route('admin.finance.roi-plans'))
            ->assertOk()
            ->assertSeeText('2.5000 USDT')
            ->assertSeeText('400');
        $this->actingAs($admin)->get(route('admin.finance.commission-settings'))
            ->assertOk()
            ->assertSeeText('0.6000')
            ->assertSeeText('960.0000')
            ->assertSeeText('Inactive')
            ->assertDontSeeText('not approved');
        $this->actingAs($admin)->get(route('admin.finance.income-test'))
            ->assertOk()
            ->assertSeeText('Historical accrual preview')
            ->assertSeeText('99.0000 USDT')
            ->assertSeeText('400-day')
            ->assertDontSeeText('not approved');

        $this->withSession(['member_context_id' => $buyer->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('USDT 2.5000');
        $this->withSession(['member_context_id' => $buyer->member_id])
            ->get(route('member.wallets.show', ['wallet' => 'roi']))
            ->assertOk()
            ->assertSeeText('1 stored ROI record');
        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.wallets.show', ['wallet' => 'working']))
            ->assertOk()
            ->assertSeeText('1 stored level income record');
        $this->withSession(['member_context_id' => $buyer->member_id])
            ->get(route('member.income.roi'))
            ->assertOk()
            ->assertSeeText('Hill Station Retreat')
            ->assertSeeText('PROI-VIP-UI');
        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.income.level'))
            ->assertOk()
            ->assertSeeText('PLVL-VIP-UI');

        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame(0, \App\Models\RoiTransaction::query()->count());
    }

    public function test_a_circular_sponsor_chain_stops_without_paying_a_substitute(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $first = $this->member('STCIRCA1', 'STCIRCB1');
        $second = $this->member('STCIRCB1', $first->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($first, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($second, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');

        app(PackageIncomeAccrual::class)->accrue('2026-10-11');

        $this->assertSame(1, PackageLevelAccrual::query()->where('from_member_id', $second->member_id)->count());
        $this->assertSame($first->member_id, PackageLevelAccrual::query()->where('from_member_id', $second->member_id)->value('member_id'));
        $this->assertSame('0.6000', $first->fresh()->working_wallet_amount);
    }

    private function sumLevels(PackageIncomePolicy $policy, string $pool): string
    {
        $sum = '0.0000';
        foreach (\App\Services\CommissionPlan::RATE_BY_LEVEL as $level => $rate) {
            $sum = bcadd($sum, (string) $policy->levelAmount($pool, (int) $level), 4);
        }

        return $sum;
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
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
            'denomination' => 'USDT',
            'roi_eligible' => $roi,
            'level_commission_eligible' => $level,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $reviewedAt, string $price): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === 'approved' ? $reviewedAt : null,
        ]);
    }
}
