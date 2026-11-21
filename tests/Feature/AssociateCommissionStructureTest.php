<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\AssociateCommissionStructure;
use App\Services\PackageIncomePolicy;
use App\Services\PackageIncomeTermRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssociateCommissionStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_tiers_match_exact_ranks_totals_and_duration(): void
    {
        $structure = app(AssociateCommissionStructure::class);
        $this->assertSame([], $structure->integrityErrors());

        $preview = $structure->preview();
        $this->assertSame('associate_v1', $preview['version_code']);
        $this->assertFalse($preview['is_active']);
        $this->assertFalse($preview['posting_enabled']);
        $this->assertFalse($preview['snapshot_on_approval']);
        $this->assertFalse($preview['writes']);
        $this->assertCount(4, $preview['tiers']);

        $byTier = collect($preview['tiers'])->keyBy('tier_code');
        $this->assertSame('0.1200', $byTier['silver']['daily_total']);
        $this->assertSame('48.0000', $byTier['silver']['duration_total']);
        $this->assertSame('0.2400', $byTier['gold']['daily_total']);
        $this->assertSame('96.0000', $byTier['gold']['duration_total']);
        $this->assertSame('1.2000', $byTier['premium']['daily_total']);
        $this->assertSame('480.0000', $byTier['premium']['duration_total']);
        $this->assertSame('2.4000', $byTier['vip']['daily_total']);
        $this->assertSame('960.0000', $byTier['vip']['duration_total']);

        $this->assertSame('0.0300', $byTier['silver']['ranks'][0]['daily_amount']);
        $this->assertSame('0.0090', $byTier['silver']['ranks'][19]['daily_amount']);
        $this->assertSame('0.0600', $byTier['gold']['ranks'][0]['daily_amount']);
        $this->assertSame('0.3000', $byTier['premium']['ranks'][0]['daily_amount']);
        $this->assertSame('0.6000', $byTier['vip']['ranks'][0]['daily_amount']);
        $this->assertSame('0.1800', $byTier['vip']['ranks'][19]['daily_amount']);

        foreach ($byTier as $tier) {
            $sum = '0.0000';
            $this->assertCount(20, $tier['ranks']);
            foreach ($tier['ranks'] as $rank) {
                $sum = bcadd($sum, $rank['daily_amount'], 4);
                $this->assertSame(bcmul($rank['daily_amount'], '400', 4), $rank['duration_amount']);
            }
            $this->assertSame($tier['daily_total'], $sum);
            $this->assertSame($tier['duration_total'], bcmul($sum, '400', 4));
            $this->assertSame(400, $tier['commission_days']);
        }
    }

    public function test_production_codes_preserve_legacy_roi_without_moving_to_vip(): void
    {
        Package::query()->updateOrCreate(['code' => 'DST-ROI-002'], [
            'name' => 'Gold mapped',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
        Package::query()->updateOrCreate(['code' => 'DST-TOUR-003'], [
            'name' => 'Premium mapped',
            'price_usd' => '500.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_PREMIUM,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);
        Package::query()->updateOrCreate(['code' => 'DST-TOUR-004'], [
            'name' => 'VIP mapped',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_PREMIUM,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $policy = app(PackageIncomePolicy::class);
        $gold = $policy->definition('DST-ROI-002');
        $premiumIncome = $policy->definition('DST-TOUR-003');
        $vipIncome = $policy->definition('DST-TOUR-004');
        $this->assertSame('0.2500', $gold['roi_daily']);
        $this->assertSame(800, $gold['roi_days']);
        $this->assertSame('0.2400', $gold['commission_pool']);
        // Legacy ROI config still keyed to DST-TOUR-003; it must not be moved to VIP.
        $this->assertSame('2.5000', $premiumIncome['roi_daily']);
        $this->assertSame(1000, $premiumIncome['roi_days']);
        $this->assertSame('2.4000', $premiumIncome['commission_pool']);
        $this->assertNull($vipIncome);

        $preview = app(AssociateCommissionStructure::class)->preview();
        $tiers = collect($preview['tiers'])->keyBy('tier_code');
        $this->assertTrue($tiers['gold']['pool_matches_legacy']);
        $this->assertFalse($tiers['premium']['pool_matches_legacy']); // associate 1.2000 vs legacy pool 2.4000
        $this->assertFalse($tiers['vip']['roi_configured']);
        $this->assertFalse($tiers['silver']['roi_configured']);
        $this->assertSame('gold', app(AssociateCommissionStructure::class)->resolveTierForPackageCode('DST-ROI-002'));
        $this->assertSame('premium', app(AssociateCommissionStructure::class)->resolveTierForPackageCode('DST-TOUR-003'));
        $this->assertSame('vip', app(AssociateCommissionStructure::class)->resolveTierForPackageCode('DST-TOUR-004'));
        $this->assertSame('silver', app(AssociateCommissionStructure::class)->resolveTierForPackageCode('DST-TOUR-001'));
    }

    public function test_snapshot_stays_off_by_default_and_is_immutable_when_forced(): void
    {
        $member = Member::query()->create([
            'member_id' => 'STASSOC01',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Assoc',
            'mobile_no' => '9000000001',
            'email' => 'assoc@example.test',
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
            'reference' => 'PUR-ASSOC-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'ASSOC-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $recorder = app(PackageIncomeTermRecorder::class);
        $default = $recorder->record($purchase);
        $this->assertNull($default->associate_commission_version);
        $this->assertNull($default->associate_rank_amounts);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        config(['deltasupport.associate_commission.snapshot_on_approval' => true]);
        $secondPurchase = PackagePurchase::query()->create([
            'reference' => 'PUR-ASSOC-2',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'ASSOC-2',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
        $snap = $recorder->record($secondPurchase);
        $this->assertSame('associate_v1', $snap->associate_commission_version);
        $this->assertSame('gold', $snap->associate_tier_code);
        $this->assertSame(400, $snap->associate_commission_days);
        $this->assertSame('0.2400', (string) $snap->associate_daily_total);
        $this->assertSame('0.0600', $snap->associate_rank_amounts[1]);
        $this->assertSame('0.0180', $snap->associate_rank_amounts[20]);

        $again = $recorder->record($secondPurchase, [
            'associate_tier_code' => 'vip',
            'associate_rank_amounts' => [1 => '9.9999'],
        ]);
        $this->assertSame($snap->id, $again->id);
        $this->assertSame('gold', $again->fresh()->associate_tier_code);
        $this->assertSame('0.0600', $again->fresh()->associate_rank_amounts[1]);

        $this->expectException(\RuntimeException::class);
        $snap->update(['associate_tier_code' => 'vip']);
    }

    public function test_rejected_purchase_has_no_associate_snapshot_or_wallet_credit(): void
    {
        $member = Member::query()->create([
            'member_id' => 'STASSOC02',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rejected',
            'mobile_no' => '9000000002',
            'email' => 'rej@example.test',
            'status' => 'inactive',
            'roi_wallet_amount' => '1.0000',
            'working_wallet_amount' => '2.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'Gold',
            'price_usd' => '100.0000',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PackagePurchase::query()->create([
            'reference' => 'PUR-ASSOC-REJ',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'ASSOC-REJ',
            'status' => PackagePurchase::REJECTED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Invalid reference',
        ]);

        $this->assertSame(0, PackageIncomeTerm::query()->count());
        $this->assertSame('1.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('2.0000', $member->fresh()->working_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_admin_package_and_commission_pages_show_associate_preview(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('GOLD PACKAGE')
            ->assertSeeText('SILVER PACKAGE')
            ->assertSeeText('PREMIUM PACKAGE')
            ->assertSeeText('VIP PACKAGE')
            ->assertSeeText('DST-TOUR-001')
            ->assertSeeText('DST-TOUR-003')
            ->assertSeeText('DST-TOUR-004')
            ->assertSeeText('0.2400 USDT/day')
            ->assertSeeText('ROI Not Configured');

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings'))
            ->assertOk()
            ->assertSeeText('Associate Commission Structure')
            ->assertSeeText('0.0300')
            ->assertSeeText('0.6000')
            ->assertSeeText('48.0000')
            ->assertSeeText('960.0000')
            ->assertSeeText('theoretical');

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', Package::query()->where('code', 'DST-ROI-002')->firstOrFail()))
            ->assertOk()
            ->assertSeeText('GOLD PACKAGE')
            ->assertSeeText('0.0600');
    }

    public function test_four_package_codes_resolve_without_price_ambiguity_or_duplicate_creation(): void
    {
        $structure = app(AssociateCommissionStructure::class);
        $this->assertSame('silver', $structure->resolveTierForPackageCode('DST-TOUR-001'));
        $this->assertSame('gold', $structure->resolveTierForPackageCode('DST-ROI-002'));
        $this->assertSame('premium', $structure->resolveTierForPackageCode('DST-TOUR-003'));
        $this->assertSame('vip', $structure->resolveTierForPackageCode('DST-TOUR-004'));
        $this->assertNull($structure->resolveTierForPackageCode('DST-SILVER-050'));
        $this->assertNull($structure->resolveTierForPackageCode('DST-PREMIUM-500'));

        // Two packages at 100 USDT must not silently bind by price for snapshots.
        Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'Gold',
            'price_usd' => '100.0000',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Package::query()->create([
            'code' => 'VERIFY-1',
            'name' => 'Verification',
            'price_usd' => '100.0000',
            'package_type' => Package::TYPE_TOUR,
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->assertNull($structure->resolveTierForPrice('100.0000'));
        $this->assertNull($structure->resolveTierForPackageCode('VERIFY-1'));

        $preview = $structure->preview();
        $this->assertFalse($preview['posting_enabled']);
        $this->assertFalse($preview['snapshot_on_approval']);
        $this->assertFalse($preview['accrual_uses_associate_posting']);
        $this->assertStringContainsString('DST-TOUR-001', $preview['catalog_finding']['summary']);
        $this->assertSame('DST-TOUR-001', $preview['tiers'][0]['mapped_package_code']);
        $this->assertSame('0.1200', $preview['tiers'][0]['daily_total']);
        $this->assertSame('DST-TOUR-003', collect($preview['tiers'])->firstWhere('tier_code', 'premium')['mapped_package_code']);
        $this->assertSame('1.2000', collect($preview['tiers'])->firstWhere('tier_code', 'premium')['daily_total']);
        $this->assertSame('DST-TOUR-004', collect($preview['tiers'])->firstWhere('tier_code', 'vip')['mapped_package_code']);
        $this->assertSame('2.4000', collect($preview['tiers'])->firstWhere('tier_code', 'vip')['daily_total']);
    }

    public function test_approval_snapshot_flag_off_keeps_associate_null_and_replay_is_idempotent(): void
    {
        config(['deltasupport.associate_commission.snapshot_on_approval' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $member = Member::query()->create([
            'member_id' => 'STASSOC03',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Buyer',
            'mobile_no' => '9000000003',
            'email' => 'buyer3@example.test',
            'status' => 'inactive',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'Gold',
            'price_usd' => '100.0000',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-ASSOC-APPR',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'ASSOC-APPR',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => now(),
        ]);

        $first = app(\App\Services\PackagePurchaseService::class)->approve($purchase, $admin);
        $second = app(\App\Services\PackagePurchaseService::class)->approve($purchase->fresh(), $admin);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PackageIncomeTerm::query()->count());
        $term = PackageIncomeTerm::query()->firstOrFail();
        $this->assertNull($term->associate_commission_version);
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_legacy_percentage_amounts_equal_associate_fixed_chart(): void
    {
        $policy = app(PackageIncomePolicy::class);
        foreach ([
            'gold' => '0.2400',
            'vip' => '2.4000',
            'silver' => '0.1200',
            'premium' => '1.2000',
        ] as $tier => $pool) {
            foreach (AssociateCommissionStructure::TIERS[$tier]['ranks'] as $rank => $amount) {
                $this->assertSame(
                    $amount,
                    $policy->levelAmount($pool, $rank),
                    $tier.' rank '.$rank
                );
            }
        }
    }
}
