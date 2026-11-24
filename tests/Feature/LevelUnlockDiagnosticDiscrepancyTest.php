<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Services\LevelIncomeDirectQualification;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LevelIncomeUnlockImpactAnalyzer;
use App\Services\PackageBusinessVolumeEligibility;
use App\Services\ReferralLevelEligibility;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression for production diagnostic discrepancies (ST100026 / ST100027 class).
 */
class LevelUnlockDiagnosticDiscrepancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_unlock_without_personal_package_shows_proposed_zero_with_entitlement_warning(): void
    {
        // ST100026 class: has income-eligible direct → live 4; no personal package → proposed 0.
        $sponsor = $this->member('ST100026', 'ST666666');
        $direct = $this->member('ST100026A', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $analyzer = app(LevelIncomeUnlockImpactAnalyzer::class);
        $row = $analyzer->analyzeMember($sponsor->member_id);

        $this->assertSame(4, $row['live_unlocked_levels']);
        $this->assertSame(0, $row['proposed_unlocked_levels']);
        // Proposed draft enables grandfathering — protected floor keeps live entitlement.
        $this->assertSame(4, $row['protected_unlocked_levels']);
        $this->assertTrue($row['raw_proposed_reduction']);
        $this->assertFalse($row['entitlement_reduction']);
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED, $row['impact']);
        $this->assertFalse($row['proposed_personally_activated']);
        $this->assertFalse($row['legacy_personally_activated']);
        $this->assertStringContainsString('PROTECTED', $row['delta_reason']);
        $this->assertFalse(
            $row['contexts'][LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY]['personal_activation_affects_unlock']
        );

        // Live posting path must not strip the 4 levels.
        $live = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(4, $live['unlocked_levels']);
        $this->assertSame(4, $live['legacy_unlocked_levels']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $live['rule_mode']);
    }

    public function test_same_member_live_unlock_is_identical_across_diagnostic_sections(): void
    {
        // ST100027 class: previously looked inconsistent across tables — single source of truth.
        $sponsor = $this->member('ST100027', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('ST100027A', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('ROI-ST27'), PackagePurchase::APPROVED, '100.0000');

        $analyzer = app(LevelIncomeUnlockImpactAnalyzer::class);
        $row = $analyzer->analyzeMember($sponsor->member_id);

        $this->assertSame(4, $row['live_unlocked_levels']);
        $this->assertSame(
            $row['live_unlocked_levels'],
            $row['contexts'][LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY]['unlocked_levels']
        );
        $this->assertFalse($row['activated_with_zero_live_unlock']);

        // Member with activation + 0 directs appears only in activated-zero with unlocked 0, never as live 4.
        $solo = $this->member('ST100027B', 'ST666666');
        $this->purchase($solo, $this->roiPackage('ROI-SOLO'), PackagePurchase::APPROVED, '50.0000');
        $soloRow = $analyzer->analyzeMember($solo->member_id);
        $this->assertSame(0, $soloRow['live_unlocked_levels']);
        $this->assertTrue($soloRow['activated_with_zero_live_unlock']);
        $this->assertSame(
            $soloRow['live_unlocked_levels'],
            $soloRow['contexts'][LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY]['unlocked_levels']
        );
    }

    public function test_eligibility_contexts_are_explicit_for_display_and_purchase_accrual(): void
    {
        $buyer = $this->member('CTX001', 'ST666666');
        $this->purchase($buyer, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $sponsor = $this->member('CTX000', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage('ROI-SP'), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('CTX002', $sponsor->member_id);
        $purchase = $this->purchase($direct, $this->roiPackage('ROI-D'), PackagePurchase::APPROVED, '100.0000');

        $display = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(
            LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY,
            $display['eligibility_context']['kind']
        );
        $this->assertNull($display['eligibility_context']['source_purchase_id']);
        $this->assertTrue($display['eligibility_context']['legacy_ladder_ignores_personal_activation']);

        $accrual = app(LevelIncomeUnlockImpactAnalyzer::class)
            ->purchaseAccrualContext($sponsor->member_id, $purchase, '2026-10-11');
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::CONTEXT_PURCHASE_ACCRUAL, $accrual['context']);
        $this->assertSame($purchase->id, $accrual['source_purchase_id']);
        $this->assertSame('2026-10-11', $accrual['business_date']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $accrual['rule_mode']);
        $this->assertArrayHasKey('posting_context', $accrual);
    }

    public function test_four_tour_only_purchases_total_200_counted_once_in_bv(): void
    {
        $amounts = ['50.0000', '50.0000', '50.0000', '50.0000'];
        $tour = $this->tourPackage();
        $owners = [];
        foreach ($amounts as $i => $amount) {
            $member = $this->member('TOURBV'.$i, 'ST666666');
            $owners[] = $member->member_id;
            $this->purchase($member, $tour, PackagePurchase::APPROVED, $amount);
        }

        $audit = app(LevelIncomeUnlockImpactAnalyzer::class)->tourOnlyBusinessVolumeAudit();
        $this->assertSame(4, $audit['purchase_count']);
        $this->assertSame(4, $audit['unique_purchase_ids']);
        $this->assertTrue($audit['counted_once']);
        $this->assertSame('200.0000', $audit['total_usdt']);

        $root = $this->member('TOURROOT', 'ST666666');
        // Attach owners as downline so team BV includes them when querying root… own BV:
        $bv = app(TeamBusinessCalculationService::class)->businessForMembers($owners);
        $sum = '0.0000';
        foreach ($owners as $id) {
            $sum = bcadd($sum, $bv[$id]['total_team_business'], 4);
        }
        $this->assertSame('200.0000', $sum);
        $this->assertContains(Package::TYPE_TOUR, PackageBusinessVolumeEligibility::BUSINESS_VOLUME_PACKAGE_TYPES);
    }

    public function test_impact_report_classifies_gain_loss_unchanged(): void
    {
        $loss = $this->member('IMPLOSS', 'ST666666');
        $lossDirect = $this->member('IMPLOSSD', $loss->member_id);
        $this->purchase($lossDirect, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $gain = $this->member('IMPGAIN', 'ST666666');
        $this->purchase($gain, $this->roiPackage('ROI-GAIN'), PackagePurchase::APPROVED, '100.0000');

        $flat = $this->member('IMPFLAT', 'ST666666');

        $result = app(LevelIncomeUnlockImpactAnalyzer::class)->analyzeMembers([
            $loss->member_id,
            $gain->member_id,
            $flat->member_id,
        ]);

        $byId = collect($result['rows'])->keyBy('member_id');
        // With draft grandfathering, IMPLOSS is protected to live 4 → unchanged (not a net loss).
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED, $byId['IMPLOSS']['impact']);
        $this->assertSame(4, $byId['IMPLOSS']['protected_unlocked_levels']);
        $this->assertTrue($byId['IMPLOSS']['raw_proposed_reduction']);
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::IMPACT_GAIN, $byId['IMPGAIN']['impact']);
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED, $byId['IMPFLAT']['impact']);
        $this->assertSame(0, $result['summary']['loss']);
        $this->assertSame(1, $result['summary']['gain']);
        $this->assertSame(2, $result['summary']['unchanged']);
    }

    public function test_qualification_policies_match_client_unique_vs_purchase_count_separation(): void
    {
        $policies = app(LevelIncomeUnlockImpactAnalyzer::class)->policyComparison();
        $byBasis = collect($policies)->keyBy('basis');

        $this->assertTrue($byBasis[LevelIncomeDirectQualification::BASIS_REGISTERED_DIRECTS]['unique_members']);
        $this->assertTrue($byBasis[LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS]['unique_members']);
        $this->assertTrue($byBasis[LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS]['unique_members']);
        $this->assertFalse($byBasis[LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT]['unique_members']);
        $this->assertTrue($byBasis[LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS]['live_default']);
        $this->assertTrue($byBasis[LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS]['proposed_draft_default']);

        $proposed = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            $proposed->direct_qualification_basis
        );
    }

    public function test_applicability_projection_explains_existing_approved_members(): void
    {
        $m = $this->member('APP43', 'ST666666');
        $this->purchase($m, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000', '2026-01-15 06:00:00');

        $projection = app(LevelIncomeUnlockImpactAnalyzer::class)
            ->applicabilityProjection(null, '2026-06-01');

        $this->assertSame(1, $projection['members_with_approved_packages']);
        $this->assertSame(1, $projection['future_purchases_only']['purchases_approved_before_effective']);
        $this->assertSame(0, $projection['future_purchases_only']['purchases_approved_on_or_after_effective']);
        $this->assertFalse($projection['existing_prospective']['historical_backfill']);
        $this->assertSame('unauthorized', $projection['authorization_status']);
        $this->assertStringContainsString('legacy', strtolower($projection['future_purchases_only']['effect_on_existing_43_style_members']));
    }

    public function test_diagnostic_command_json_is_consistent_and_read_only(): void
    {
        $sponsor = $this->member('ST100027', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('ST100027C', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('ROI-27C'), PackagePurchase::APPROVED, '100.0000');

        Artisan::call('deltasupport:diagnose-level-unlock-business', [
            '--member' => 'ST100027',
            '--limit' => 20,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['read_only']);
        $this->assertTrue($payload['consistency']['ok']);

        $focusLive = $payload['member_focus']['impact']['live_unlocked_levels'];
        $this->assertSame(4, $focusLive);
        $this->assertSame(
            $focusLive,
            $payload['member_focus']['consistency_check']['table_current_would_show']
        );
        $this->assertSame(
            $focusLive,
            $payload['member_focus']['consistency_check']['activated_zero_table_unlocked_would_show']
        );

        $inActivatedZero = collect($payload['activated_with_zero_live_unlock'])
            ->contains(fn (array $r): bool => $r['member_id'] === 'ST100027');
        $this->assertFalse($inActivatedZero, 'ST100027 with live 4 must not appear as unlocked 0');

        $loss = collect($payload['members_losing_levels'] ?? [])
            ->firstWhere('member_id', 'ST100027');
        // Has personal package + 1 direct under proposed → gain or match, not live4/proposed0 activation miss.
        if ($loss !== null) {
            $this->assertSame($focusLive, $loss['live_unlocked']);
        }
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

    private function roiPackage(string $code = 'DST-ROI-DISC'): Package
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

    private function tourPackage(string $code = 'DST-TOUR-DISC'): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => $code],
            [
                'package_type' => Package::TYPE_TOUR,
                'name' => $code,
                'price_usd' => '50.0000',
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
