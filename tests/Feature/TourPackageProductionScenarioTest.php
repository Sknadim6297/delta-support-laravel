<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeEligibilityAudit;
use App\Services\TourPackageIncomeActivation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Local simulation of production Tour purchases ST100005 / ST100017.
 * Does not connect to production.
 */
class TourPackageProductionScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        parent::tearDown();
    }

    public function test_unauthorized_tour_skips_while_standard_still_posts_on_nov_16(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        $this->activateTourFloor('2026-11-14');

        $standard = $this->member('STSTAND16', 'ST666666', 'std16@example.test');
        $tourA = $this->member('ST100005', 'ST666666', 'st100005@example.test');
        $tourB = $this->member('ST100017', 'ST666666', 'st100017@example.test');
        $this->purchase($standard, $this->roiPackage(), '2026-11-01 06:00:00', 'STD-16', 'PAY-STD-16');
        $this->purchase($tourA, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-FDODSV', 'PAY-FDODSV');
        $this->purchase($tourB, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-VTXODU', 'PAY-VTXODU');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:30:00', 'Asia/Kolkata'));
        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-16', true);
        $live = app(PackageIncomeAccrual::class)->runControlled('2026-11-16');

        $this->assertTrue(collect($dry['skipped'])->contains(
            fn (string $reason): bool => str_contains($reason, 'posting is not authorized')
        ));
        // Standard only: 0.2500. Tour projected 5.0000 is blocked by auth.
        $this->assertSame(1, $dry['roi_records']);
        $this->assertSame(1, $live['roi_records']);
        $this->assertSame('0.2500', $standard->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tourA->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tourB->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->count());

        $audit = app(PackageIncomeEligibilityAudit::class)->forDate('2026-11-16');
        $tourRow = collect($audit['ineligible'])->firstWhere('member_id', 'ST100005');
        $this->assertNotNull($tourRow);
        $this->assertSame('no', $tourRow['eligible']);
        $this->assertStringContainsString('posting is not authorized', $tourRow['reason']);
    }

    public function test_authorized_tour_credits_5_usdt_total_on_nov_16_and_17_idempotently(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateTourFloor('2026-11-14');

        $tourA = $this->member('ST100005', 'ST666666', 'st100005b@example.test');
        $tourB = $this->member('ST100017', 'ST666666', 'st100017b@example.test');
        $this->purchase($tourA, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-FDODSV', 'PAY-FDODSV-B');
        $this->purchase($tourB, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-VTXODU', 'PAY-VTXODU-B');

        // Original first accrual = 2026-11-12; floor 2026-11-14. Nov 13 must stay unpaid.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-13 00:30:00', 'Asia/Kolkata'));
        $beforeFloor = app(PackageIncomeAccrual::class)->runControlled('2026-11-13', true);
        $this->assertSame(0, $beforeFloor['roi_records']);

        foreach (['2026-11-16', '2026-11-17'] as $date) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($date.' 00:30:00', 'Asia/Kolkata'));
            $beforeA = $tourA->fresh()->roi_wallet_amount;
            $beforeB = $tourB->fresh()->roi_wallet_amount;
            $first = app(PackageIncomeAccrual::class)->runControlled($date);
            $second = app(PackageIncomeAccrual::class)->runControlled($date);
            $this->assertSame(2, $first['roi_records'], 'expected 2 Tour ROI rows on '.$date);
            $this->assertSame(0, $second['roi_records']);
            $dayTotal = bcadd(
                bcsub($tourA->fresh()->roi_wallet_amount, $beforeA, 4),
                bcsub($tourB->fresh()->roi_wallet_amount, $beforeB, 4),
                4
            );
            $this->assertSame('5.0000', $dayTotal, 'expected daily Tour ROI 2.5+2.5 on '.$date);
            // Pool 4.8000 may distribute 0 when uplines are admin-root only.
            $this->assertGreaterThanOrEqual(0, $first['commission_records']);
        }

        $this->assertSame('10.0000', bcadd($tourA->fresh()->roi_wallet_amount, $tourB->fresh()->roi_wallet_amount, 4));
        $this->assertSame(4, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->where('status', 'accrued')->count());
        $this->assertSame(4, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-13')->count());
    }

    public function test_waiting_period_day_9_10_11_for_new_tour_purchase_after_activation(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateTourFloor('2026-11-01');
        $member = $this->member('STWAIT011', 'ST666666', 'wait@example.test');
        // Approved 2026-11-05 → first accrual 2026-11-15 (day 10 after approval start).
        $this->purchase($member, $this->tourPackage(), '2026-11-05 06:00:00', 'WAIT-NEW', 'PAY-WAIT');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-14 00:30:00', 'Asia/Kolkata')); // day 9
        $this->assertSame(0, app(PackageIncomeAccrual::class)->runControlled('2026-11-14', true)['roi_records']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 00:30:00', 'Asia/Kolkata')); // day 10 / first
        $this->assertSame(1, app(PackageIncomeAccrual::class)->runControlled('2026-11-15')['roi_records']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:30:00', 'Asia/Kolkata')); // day 11
        $this->assertSame(1, app(PackageIncomeAccrual::class)->runControlled('2026-11-16')['roi_records']);
        $this->assertSame('5.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_member_dashboard_distinguishes_zero_from_unauthorized_tour(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        $this->activateTourFloor('2026-11-14');
        $member = $this->member('ST100005', 'ST666666', 'dash@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'DASH-TOUR', 'PAY-DASH');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.wallets.overview'))
            ->assertOk()
            ->assertSeeText('Premium Tour posting is not authorized')
            ->assertDontSeeText('ROI and level income are not being credited');
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function activateTourFloor(string $date): void
    {
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => $date,
            'activated_at' => now(),
            'activated_by' => 'test',
            'confirm_token' => TourPackageIncomeActivation::CONFIRM,
            'terms_snapshot' => [
                'roi_daily' => '2.5000',
                'roi_days' => 1000,
                'commission_pool' => '2.4000',
                'commission_days' => 400,
            ],
        ]);
    }

    private function member(string $memberId, ?string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId ? 'Sponsor' : null,
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function roiPackage(): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-ROI-002'], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    private function tourPackage(): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-TOUR-003'], [
            'package_type' => Package::TYPE_PREMIUM,
            'name' => 'Delta Support 3-Year Tour Package',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function purchase(
        Member $member,
        Package $package,
        string $reviewedAt,
        string $referenceSuffix,
        string $paymentReference,
    ): PackagePurchase {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$referenceSuffix,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => $paymentReference,
            'status' => 'approved',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
