<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageAutoRoiControl;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeDefinitionResolver;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use App\Services\PackagePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackageAutoRoiAuthorizationAndHistoricalProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_silver_published_unauthorized_plan_shows_incomplete_and_definition_null(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);

        $version = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'published without authority',
            'accrual_authority' => true, // request ignored while income_source is legacy_config
        ], $admin, true);

        $this->assertFalse((bool) $version->accrual_authority);
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-001'));

        $package->forceFill(['auto_roi_enabled' => true])->save();
        $status = app(PackageAutoRoiControl::class)->effectiveStatus($package->fresh());
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_INCOMPLETE, $status['code']);
        $this->assertSame('ON — Configuration Incomplete', $status['label']);
        $this->assertTrue($status['toggle']);
        $this->assertStringNotContainsString('OFF', $status['label']);
    }

    public function test_admin_list_and_edit_match_on_incomplete_when_toggle_on_without_authority(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);
        app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'published without authority',
            'accrual_authority' => true,
        ], $admin, true);
        $package->forceFill(['auto_roi_enabled' => true])->save();

        // Stale in-memory attribute must not flip status to OFF.
        $stale = $package->fresh();
        $stale->auto_roi_enabled = false;
        $status = app(PackageAutoRoiControl::class)->effectiveStatus($stale);
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_INCOMPLETE, $status['code']);
        $this->assertSame('ON — Configuration Incomplete', $status['label']);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('ON — Configuration Incomplete')
            ->assertDontSeeText('Auto ROI Generation: OFF');

        $edit = $this->actingAs($admin)->get(route('admin.packages.edit', $package->fresh()));
        $edit->assertOk()
            ->assertSeeText('Auto ROI Generation: ON — Configuration Incomplete')
            ->assertSeeText('Authorize a published Daily ROI plan')
            ->assertDontSeeText('Auto ROI Generation: OFF')
            ->assertSeeText('Turn Auto ROI OFF');

        $this->actingAs($admin)
            ->get(route('admin.packages.show', $package->fresh()))
            ->assertOk()
            ->assertSeeText('Auto ROI Generation: ON — Configuration Incomplete');

        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_silver_authorize_then_auto_roi_on_scheduled_when_globals_permit(): void
    {
        config([
            'deltasupport.controls.financial_automation_armed' => true,
            'deltasupport.controls.daily_financial_cycle_enabled' => true,
            'deltasupport.controls.roi_calculation_enabled' => true,
            'deltasupport.controls.financial_emergency_stop' => false,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);
        app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'silver plan',
            'accrual_authority' => true,
        ], $admin, true);

        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-001'));

        $authorized = app(PackagePlanAdministration::class)->authorizeAccrualPlan(
            $package->fresh(),
            $admin,
            true
        );
        $this->assertTrue((bool) $authorized->accrual_authority);
        $this->assertSame(Package::INCOME_SOURCE_DATABASE, $package->fresh()->income_source);

        $def = app(PackageIncomePolicy::class)->definition('DST-TOUR-001');
        $this->assertSame('0.1100', $def['roi_daily']);
        $this->assertSame(900, $def['roi_days']);
        $this->assertSame(PackageIncomeDefinitionResolver::SOURCE_DATABASE, $def['source']);

        app(PackageAutoRoiControl::class)->setEnabled($package->fresh(), true, $admin, true);
        $status = app(PackageAutoRoiControl::class)->effectiveStatus($package->fresh());
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_SCHEDULED, $status['code']);
        $this->assertSame('ON — Scheduled', $status['label']);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_eight_historical_silver_purchases_remain_without_roi_after_authorization(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);
        app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'silver',
            'accrual_authority' => true,
        ], $admin, true);

        $ids = [];
        for ($i = 1; $i <= 8; $i++) {
            $member = $this->member(sprintf('STH%05d', $i), 'ST666666');
            $purchase = $this->approvedPurchase($member, $package, '2026-09-15 06:00:00', null);
            // Match production: seven empty income terms + one without a term.
            if ($i !== 1) {
                PackageIncomeTerm::query()->create([
                    'package_purchase_id' => $purchase->id,
                    'reference' => 'PIT-'.$purchase->reference,
                    'income_eligible' => false,
                    'monthly_amount' => null,
                    'duration_months' => null,
                    'commission_base' => null,
                    'snapshotted_at' => now(),
                ]);
            }
            $ids[] = $purchase->id;
        }

        app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, true);
        app(PackageAutoRoiControl::class)->setEnabled($package->fresh(), true, $admin, true);

        $policy = app(PackageIncomePolicy::class);
        foreach ($ids as $id) {
            $purchase = PackagePurchase::query()->findOrFail($id);
            $this->assertNull($purchase->package_plan_version_id);
            $this->assertNull($policy->definitionForPurchase($purchase));
        }

        $ambiguous = app(PackageIncomeDefinitionResolver::class)->ambiguousApprovedPurchases($package->fresh());
        $this->assertCount(8, $ambiguous);

        config(['deltasupport.package_income.enabled' => true]);
        $preview = app(PackageIncomeAccrual::class)->previewReal('2026-10-11');
        $this->assertFalse(
            collect($preview['roi'])->contains(fn (array $row): bool => $row['package_code'] === 'DST-TOUR-001')
        );
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_new_purchase_receives_authorized_plan_version_and_immutable_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);
        $version = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'silver',
            'accrual_authority' => true,
        ], $admin, true);
        app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, true);
        $authorizedId = $package->fresh()->active_plan_version_id;
        $this->assertNotNull($authorizedId);

        $member = $this->member('STNEW001', 'ST666666');
        $pending = PackagePurchase::query()->create([
            'reference' => 'PUR-NEW-SILVER-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-NEW-SILVER-1',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => now(),
            'funding_source' => PackagePurchase::FUNDING_EXTERNAL,
        ]);

        app(PackagePurchaseService::class)->approve($pending, $admin);
        $approved = $pending->fresh();
        $this->assertSame(PackagePurchase::APPROVED, $approved->status);
        $this->assertSame($authorizedId, $approved->package_plan_version_id);

        $term = PackageIncomeTerm::query()->where('package_purchase_id', $approved->id)->firstOrFail();
        $this->assertSame('0.1100', bcadd((string) $term->monthly_amount, '0', 4));
        $this->assertSame(900, (int) $term->duration_months);

        // Later catalog change does not rewrite the snapshot.
        app(PackagePlanAdministration::class)->savePlanVersion($package->fresh(), [
            'has_daily_roi' => true,
            'roi_daily' => '0.1200',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'later rate',
            'accrual_authority' => true,
        ], $admin, true);
        app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, true);

        $def = app(PackageIncomePolicy::class)->definitionForPurchase($approved->fresh());
        $this->assertSame('0.1100', $def['roi_daily']);
        $this->assertSame(900, $def['roi_days']);
        $this->assertSame('0.1100', bcadd((string) $term->fresh()->monthly_amount, '0', 4));
        $this->assertSame($authorizedId, $approved->fresh()->package_plan_version_id);
        $this->assertSame($version->id, $authorizedId);
    }

    public function test_gold_and_premium_legacy_roi_unchanged(): void
    {
        $gold = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'GOLD PACKAGE',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'auto_roi_enabled' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_LEGACY,
            'sort_order' => 1,
        ]);
        $premium = Package::query()->create([
            'code' => 'DST-TOUR-003',
            'name' => 'PREMIUM PACKAGE',
            'price_usd' => '500.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_PREMIUM,
            'roi_eligible' => true,
            'auto_roi_enabled' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_LEGACY,
            'sort_order' => 2,
        ]);

        $policy = app(PackageIncomePolicy::class);
        $this->assertSame('0.2500', $policy->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame(800, $policy->definition('DST-ROI-002')['roi_days']);
        $this->assertSame('2.5000', $policy->definition('DST-TOUR-003')['roi_daily']);
        $this->assertSame(1000, $policy->definition('DST-TOUR-003')['roi_days']);

        $buyer = $this->member('STGOLD01', 'ST666666');
        $purchase = $this->approvedPurchase($buyer, $gold, '2026-10-01 06:00:00', null);
        $this->assertSame('0.2500', $policy->definitionForPurchase($purchase)['roi_daily']);
        $this->assertSame(800, $policy->definitionForPurchase($purchase)['roi_days']);

        // Authorizing an unrelated SILVER plan must not touch GOLD/PREMIUM.
        $admin = User::factory()->create(['is_admin' => true]);
        $silver = $this->silverLikePackage('legacy_config', false);
        app(PackagePlanAdministration::class)->savePlanVersion($silver, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'level_commission_eligible' => true,
            'change_note' => 'silver',
            'accrual_authority' => true,
        ], $admin, true);
        app(PackagePlanAdministration::class)->authorizeAccrualPlan($silver->fresh(), $admin, true);

        $this->assertSame('0.2500', $policy->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame('2.5000', $policy->definition('DST-TOUR-003')['roi_daily']);
        $this->assertTrue((bool) $gold->fresh()->auto_roi_enabled);
        $this->assertTrue((bool) $premium->fresh()->auto_roi_enabled);
    }

    public function test_level_income_unaffected_by_auto_roi_off_and_emergency_stop_blocks_roi(): void
    {
        config([
            'deltasupport.package_income.enabled' => true,
            'deltasupport.controls.financial_automation_armed' => true,
            'deltasupport.controls.daily_financial_cycle_enabled' => true,
            'deltasupport.controls.roi_calculation_enabled' => true,
            'deltasupport.controls.level_calculation_enabled' => true,
            'deltasupport.controls.financial_emergency_stop' => false,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $gold = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'GOLD PACKAGE',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'auto_roi_enabled' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_LEGACY,
            'sort_order' => 1,
        ]);

        $sponsor = $this->member('STSPONLI', 'ST666666');
        $buyer = $this->member('STBUYLI1', $sponsor->member_id);
        $this->approvedPurchase($sponsor, $gold, '2026-09-01 06:00:00', null);
        $this->approvedPurchase($buyer, $gold, '2026-10-01 06:00:00', null);

        $before = app(PackageIncomeAccrual::class)->previewReal('2026-10-11');
        $this->assertNotEmpty($before['roi']);
        $this->assertNotEmpty($before['commissions']);

        app(PackageAutoRoiControl::class)->setEnabled($gold->fresh(), false, $admin, false);
        $afterOff = app(PackageIncomeAccrual::class)->previewReal('2026-10-11');
        $this->assertEmpty($afterOff['roi']);
        $this->assertNotEmpty($afterOff['commissions']);

        app(PackageAutoRoiControl::class)->setEnabled($gold->fresh(), true, $admin, true);
        config(['deltasupport.controls.financial_emergency_stop' => true]);
        $status = app(PackageAutoRoiControl::class)->effectiveStatus($gold->fresh());
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_BLOCKED_GLOBAL, $status['code']);
        $this->assertSame('ON — Globally Blocked', $status['label']);
    }

    public function test_vip_and_diamond_follow_same_authorize_workflow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config([
            'deltasupport.controls.daily_financial_cycle_enabled' => true,
            'deltasupport.controls.roi_calculation_enabled' => true,
            'deltasupport.controls.financial_emergency_stop' => false,
        ]);

        foreach ([
            ['DST-TOUR-004', 'VIP PACKAGE', '1000.0000', '3.2019'],
            ['DST-TOUR-005', 'DIAMOND PACKAGE', '5000.0000', '16.8654'],
        ] as [$code, $name, $price, $daily]) {
            $package = Package::query()->create([
                'code' => $code,
                'name' => $name,
                'price_usd' => $price,
                'denomination' => 'USDT',
                'package_type' => Package::TYPE_TOUR_ROI,
                'roi_eligible' => true,
                'auto_roi_enabled' => false,
                'level_commission_eligible' => true,
                'is_active' => true,
                'income_source' => Package::INCOME_SOURCE_LEGACY,
                'sort_order' => 1,
            ]);
            app(PackagePlanAdministration::class)->savePlanVersion($package, [
                'has_daily_roi' => true,
                'roi_daily' => $daily,
                'roi_days' => 900,
                'wait_days' => 10,
                'level_commission_eligible' => true,
                'change_note' => $code.' plan',
                'accrual_authority' => true,
            ], $admin, true);

            $this->assertNull(app(PackageIncomePolicy::class)->definition($code));
            app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, true);
            $this->assertSame($daily, app(PackageIncomePolicy::class)->definition($code)['roi_daily']);

            app(PackageAutoRoiControl::class)->setEnabled($package->fresh(), true, $admin, true);
            $this->assertSame(
                PackageAutoRoiControl::STATUS_ON_SCHEDULED,
                app(PackageAutoRoiControl::class)->effectiveStatus($package->fresh())['code']
            );
        }
    }

    public function test_authorize_requires_confirmation_and_does_not_duplicate_on_retry_approve(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = $this->silverLikePackage('legacy_config', false);
        app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1100',
            'roi_days' => 900,
            'wait_days' => 10,
            'level_commission_eligible' => true,
            'change_note' => 'silver',
            'accrual_authority' => true,
        ], $admin, true);

        try {
            app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, false);
            $this->fail('Authorization without confirm should fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm_authorize_accrual', $e->errors());
        }

        app(PackagePlanAdministration::class)->authorizeAccrualPlan($package->fresh(), $admin, true);
        $member = $this->member('STRETRY1', 'ST666666');
        $pending = PackagePurchase::query()->create([
            'reference' => 'PUR-RETRY-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-RETRY-1',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => now(),
            'funding_source' => PackagePurchase::FUNDING_EXTERNAL,
        ]);

        $first = app(PackagePurchaseService::class)->approve($pending, $admin);
        $second = app(PackagePurchaseService::class)->approve($pending->fresh(), $admin);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PackageIncomeTerm::query()->where('package_purchase_id', $pending->id)->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    private function silverLikePackage(string $incomeSource, bool $autoRoi): Package
    {
        return Package::query()->create([
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER PACKAGE',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'auto_roi_enabled' => $autoRoi,
            'level_commission_eligible' => true,
            'is_active' => true,
            'purchase_available' => true,
            'income_source' => $incomeSource,
            'sort_order' => 1,
        ]);
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

    private function approvedPurchase(Member $member, Package $package, string $reviewedAt, ?int $planVersionId): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(3)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_plan_version_id' => $planVersionId,
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
