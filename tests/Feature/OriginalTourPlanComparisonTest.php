<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\OriginalTourPlanComparison;
use App\Services\PackageIncomePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OriginalTourPlanComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_plan_covers_five_roi_packages_and_delta_share_without_mutation(): void
    {
        $preview = app(OriginalTourPlanComparison::class)->preview();
        $byTier = collect($preview['packages'])->keyBy('tier');

        $this->assertSame('original_tour_plan_v1', $preview['version_code']);
        $this->assertSame('proposed', $preview['status']);
        $this->assertFalse($preview['active']);
        $this->assertFalse($preview['applies_to_new_purchases']);
        $this->assertFalse($preview['mutates']);
        $this->assertFalse($preview['candidate_merged_into_package_income']);
        $this->assertSame('104', $preview['conversion']['inr_per_usdt']);
        $this->assertSame('four_decimal_round_half_up_candidate', $preview['conversion']['rounding_policy']);
        $this->assertSame(OriginalTourPlanComparison::PRODUCTION_PACKAGE_CODES, $preview['production_package_codes']);

        $this->assertTrue($byTier['SILVER']['has_daily_roi']);
        $this->assertSame('DST-TOUR-001', $byTier['SILVER']['proposed_package_code']);
        $this->assertSame(900, $byTier['SILVER']['proposed_roi_days']);
        $this->assertSame('0.1058', $byTier['SILVER']['proposed_usdt_daily']);
        $this->assertSame('0.105769230769', $byTier['SILVER']['proposed_conversion']['exact_usdt_daily_12dp']);
        $this->assertSame('0.1057', $byTier['SILVER']['proposed_conversion']['proposed_usdt_daily_4dp_floor']);
        $this->assertSame('0.1058', $byTier['SILVER']['proposed_conversion']['proposed_usdt_daily_4dp_round']);
        $this->assertSame('0.1058', $byTier['SILVER']['proposed_conversion']['proposed_usdt_daily_candidate']);

        $this->assertSame('DST-ROI-002', $byTier['GOLD']['proposed_package_code']);
        $this->assertSame(800, $byTier['GOLD']['proposed_roi_days']);
        $this->assertSame('0.2404', $byTier['GOLD']['proposed_usdt_daily']);
        $this->assertSame('0.2403', $byTier['GOLD']['proposed_conversion']['proposed_usdt_daily_4dp_floor']);
        $this->assertSame('0.2404', $byTier['GOLD']['proposed_conversion']['proposed_usdt_daily_4dp_round']);

        $this->assertSame('DST-TOUR-003', $byTier['PREMIUM']['proposed_package_code']);
        $this->assertSame(700, $byTier['PREMIUM']['proposed_roi_days']);
        $this->assertSame('1.3654', $byTier['PREMIUM']['proposed_usdt_daily']);
        $this->assertSame('1.3653', $byTier['PREMIUM']['proposed_conversion']['proposed_usdt_daily_4dp_floor']);
        $this->assertSame('1.3654', $byTier['PREMIUM']['proposed_conversion']['proposed_usdt_daily_4dp_round']);

        $this->assertSame('DST-TOUR-004', $byTier['VIP']['proposed_package_code']);
        $this->assertSame(600, $byTier['VIP']['proposed_roi_days']);
        $this->assertSame('3.2019', $byTier['VIP']['proposed_usdt_daily']);
        $this->assertSame('3.2019', $byTier['VIP']['proposed_conversion']['proposed_usdt_daily_4dp_floor']);
        $this->assertSame('3.2019', $byTier['VIP']['proposed_conversion']['proposed_usdt_daily_4dp_round']);

        $this->assertSame('DST-TOUR-005', $byTier['DIAMOND']['proposed_package_code']);
        $this->assertSame(570, $byTier['DIAMOND']['proposed_roi_days']);
        $this->assertSame('16.8654', $byTier['DIAMOND']['proposed_usdt_daily']);
        $this->assertSame('16.8653', $byTier['DIAMOND']['proposed_conversion']['proposed_usdt_daily_4dp_floor']);
        $this->assertSame('16.8654', $byTier['DIAMOND']['proposed_conversion']['proposed_usdt_daily_4dp_round']);

        $this->assertFalse($byTier['DELTA_SHARE']['has_daily_roi']);
        $this->assertFalse($byTier['DELTA_SHARE']['has_monthly_roi']);
        $this->assertSame('DST-RY-006', $byTier['DELTA_SHARE']['proposed_package_code']);
        $this->assertNull($byTier['DELTA_SHARE']['proposed_usdt_daily']);
        $this->assertNull($byTier['DELTA_SHARE']['proposed_conversion']);
        $this->assertSame(100, $byTier['DELTA_SHARE']['tour']['eligible_after_days']);
        $this->assertStringContainsString('food coupon INR 1000', $byTier['DELTA_SHARE']['tour']['summary']);

        $this->assertArrayHasKey('DST-TOUR-001', $preview['candidate_package_income']);
        $this->assertArrayHasKey('DST-TOUR-005', $preview['candidate_package_income']);
        $this->assertArrayNotHasKey('DST-RY-006', $preview['candidate_package_income']);
        $this->assertSame(900, $preview['candidate_package_income']['DST-TOUR-001']['roi_days']);
        $this->assertSame('0.1058', $preview['candidate_package_income']['DST-TOUR-001']['roi_daily']);
        $this->assertSame(570, $preview['candidate_package_income']['DST-TOUR-005']['roi_days']);

        $this->assertFalse($preview['associate_reminder']['posting_enabled']);
        $this->assertFalse($preview['associate_reminder']['snapshot_on_approval']);
        $this->assertSame('0.1200', $preview['associate_reminder']['pools']['SILVER']);
        $this->assertSame('2.4000', $preview['associate_reminder']['pools']['VIP']);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_existing_package_income_engine_is_unchanged_by_proposed_plan(): void
    {
        $policy = app(PackageIncomePolicy::class);
        $gold = $policy->definition('DST-ROI-002');
        $tour = $policy->definition('DST-TOUR-003');

        $this->assertSame('0.2500', $gold['roi_daily']);
        $this->assertSame(800, $gold['roi_days']);
        $this->assertSame('2.5000', $tour['roi_daily']);
        $this->assertSame(1000, $tour['roi_days']);
        $this->assertNull($policy->definition('DST-TOUR-001'));
        $this->assertNull($policy->definition('DST-TOUR-004'));
        $this->assertNull($policy->definition('DST-TOUR-005'));
        $this->assertNull($policy->definition('DST-RY-006'));
        $this->assertFalse((bool) config('deltasupport.original_tour_plan.active'));
        $this->assertFalse((bool) config('deltasupport.package_income.enabled'));
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
        $this->assertFalse((bool) config('deltasupport.associate_commission.snapshot_on_approval'));
    }

    public function test_historical_income_term_snapshot_is_not_rewritten_by_preview(): void
    {
        Member::query()->create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin Root',
            'mobile_no' => '9000000999',
            'email' => 'adminroot@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'Gold',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-ORIG-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => 'ST666666',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-ORIG-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDays(20),
            'reviewed_at' => now()->subDays(20),
        ]);
        $term = PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'reference' => 'PIT-PUR-ORIG-1',
            'income_eligible' => true,
            'monthly_amount' => '0.2500',
            'duration_months' => 800,
            'snapshotted_at' => now()->subDays(20),
        ]);

        app(OriginalTourPlanComparison::class)->preview();

        $this->assertSame('0.2500', (string) $term->fresh()->monthly_amount);
        $this->assertSame(800, $term->fresh()->duration_months);
        $this->assertSame(1, PackageIncomeTerm::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_admin_roi_plans_does_not_show_proposed_original_plan_comparison(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.finance.roi-plans'))
            ->assertOk()
            ->assertSeeText('ROI Plans')
            ->assertDontSeeText('Original Tour Plan Comparison (PROPOSED)')
            ->assertDontSeeText('original_tour_plan_v1')
            ->assertDontSeeText('Outstanding approvals')
            ->assertDontSeeText('Do not merge candidate_package_income into package_income.packages');
    }

    public function test_tour_benefits_match_original_chart_for_all_tiers(): void
    {
        $byTier = collect(app(OriginalTourPlanComparison::class)->preview()['packages'])->keyBy('tier');

        $this->assertSame(30, $byTier['SILVER']['tour']['eligible_after_days']);
        $this->assertSame(1, $byTier['SILVER']['tour']['nights']);
        $this->assertSame(2, $byTier['SILVER']['tour']['days']);

        foreach (['GOLD', 'PREMIUM'] as $tier) {
            $this->assertSame(30, $byTier[$tier]['tour']['eligible_after_days']);
            $this->assertSame(2, $byTier[$tier]['tour']['nights']);
            $this->assertSame(3, $byTier[$tier]['tour']['days']);
            $this->assertNull($byTier[$tier]['tour']['food_coupon_inr']);
        }

        foreach (['VIP', 'DIAMOND'] as $tier) {
            $this->assertSame(30, $byTier[$tier]['tour']['eligible_after_days']);
            $this->assertSame(2, $byTier[$tier]['tour']['nights']);
            $this->assertSame('2000', $byTier[$tier]['tour']['food_coupon_inr']);
        }

        $this->assertSame('1000', $byTier['DELTA_SHARE']['tour']['food_coupon_inr']);
    }

    public function test_decimal_safe_duration_totals_for_candidate_rates(): void
    {
        $byTier = collect(app(OriginalTourPlanComparison::class)->preview()['packages'])->keyBy('tier');

        $this->assertSame('95.2200', $byTier['SILVER']['proposed_conversion']['math_daily_x_days_candidate']);
        $this->assertSame('192.3200', $byTier['GOLD']['proposed_conversion']['math_daily_x_days_candidate']);
        $this->assertSame('955.7800', $byTier['PREMIUM']['proposed_conversion']['math_daily_x_days_candidate']);
        $this->assertSame('1921.1400', $byTier['VIP']['proposed_conversion']['math_daily_x_days_candidate']);
        $this->assertSame('9613.2780', $byTier['DIAMOND']['proposed_conversion']['math_daily_x_days_candidate']);
    }
}
