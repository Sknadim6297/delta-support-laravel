<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\AssociateCommissionStructure;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SilverPackagePlanCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_silver_wrong_rate_is_corrected_with_new_version_without_rewriting_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Member::query()->create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin Root',
            'mobile_no' => '9000000001',
            'email' => 'silverfix@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);

        $package = Package::query()->create([
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER PACKAGE',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'purchase_available' => true,
            'income_source' => 'database',
            'sort_order' => 1,
        ]);

        $v1 = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1110',
            'roi_days' => 900,
            'wait_days' => 9,
            'commission_pool' => '0.2400',
            'commission_days' => 400,
            'level_commission_eligible' => true,
            'change_note' => 'incorrect admin entry',
            'accrual_authority' => true,
        ], $admin, true);

        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-SILVER-1',
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
            'payment_reference' => 'TX-SILVER-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
        ]);
        PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'package_plan_version_id' => $v1->id,
            'income_definition_source' => 'purchase_plan_version',
            'reference' => 'PIT-PUR-SILVER-1',
            'income_eligible' => true,
            'monthly_amount' => '0.1110',
            'duration_months' => 900,
            'commission_base' => '0.2400',
            'snapshotted_at' => now()->subDay(),
        ]);

        $v2 = app(PackagePlanAdministration::class)->savePlanVersion($package->fresh(), [
            'has_daily_roi' => true,
            'roi_daily' => '0.1058',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'tour_eligible_after_days' => 30,
            'tour_nights' => 1,
            'tour_days' => 2,
            'tour_adults' => 2,
            'tour_children' => 1,
            'label' => 'original_tour_plan_v1_silver',
            'change_note' => 'Corrected approved Original Tour Plan SILVER 0.1058×900. Prior 0.1110 preserved for existing purchase.',
            'accrual_authority' => true,
        ], $admin, true);

        $this->assertSame(2, $v2->version_number);
        $this->assertSame(PackagePlanVersion::STATUS_PUBLISHED, $v2->status);
        $this->assertSame('0.1058', (string) $v2->roi_daily);
        $this->assertNull($v2->commission_pool);

        $v1->refresh();
        $this->assertSame(PackagePlanVersion::STATUS_SUPERSEDED, $v1->status);
        $this->assertSame('0.1110', (string) $v1->roi_daily);

        $policy = app(PackageIncomePolicy::class);
        $this->assertSame('0.1058', $policy->definition('DST-TOUR-001')['roi_daily']);
        $this->assertNull($policy->definition('DST-TOUR-001')['commission_pool']);
        $this->assertSame('0.1110', $policy->definitionForPurchase($purchase->fresh())['roi_daily']);
        $this->assertSame('purchase_snapshot', $policy->definitionForPurchase($purchase->fresh())['source']);

        $term = PackageIncomeTerm::query()->where('package_purchase_id', $purchase->id)->firstOrFail();
        $this->assertSame('0.1110', (string) $term->monthly_amount);
        $this->assertSame(900, $term->duration_months);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_published_version_without_accrual_authority_hides_catalog_roi_until_corrected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Member::query()->create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin Root',
            'mobile_no' => '9000000002',
            'email' => 'silver.auth@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);

        $package = Package::query()->create([
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER PACKAGE',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'purchase_available' => true,
            'income_source' => 'database',
            'sort_order' => 1,
        ]);

        $good = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1058',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'approved silver',
            'accrual_authority' => true,
        ], $admin, true);

        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-SILVER-AUTH-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => 'ST666666',
            'package_id' => $package->id,
            'package_plan_version_id' => $good->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-SILVER-AUTH-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
        ]);
        PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'package_plan_version_id' => $good->id,
            'income_definition_source' => 'purchase_plan_version',
            'reference' => 'PIT-PUR-SILVER-AUTH-1',
            'income_eligible' => true,
            'monthly_amount' => '0.1058',
            'duration_months' => 900,
            'commission_base' => null,
            'snapshotted_at' => now()->subDay(),
        ]);

        // Simulate a later admin publish that keeps ROI fields but drops accrual_authority.
        $broken = app(PackagePlanAdministration::class)->savePlanVersion($package->fresh(), [
            'has_daily_roi' => true,
            'roi_daily' => '0.1110',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'change_note' => 'mistaken republish',
            'accrual_authority' => false,
        ], $admin, true);

        $this->assertFalse((bool) $broken->accrual_authority);
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-001'));

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('ROI Not Configured');

        $fixed = app(PackagePlanAdministration::class)->savePlanVersion($package->fresh(), [
            'has_daily_roi' => true,
            'roi_daily' => '0.1058',
            'roi_days' => 900,
            'wait_days' => 10,
            'commission_pool' => null,
            'commission_days' => null,
            'level_commission_eligible' => true,
            'label' => 'original_tour_plan_v1_silver',
            'change_note' => 'restore approved silver catalog roi',
            'accrual_authority' => true,
        ], $admin, true);

        $this->assertSame('0.1058', (string) $fixed->roi_daily);
        $this->assertTrue((bool) $fixed->accrual_authority);
        $def = app(PackageIncomePolicy::class)->definition('DST-TOUR-001');
        $this->assertSame('0.1058', $def['roi_daily']);
        $this->assertSame(900, $def['roi_days']);
        $this->assertSame('database_plan_version', $def['source']);
        $this->assertSame('0.1058', app(PackageIncomePolicy::class)->definitionForPurchase($purchase->fresh())['roi_daily']);
        $this->assertSame('purchase_snapshot', app(PackageIncomePolicy::class)->definitionForPurchase($purchase->fresh())['source']);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('0.1058 USDT / 900 days')
            ->assertDontSeeText('ROI Not Configured');

        $this->actingAs($admin)
            ->get(route('admin.packages.show', $package->fresh()))
            ->assertOk()
            ->assertSeeText('0.1058')
            ->assertSeeText('database_plan_version');

        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_associate_structure_can_be_restored_without_enabling_posting(): void
    {
        // Simulate local wipe of associate rows while keeping tables.
        \Illuminate\Support\Facades\DB::table('associate_commission_ranks')->delete();
        \Illuminate\Support\Facades\DB::table('associate_commission_packages')->delete();
        \Illuminate\Support\Facades\DB::table('associate_commission_versions')->delete();

        $structure = app(AssociateCommissionStructure::class);
        $this->assertNull($structure->storedVersion());
        $this->assertNotEmpty($structure->integrityErrors());

        $created = $structure->ensureCanonicalStructureStored();
        $this->assertTrue($created);
        $this->assertNotNull($structure->storedVersion());
        $this->assertSame([], $structure->integrityErrors());
        $this->assertFalse($structure->postingEnabled());
        $this->assertFalse($structure->snapshotOnApprovalEnabled());
        $this->assertSame('DST-TOUR-001', $structure->mappedPackageCode('silver'));
        $this->assertSame('0.1200', $structure->preview()['tiers'][0]['daily_total']);

        $this->assertFalse($structure->ensureCanonicalStructureStored());
        $this->artisan('associate:ensure-structure')->assertSuccessful();
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }
}

