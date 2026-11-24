<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Services\LevelIncomeRuleResolver;
use App\Services\PackageBusinessVolumeEligibility;
use App\Services\PackageIncomeAccrual;
use App\Services\ReferralLevelEligibility;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression: Tour Only BV inclusion without income side-effects;
 * unlock remains unique income-qualifying directs under legacy until authorized.
 */
class LevelUnlockAndBusinessVolumeFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_without_directs_stays_at_zero_unlock_under_legacy(): void
    {
        $member = $this->member('STUL001', 'ST666666');
        $this->purchase($member, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $summary = app(ReferralLevelEligibility::class)->forMember($member->member_id);
        $this->assertTrue($summary['personally_activated']);
        $this->assertSame(0, $summary['registered_direct_count']);
        $this->assertSame(0, $summary['qualifying_direct_count']);
        $this->assertSame(0, $summary['unlocked_levels']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $summary['rule_mode']);
        $this->assertSame(4, $summary['authorized_preview_unlocked_levels']);
    }

    public function test_registered_direct_without_purchase_does_not_unlock(): void
    {
        $sponsor = $this->member('STUL010', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $this->member('STUL011', $sponsor->member_id); // join only

        $summary = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(1, $summary['registered_direct_count']);
        $this->assertSame(0, $summary['qualifying_direct_count']);
        $this->assertSame(0, $summary['unlocked_levels']);
    }

    public function test_direct_with_approved_roi_purchase_unlocks_legacy_four(): void
    {
        $sponsor = $this->member('STUL020', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('STUL021', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $summary = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(1, $summary['qualifying_direct_count']);
        $this->assertSame(4, $summary['unlocked_levels']);
    }

    public function test_multiple_packages_by_same_direct_count_as_one_qualifying_member(): void
    {
        $sponsor = $this->member('STUL030', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('STUL031', $sponsor->member_id);
        $pkg = $this->roiPackage('DST-MULTI-A');
        $this->purchase($direct, $pkg, PackagePurchase::APPROVED, '50.0000');
        $this->purchase($direct, $this->roiPackage('DST-MULTI-B'), PackagePurchase::APPROVED, '75.0000');

        $summary = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(1, $summary['qualifying_direct_count']);
        $this->assertSame(4, $summary['unlocked_levels']);

        $bv = app(TeamBusinessCalculationService::class)->businessForMembers([$sponsor->member_id]);
        $this->assertSame('125.0000', $bv[$sponsor->member_id]['first_level_business']);
    }

    public function test_tour_only_adds_business_volume_without_roi_or_level_accrual(): void
    {
        config(['deltasupport.package_income.enabled' => true]);

        $buyer = $this->member('STUL040', 'ST666666');
        $tour = $this->tourPackage();
        $purchase = $this->purchase($buyer, $tour, PackagePurchase::APPROVED, '250.0000', reviewedAt: '2026-10-01 06:00:00');

        $bv = app(TeamBusinessCalculationService::class)->businessForMembers([$buyer->member_id]);
        $this->assertSame('250.0000', $bv[$buyer->member_id]['total_team_business']);

        app(PackageIncomeAccrual::class)->accrue('2026-10-11');
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_purchase_id', $purchase->id)->count());
        $this->assertSame(0, PackageLevelAccrual::query()->where('package_purchase_id', $purchase->id)->count());
    }

    public function test_mixed_roi_and_tour_only_business_and_unlock_separation(): void
    {
        $root = $this->member('STUL050', 'ST666666');
        $d1 = $this->member('STUL051', $root->member_id);
        $d2 = $this->member('STUL052', $root->member_id);

        $this->purchase($root, $this->roiPackage(), PackagePurchase::APPROVED, '10.0000');
        $this->purchase($root, $this->tourPackage('TOUR-ROOT'), PackagePurchase::APPROVED, '40.0000');
        $this->purchase($d1, $this->tourPackage('TOUR-D1'), PackagePurchase::APPROVED, '100.0000');
        $this->purchase($d2, $this->roiPackage('ROI-D2'), PackagePurchase::APPROVED, '20.0000');

        $bv = app(TeamBusinessCalculationService::class)->businessForMembers([$root->member_id]);
        $this->assertSame('120.0000', $bv[$root->member_id]['first_level_business']);
        $this->assertSame('170.0000', $bv[$root->member_id]['total_team_business']);

        $unlock = app(ReferralLevelEligibility::class)->forMember($root->member_id);
        $this->assertSame(2, $unlock['registered_direct_count']);
        $this->assertSame(1, $unlock['qualifying_direct_count']);
        $this->assertSame(4, $unlock['unlocked_levels']);
    }

    public function test_pending_rejected_excluded_and_snapshot_price_preserved(): void
    {
        $root = $this->member('STUL060', 'ST666666');
        $direct = $this->member('STUL061', $root->member_id);
        $tour = $this->tourPackage();
        $tour->forceFill(['price_usd' => '999.0000'])->save();

        $this->purchase($direct, $tour, PackagePurchase::APPROVED, '123.4500');
        $this->purchase($direct, $tour, PackagePurchase::PENDING, '50.0000');
        $this->purchase($direct, $tour, PackagePurchase::REJECTED, '50.0000');

        $bv = app(TeamBusinessCalculationService::class)->businessForMembers([$root->member_id]);
        $this->assertSame('123.4500', $bv[$root->member_id]['first_level_business']);
        $this->assertSame('123.4500', $bv[$root->member_id]['total_team_business']);
    }

    public function test_business_volume_types_include_tour_and_income_types_exclude_tour(): void
    {
        $this->assertContains(Package::TYPE_TOUR, PackageBusinessVolumeEligibility::BUSINESS_VOLUME_PACKAGE_TYPES);
        $this->assertNotContains(Package::TYPE_TOUR, ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES);
    }

    public function test_eligibility_page_shows_applicable_rule_messaging(): void
    {
        $member = $this->member('STUL070', 'ST666666');
        $this->purchase($member, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.eligibility'))
            ->assertOk()
            ->assertSeeText('Applicable rule')
            ->assertSeeText('Qualifying directs')
            ->assertSeeText('Tour Only')
            ->assertSeeText('Qualification preview');
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '91000'.substr(preg_replace('/\D/', '', $memberId).'00000', -5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function roiPackage(string $code = 'DST-ROI-UL'): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => $code],
            [
                'package_type' => Package::TYPE_TOUR_ROI,
                'name' => $code,
                'price_usd' => '100.0000',
                'denomination' => 'USDT',
                'roi_eligible' => true,
                'level_commission_eligible' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function tourPackage(string $code = 'DST-TOUR-UL'): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => $code],
            [
                'package_type' => Package::TYPE_TOUR,
                'name' => $code,
                'price_usd' => '200.0000',
                'denomination' => 'USDT',
                'roi_eligible' => false,
                'level_commission_eligible' => false,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }

    private function purchase(
        Member $member,
        Package $package,
        string $status,
        string $amount,
        string $reviewedAt = '2026-10-01 06:00:00'
    ): PackagePurchase {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(5)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $amount,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(4)),
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === PackagePurchase::APPROVED ? $reviewedAt : null,
        ]);
    }
}
