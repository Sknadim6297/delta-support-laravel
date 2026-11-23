<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePlanAudit;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageAutoRoiControl;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use App\Support\PackageReturnDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimplePackageAutoRoiTest extends TestCase
{
    use RefreshDatabase;

    public function test_eight_fields_monthly_and_total_return_save_and_reload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $before = WalletLedgerEntry::query()->count();

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-SIMPLE-1',
            'name' => 'Simple Silver',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 1,
            'is_active' => '1',
            'tour_nights' => '1',
            'tour_days' => '2',
            'tour_adults' => '2',
            'tour_children' => '1',
            'food_coupon_amount' => '1000.0000',
            'food_coupon_currency' => 'INR',
            'roi_daily' => '0.1234',
            'roi_days' => '900',
        ])->assertRedirect();

        $package = Package::query()->where('code', 'DST-SIMPLE-1')->firstOrFail();
        $this->assertFalse((bool) $package->auto_roi_enabled);
        $this->assertSame('0.1234', (string) $package->activePlanVersion->roi_daily);
        $this->assertSame(900, (int) $package->activePlanVersion->roi_days);
        $this->assertSame('3.7020', PackageReturnDisplay::monthlyReturn('0.1234'));
        $this->assertSame('111.0600', PackageReturnDisplay::totalReturn('0.1234', 900));
        $this->assertSame('30', PackageReturnDisplay::totalMonthsDisplay(900));

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('value="0.1234"', false)
            ->assertSee('name="roi_days"', false)
            ->assertDontSee('income_source')
            ->assertDontSee('accrual_authority')
            ->assertDontSee('commission_pool');

        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'DST-SIMPLE-1',
            'name' => 'Simple Silver',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 1,
            'is_active' => '1',
            'tour_nights' => '1',
            'tour_days' => '2',
            'tour_adults' => '2',
            'tour_children' => '1',
            'roi_daily' => '0.2222',
            'roi_days' => '600',
        ])->assertRedirect(route('admin.packages.edit', $package));

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('value="0.2222"', false)
            ->assertSee('value="600"', false);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('Simple Silver')
            ->assertSeeText('0.2222 USDT')
            ->assertSeeText('Auto ROI');

        $this->assertSame($before, WalletLedgerEntry::query()->count());
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_auto_roi_off_blocks_accrual_on_allows_when_global_permits(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STAUTO001', 'ST666666');
        $package = Package::query()->create([
            'code' => 'DST-AUTO-1',
            'name' => 'Auto Pack',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'auto_roi_enabled' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_LEGACY,
            'sort_order' => 1,
        ]);
        // Use a known legacy config code path via forcing definition through plan version + database.
        $package->income_source = Package::INCOME_SOURCE_DATABASE;
        $package->save();
        $version = PackagePlanVersion::query()->create([
            'package_id' => $package->id,
            'version_number' => 1,
            'status' => PackagePlanVersion::STATUS_PUBLISHED,
            'has_daily_roi' => true,
            'roi_daily' => '0.2500',
            'roi_days' => 800,
            'wait_days' => 10,
            'roi_start_rule' => 'approval_plus_wait_days',
            'roi_end_rule' => 'first_accrual_plus_duration_minus_one',
            'accrual_authority' => true,
            'published_at' => now()->subDays(30),
            'created_by' => $admin->id,
            'published_by' => $admin->id,
        ]);
        $package->forceFill(['active_plan_version_id' => $version->id])->save();

        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-AUTO-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_plan_version_id' => $version->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-AUTO-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDays(20),
            'reviewed_at' => now()->subDays(20),
        ]);
        PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'package_plan_version_id' => $version->id,
            'income_definition_source' => 'purchase_plan_version',
            'reference' => 'PIT-AUTO-1',
            'income_eligible' => true,
            'monthly_amount' => '0.2500',
            'duration_months' => 800,
            'snapshotted_at' => now()->subDays(20),
        ]);

        $businessDate = now()->subDays(20)->addDays(10)->toDateString();
        $previewOff = app(PackageIncomeAccrual::class)->preview($businessDate);
        $this->assertSame([], $previewOff['roi']);

        $this->actingAs($admin)->patch(route('admin.packages.auto-roi', $package), [
            'auto_roi_enabled' => '1',
            'confirm_auto_roi' => '1',
        ])->assertRedirect(route('admin.packages.edit', $package));

        $this->assertTrue((bool) $package->fresh()->auto_roi_enabled);
        $this->assertTrue(
            PackagePlanAudit::query()->where('package_id', $package->id)->where('action', 'auto_roi_enabled')->exists()
        );
        $this->assertSame(0, WalletLedgerEntry::query()->count());

        $previewOn = app(PackageIncomeAccrual::class)->preview($businessDate);
        $this->assertNotEmpty($previewOn['roi']);

        // Global emergency stop keeps package ON but posting unauthorized (label stays simple ON).
        config(['deltasupport.controls.financial_emergency_stop' => true]);
        $status = app(PackageAutoRoiControl::class)->effectiveStatus($package->fresh());
        $this->assertSame(PackageAutoRoiControl::STATUS_ON_BLOCKED_GLOBAL, $status['code']);
        $this->assertSame('ON — Globally Blocked', $status['label']);
        $this->assertStringContainsString('global financial controls', (string) $status['message']);
        config(['deltasupport.controls.financial_emergency_stop' => false]);

        $this->actingAs($admin)->patch(route('admin.packages.auto-roi', $package), [
            'auto_roi_enabled' => '0',
        ])->assertRedirect();

        $this->assertFalse((bool) $package->fresh()->auto_roi_enabled);
        $previewAgain = app(PackageIncomeAccrual::class)->preview($businessDate);
        $this->assertSame([], $previewAgain['roi']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_invalid_definition_and_delta_share_cannot_enable_auto_roi(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $incomplete = Package::query()->create([
            'code' => 'DST-NODEF-1',
            'name' => 'No Def',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => false,
            'auto_roi_enabled' => false,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_DATABASE,
            'sort_order' => 1,
        ]);
        $this->actingAs($admin)->patch(route('admin.packages.auto-roi', $incomplete), [
            'auto_roi_enabled' => '1',
            'confirm_auto_roi' => '1',
        ])->assertSessionHasErrors('auto_roi_enabled');

        $share = Package::query()->create([
            'code' => 'DST-RY-006',
            'name' => 'DELTA SHARE',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR,
            'roi_eligible' => false,
            'auto_roi_enabled' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'income_source' => Package::INCOME_SOURCE_LEGACY,
            'sort_order' => 6,
        ]);
        $this->actingAs($admin)->patch(route('admin.packages.auto-roi', $share), [
            'auto_roi_enabled' => '1',
            'confirm_auto_roi' => '1',
        ])->assertSessionHasErrors('auto_roi_enabled');

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', $share))
            ->assertOk()
            ->assertSeeText('Tour Only packages have no Daily ROI');
    }

    public function test_admin_can_change_silver_daily_roi_from_1058_to_1100_and_list_shows_saved_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $package = Package::query()->create([
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER PACKAGE',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'auto_roi_enabled' => false,
            'level_commission_eligible' => true,
            'is_active' => true,
            'purchase_available' => true,
            'income_source' => Package::INCOME_SOURCE_DATABASE,
            'sort_order' => 1,
        ]);
        $v1 = app(PackagePlanAdministration::class)->savePlanVersion($package, [
            'has_daily_roi' => true,
            'roi_daily' => '0.1058',
            'roi_days' => 900,
            'wait_days' => 10,
            'accrual_authority' => true,
        ], $admin, true);
        $package->refresh();
        $this->assertSame('0.1058', (string) $v1->roi_daily);

        $beforeWallets = WalletLedgerEntry::query()->count();
        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER PACKAGE',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 1,
            'is_active' => '1',
            'tour_nights' => '1',
            'tour_days' => '2',
            'tour_adults' => '2',
            'tour_children' => '1',
            'roi_daily' => '0.11',
            'roi_days' => '900',
        ])->assertRedirect(route('admin.packages.edit', $package))
            ->assertSessionHas('success');

        $package->refresh();
        $active = $package->activePlanVersion;
        $this->assertNotNull($active);
        $this->assertSame('0.1100', (string) $active->roi_daily);
        $this->assertSame(900, (int) $active->roi_days);
        $this->assertSame('0.1100', app(PackageIncomePolicy::class)->definition('DST-TOUR-001')['roi_daily']);
        $this->assertSame(PackagePlanVersion::STATUS_SUPERSEDED, $v1->fresh()->status);
        $this->assertSame('0.1058', (string) $v1->fresh()->roi_daily);

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('value="0.1100"', false)
            ->assertDontSee('value="0.1058"', false);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('0.1100 USDT')
            ->assertSeeText('3.3000 USDT')
            ->assertSeeText('99.0000 USDT')
            ->assertDontSeeText('0.1058 USDT');

        $this->assertSame($beforeWallets, WalletLedgerEntry::query()->count());
    }

    public function test_gold_legacy_and_purchase_snapshot_survive_simple_save(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STGOLD001', 'ST666666');
        $package = Package::query()->create([
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
            'sort_order' => 2,
        ]);
        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);

        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-GOLD-S1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-GOLD-S1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDays(20),
            'reviewed_at' => now()->subDays(20),
        ]);
        PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'income_definition_source' => 'legacy_snapshot',
            'reference' => 'PIT-GOLD-S1',
            'income_eligible' => true,
            'monthly_amount' => '0.2500',
            'duration_months' => 800,
            'snapshotted_at' => now()->subDays(20),
        ]);

        $beforeWallets = WalletLedgerEntry::query()->count();
        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'DST-ROI-002',
            'name' => 'GOLD PACKAGE',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '100.0000',
            'sort_order' => 2,
            'is_active' => '1',
            'tour_nights' => '2',
            'tour_days' => '3',
            'tour_adults' => '2',
            'tour_children' => '1',
            'roi_daily' => '0.2404',
            'roi_days' => '800',
        ])->assertRedirect(route('admin.packages.edit', $package));

        $this->assertSame(Package::INCOME_SOURCE_LEGACY, $package->fresh()->income_source);
        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame('0.2500', app(PackageIncomePolicy::class)->definitionForPurchase($purchase->fresh())['roi_daily']);
        $this->assertSame($beforeWallets, WalletLedgerEntry::query()->count());
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
}
