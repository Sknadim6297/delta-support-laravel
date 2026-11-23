<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePlanAudit;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPackagePlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_roi_package_with_decimal_total_and_no_wallet_mutation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $before = WalletLedgerEntry::query()->count();

        $response = $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-NEW-100',
            'name' => 'New Silver Like',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 5,
            'is_active' => '1',
            'purchase_available' => '1',
            'has_daily_roi' => '1',
            'roi_daily' => '0.1058',
            'roi_days' => '900',
            'wait_days' => '10',
            'tour_eligible_after_days' => '30',
            'tour_nights' => '1',
            'tour_days' => '2',
            'tour_adults' => '2',
            'tour_children' => '1',
        ]);

        $response->assertRedirect();
        $package = Package::query()->where('code', 'DST-NEW-100')->firstOrFail();
        $this->assertTrue((bool) $package->is_active);
        $this->assertSame('database', $package->income_source);
        $version = PackagePlanVersion::query()->where('package_id', $package->id)->firstOrFail();
        $this->assertSame('0.1058', (string) $version->roi_daily);
        $this->assertSame(900, $version->roi_days);
        $this->assertSame('95.2200', $version->totalRoi());
        $this->assertTrue((bool) $version->accrual_authority);
        $this->assertSame('0.1058', app(PackageIncomePolicy::class)->definition('DST-NEW-100')['roi_daily']);
        $this->assertSame($before, WalletLedgerEntry::query()->count());
        $this->assertGreaterThan(0, PackagePlanAudit::query()->where('package_id', $package->id)->count());
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_duplicate_code_is_rejected_and_tour_only_disables_roi(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Package::query()->create([
            'code' => 'DST-DUP-1',
            'name' => 'Existing',
            'price_usd' => '10.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-DUP-1',
            'name' => 'Dup',
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '10.0000',
            'sort_order' => 1,
            'has_daily_roi' => '1',
            'roi_daily' => '1.0000',
            'roi_days' => '10',
        ])->assertSessionHasErrors('code');

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-TOURONLY-1',
            'name' => 'Tour Only Pack',
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '50.0000',
            'sort_order' => 2,
            'is_active' => '1',
            'has_daily_roi' => '1',
            'roi_daily' => '1.0000',
            'roi_days' => '10',
        ])->assertSessionHasErrors('has_daily_roi');
    }

    public function test_legacy_gold_definition_unchanged_and_new_version_does_not_replace_accrual(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'GOLD',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'income_source' => 'legacy_config',
            'sort_order' => 1,
        ]);

        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);

        app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.2404',
            'roi_days' => 800,
            'change_note' => 'candidate',
            'accrual_authority' => true,
        ], $admin, true);

        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame('legacy_config', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['source']);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_approved_purchase_keeps_snapshot_when_plan_changes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Member::query()->create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin Root',
            'mobile_no' => '9000000111',
            'email' => 'planroot@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'DST-PLAN-1',
            'name' => 'Plan Pack',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'is_active' => true,
            'income_source' => 'database',
            'sort_order' => 1,
        ]);
        $v1 = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1058',
            'roi_days' => 900,
            'accrual_authority' => true,
        ], $admin, true);
        $package->refresh();

        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-PLAN-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => 'ST666666',
            'package_id' => $package->id,
            'package_plan_version_id' => $v1->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-PLAN-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDays(20),
            'reviewed_at' => now()->subDays(20),
        ]);
        PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'package_plan_version_id' => $v1->id,
            'income_definition_source' => 'purchase_plan_version',
            'reference' => 'PIT-PUR-PLAN-1',
            'income_eligible' => true,
            'monthly_amount' => '0.1058',
            'duration_months' => 900,
            'snapshotted_at' => now()->subDays(20),
        ]);

        app(PackagePlanAdministration::class)->savePlanVersion($package->fresh(), [
            'has_daily_roi' => true,
            'roi_daily' => '0.2000',
            'roi_days' => 500,
            'accrual_authority' => true,
            'change_note' => 'future only',
        ], $admin, true);

        $def = app(PackageIncomePolicy::class)->definitionForPurchase($purchase->fresh());
        $this->assertSame('0.1058', $def['roi_daily']);
        $this->assertSame(900, $def['roi_days']);
        $this->assertSame('purchase_snapshot', $def['source']);
        $this->assertSame('0.2000', app(PackageIncomePolicy::class)->definition('DST-PLAN-1')['roi_daily']);
        $this->assertSame(2, PackagePlanVersion::query()->where('package_id', $package->id)->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_member_cannot_access_package_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.packages.index'))->assertForbidden();
    }

    public function test_invalid_roi_rejected_and_associate_flags_remain_off(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-BAD-ROI',
            'name' => 'Bad',
            'package_type' => Package::TYPE_ROI,
            'price_usd' => '10.0000',
            'sort_order' => 1,
            'has_daily_roi' => '1',
            'roi_daily' => '0.10585',
            'roi_days' => '900',
        ])->assertSessionHasErrors('roi_daily');

        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
        $this->assertFalse((bool) config('deltasupport.associate_commission.snapshot_on_approval'));
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }
}
