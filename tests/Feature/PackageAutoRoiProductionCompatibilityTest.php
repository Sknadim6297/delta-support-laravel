<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\FinancialControls;
use App\Services\PackageAutoRoiControl;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanitized production-shaped compatibility audit for package Auto ROI.
 * Does not touch production. Does not enable live posting outside the test harness.
 */
class PackageAutoRoiProductionCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_migration_keeps_gold_premium_on_and_silver_vip_diamond_share_off(): void
    {
        $this->seedSixProductionPackages();

        $result = app(PackageAutoRoiControl::class)->applySafeProductionDefaults();

        $byCode = Package::query()->get()->keyBy('code');
        $this->assertTrue((bool) $byCode['DST-ROI-002']->auto_roi_enabled, 'GOLD must stay Auto ROI ON');
        $this->assertTrue((bool) $byCode['DST-TOUR-003']->auto_roi_enabled, 'PREMIUM must stay Auto ROI ON');
        $this->assertFalse((bool) $byCode['DST-TOUR-001']->auto_roi_enabled, 'SILVER must stay OFF');
        $this->assertFalse((bool) $byCode['DST-TOUR-004']->auto_roi_enabled, 'VIP must stay OFF');
        $this->assertFalse((bool) $byCode['DST-TOUR-005']->auto_roi_enabled, 'DIAMOND must stay OFF');
        $this->assertFalse((bool) $byCode['DST-RY-006']->auto_roi_enabled, 'DELTA SHARE must stay OFF');
        $this->assertContains('DST-ROI-002', $result['enabled_codes']);
        $this->assertContains('DST-TOUR-003', $result['enabled_codes']);
        $this->assertContains('DST-TOUR-001', $result['disabled_codes']);
    }

    public function test_production_shaped_preview_preserves_gold_premium_roi_and_blocks_silver_vip(): void
    {
        config([
            'deltasupport.package_income.enabled' => true,
            'deltasupport.package_income.tour_posting_authorized' => true,
            'deltasupport.controls.financial_automation_armed' => true,
            'deltasupport.controls.daily_financial_cycle_enabled' => true,
            'deltasupport.controls.roi_calculation_enabled' => true,
            'deltasupport.controls.roi_wallet_crediting_enabled' => true,
            'deltasupport.controls.level_calculation_enabled' => true,
            'deltasupport.controls.level_wallet_crediting_enabled' => true,
            'deltasupport.controls.financial_emergency_stop' => false,
        ]);

        $packages = $this->seedSixProductionPackages();
        app(PackageAutoRoiControl::class)->applySafeProductionDefaults();
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => '2026-10-01',
            'activated_at' => now(),
            'confirm_token' => 'test-premium-activation',
            'terms_snapshot' => ['roi_daily' => '2.5000', 'roi_days' => 1000],
        ]);

        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame(800, app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_days']);
        $this->assertSame('2.5000', app(PackageIncomePolicy::class)->definition('DST-TOUR-003')['roi_daily']);
        $this->assertSame(1000, app(PackageIncomePolicy::class)->definition('DST-TOUR-003')['roi_days']);
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-001'));
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-004'));

        $reviewedAt = '2026-10-01 06:00:00';
        $businessDate = '2026-10-11'; // wait_days=10 → first accrual day

        // Non-admin sponsor (earns Level Income); buyers under this sponsor for commission planning.
        $sponsor = $this->member('STSPON47', 'ST666666');
        $this->approvedPurchase($sponsor, $packages['DST-ROI-002'], '2026-09-01 06:00:00', '0.2500', 800, '0.2400');

        $goldMembers = [];
        for ($i = 1; $i <= 31; $i++) {
            $goldMembers[] = $this->member(sprintf('STG%05d', $i), $sponsor->member_id);
        }
        $premiumMembers = [];
        for ($i = 1; $i <= 3; $i++) {
            $premiumMembers[] = $this->member(sprintf('STP%05d', $i), $sponsor->member_id);
        }
        $silverMembers = [];
        for ($i = 1; $i <= 8; $i++) {
            $silverMembers[] = $this->member(sprintf('STS%05d', $i), $sponsor->member_id);
        }
        $vipMembers = [];
        for ($i = 1; $i <= 4; $i++) {
            $vipMembers[] = $this->member(sprintf('STV%05d', $i), $sponsor->member_id);
        }

        foreach ($goldMembers as $member) {
            $this->approvedPurchase($member, $packages['DST-ROI-002'], $reviewedAt, '0.2500', 800, '0.2400');
        }
        foreach ($premiumMembers as $member) {
            $this->approvedPurchase($member, $packages['DST-TOUR-003'], $reviewedAt, '2.5000', 1000, '2.4000');
        }
        foreach ($silverMembers as $member) {
            // Approved without effective catalog ROI definition / without income-term ROI snapshot amount usable as catalog.
            $this->approvedPurchaseWithoutIncomeTerm($member, $packages['DST-TOUR-001'], $reviewedAt);
        }
        foreach ($vipMembers as $member) {
            $this->approvedPurchaseWithoutIncomeTerm($member, $packages['DST-TOUR-004'], $reviewedAt);
        }

        $this->assertSame(47, PackagePurchase::query()->where('status', PackagePurchase::APPROVED)->count());

        $beforeWallets = WalletLedgerEntry::query()->count();
        $preview = app(PackageIncomeAccrual::class)->previewReal($businessDate);

        $roiByCode = collect($preview['roi'])->groupBy('package_code');
        $this->assertCount(32, $roiByCode->get('DST-ROI-002', collect()));
        $this->assertCount(3, $roiByCode->get('DST-TOUR-003', collect()));
        $this->assertSame(35, count($preview['roi']));
        $this->assertTrue(
            collect($preview['roi'])->every(fn (array $row): bool => in_array($row['package_code'], ['DST-ROI-002', 'DST-TOUR-003'], true))
        );
        $this->assertSame('0.2500', $roiByCode->get('DST-ROI-002')->first()['amount']);
        $this->assertSame('2.5000', $roiByCode->get('DST-TOUR-003')->first()['amount']);
        $this->assertFalse(
            collect($preview['roi'])->contains(fn (array $row): bool => in_array($row['package_code'], ['DST-TOUR-001', 'DST-TOUR-004', 'DST-TOUR-005', 'DST-RY-006'], true))
        );

        // Level Income still plans for commission-capable purchases (GOLD pool).
        $this->assertNotEmpty($preview['commissions']);
        $this->assertTrue(
            collect($preview['commissions'])->contains(fn (array $row): bool => $row['package_code'] === 'DST-ROI-002')
        );

        // Turning GOLD Auto ROI OFF removes ROI only; commissions remain.
        app(PackageAutoRoiControl::class)->setEnabled(
            $packages['DST-ROI-002']->fresh(),
            false,
            User::factory()->create(['is_admin' => true]),
            false
        );
        $afterOff = app(PackageIncomeAccrual::class)->previewReal($businessDate);
        $this->assertFalse(
            collect($afterOff['roi'])->contains(fn (array $row): bool => $row['package_code'] === 'DST-ROI-002')
        );
        $this->assertTrue(
            collect($afterOff['commissions'])->contains(fn (array $row): bool => $row['package_code'] === 'DST-ROI-002')
        );

        // Legacy rates untouched.
        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame('2.5000', app(PackageIncomePolicy::class)->definition('DST-TOUR-003')['roi_daily']);
        $this->assertSame($beforeWallets, WalletLedgerEntry::query()->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_package_on_cannot_bypass_global_safety_and_no_backfill_from_toggle(): void
    {
        config([
            'deltasupport.package_income.enabled' => true,
            'deltasupport.controls.financial_automation_armed' => false,
            'deltasupport.controls.daily_financial_cycle_enabled' => false,
            'deltasupport.controls.roi_calculation_enabled' => false,
            'deltasupport.controls.financial_emergency_stop' => false,
        ]);

        $packages = $this->seedSixProductionPackages();
        app(PackageAutoRoiControl::class)->applySafeProductionDefaults();
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STSAFE01', 'ST666666');
        $this->approvedPurchase($member, $packages['DST-ROI-002'], '2026-10-01 06:00:00', '0.2500', 800, '0.2400');

        $status = app(PackageAutoRoiControl::class)->effectiveStatus($packages['DST-ROI-002']->fresh());
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_BLOCKED_GLOBAL, $status['code']);
        $this->assertTrue(app(PackageAutoRoiControl::class)->allowsScheduledRoi($packages['DST-ROI-002']->fresh()));

        // Toggle alone never writes accruals / wallets.
        $before = WalletLedgerEntry::query()->count();
        app(PackageAutoRoiControl::class)->setEnabled($packages['DST-ROI-002']->fresh(), false, $admin, false);
        app(PackageAutoRoiControl::class)->setEnabled($packages['DST-ROI-002']->fresh(), true, $admin, true);
        $this->assertSame($before, WalletLedgerEntry::query()->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertFalse(app(FinancialControls::class)->enabled('daily_financial_cycle_enabled'));
    }

    /**
     * @return array<string, Package>
     */
    private function seedSixProductionPackages(): array
    {
        $rows = [
            ['DST-TOUR-001', 'SILVER PACKAGE', '50.0000', Package::TYPE_TOUR_ROI, true, Package::INCOME_SOURCE_DATABASE],
            ['DST-ROI-002', 'GOLD PACKAGE', '100.0000', Package::TYPE_TOUR_ROI, true, Package::INCOME_SOURCE_LEGACY],
            ['DST-TOUR-003', 'PREMIUM PACKAGE', '500.0000', Package::TYPE_PREMIUM, true, Package::INCOME_SOURCE_LEGACY],
            ['DST-TOUR-004', 'VIP PACKAGE', '1000.0000', Package::TYPE_TOUR_ROI, true, Package::INCOME_SOURCE_DATABASE],
            ['DST-TOUR-005', 'DIAMOND PACKAGE', '5000.0000', Package::TYPE_TOUR_ROI, true, Package::INCOME_SOURCE_DATABASE],
            ['DST-RY-006', 'DELTA SHARE', '50.0000', Package::TYPE_TOUR, false, Package::INCOME_SOURCE_LEGACY],
        ];
        $out = [];
        foreach ($rows as [$code, $name, $price, $type, $roi, $source]) {
            $out[$code] = Package::query()->create([
                'code' => $code,
                'name' => $name,
                'price_usd' => $price,
                'denomination' => 'USDT',
                'package_type' => $type,
                'roi_eligible' => $roi,
                'auto_roi_enabled' => false,
                'level_commission_eligible' => $roi,
                'is_active' => true,
                'purchase_available' => true,
                'income_source' => $source,
                'sort_order' => 1,
            ]);
        }

        return $out;
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

    private function approvedPurchase(
        Member $member,
        Package $package,
        string $reviewedAt,
        string $roiDaily,
        int $roiDays,
        ?string $commissionPool,
    ): PackagePurchase {
        // Production GOLD/PREMIUM accrual resolves via legacy catalog (roi + commission_days).
        // Income-term snapshots omit commission_days and would block Level Income planning —
        // so this shaped fixture mirrors live catalog resolution, not ROI-only term rows.
        unset($roiDaily, $roiDays, $commissionPool);

        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(3)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }

    private function approvedPurchaseWithoutIncomeTerm(Member $member, Package $package, string $reviewedAt): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(3)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
