<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\CommissionPlan;
use App\Services\LevelIncomeDirectQualification;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Services\ReferralLevelEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CombinedLevelUnlockQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_legacy_unchanged_while_combined_settings_are_staged(): void
    {
        $sponsor = $this->member('CMB001', 'ST666666');
        $this->member('CMB001A', $sponsor->member_id); // join only
        $buyer = $this->member('CMB001B', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $summary = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $summary['rule_mode']);
        $this->assertSame(2, $summary['registered_direct_count']);
        $this->assertSame(1, $summary['qualifying_direct_count']);
        $this->assertSame(4, $summary['unlocked_levels']);
        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());
    }

    public function test_combined_prevent_overlap_counts_join_and_purchase_independently_without_double_credit(): void
    {
        $sponsor = $this->member('CMB010', 'ST666666');
        $this->member('CMB010A', $sponsor->member_id); // join only
        $buyer = $this->member('CMB010B', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $this->purchase($buyer, $this->roiPackage('CMB-ROI-2'), PackagePurchase::APPROVED, '50.0000');

        $version = $this->combinedVersion([
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
        ]);

        $breakdown = app(LevelIncomeDirectQualification::class)->breakdownForVersion($sponsor->member_id, $version);
        $this->assertSame('combined', $breakdown['mode']);
        $this->assertSame(2, $breakdown['registered_direct_count']);
        $this->assertSame(1, $breakdown['purchasing_unique_direct_count']);
        $this->assertSame(1, $breakdown['joining_weighted']); // only non-purchasing join
        $this->assertSame(1, $breakdown['purchase_weighted']);
        $this->assertSame(2, $breakdown['total_metric']);
        $this->assertSame(1, $breakdown['overlapping_directs']);
    }

    public function test_combined_allow_sum_can_double_credit_same_direct(): void
    {
        $sponsor = $this->member('CMB020', 'ST666666');
        $buyer = $this->member('CMB020A', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $version = $this->combinedVersion([
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_ALLOW_SUM,
        ]);

        $breakdown = app(LevelIncomeDirectQualification::class)->breakdownForVersion($sponsor->member_id, $version);
        $this->assertSame(1, $breakdown['joining_weighted']);
        $this->assertSame(1, $breakdown['purchase_weighted']);
        $this->assertSame(2, $breakdown['total_metric']);
    }

    public function test_combined_max_only_uses_larger_contribution(): void
    {
        $sponsor = $this->member('CMB030', 'ST666666');
        $this->member('CMB030A', $sponsor->member_id);
        $this->member('CMB030B', $sponsor->member_id);
        $buyer = $this->member('CMB030C', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $version = $this->combinedVersion([
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_MAX_ONLY,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
        ]);

        $breakdown = app(LevelIncomeDirectQualification::class)->breakdownForVersion($sponsor->member_id, $version);
        // joining raw 3, purchase raw 1 → max of weighted = 3
        $this->assertSame(3, $breakdown['total_metric']);
    }

    public function test_repeat_each_purchase_counts_multiple_rows(): void
    {
        $sponsor = $this->member('CMB040', 'ST666666');
        $buyer = $this->member('CMB040A', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage('CMB-R1'), PackagePurchase::APPROVED, '40.0000');
        $this->purchase($buyer, $this->roiPackage('CMB-R2'), PackagePurchase::APPROVED, '60.0000');

        $unique = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
        ]);
        $each = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_EACH_PURCHASE,
            'version_code' => 'combined_each_'.Str::lower(Str::random(4)),
        ]);

        $q = app(LevelIncomeDirectQualification::class);
        $this->assertSame(1, $q->countForVersion($sponsor->member_id, $unique));
        $this->assertSame(2, $q->countForVersion($sponsor->member_id, $each));
    }

    public function test_authorized_combined_unlock_with_grandfathering_compatibility(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);

        $sponsor = $this->member('CMB050', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $this->member('CMB050A', $sponsor->member_id); // join only → under combined prevent_overlap contributes 1

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft = $service->saveDraft([
            'label' => $draft->label,
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASES,
            'tour_only_qualifies_for_unlock' => true,
            'grandfather_existing_unlock' => true,
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => true,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => 1,
            'combined_purchase_unit_value' => 1,
            'bands' => $draft->bands->map(fn ($b) => [
                'start_level' => $b->start_level,
                'end_level' => $b->end_level,
                'min_qualifying_directs' => $b->min_qualifying_directs,
                'requires_personal_activation' => $b->requires_personal_activation,
            ])->all(),
            'rates' => $draft->rates->mapWithKeys(fn ($r) => [(int) $r->level => (string) $r->percentage])->all(),
        ], $admin, null, $draft);

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-11-24',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-24');
        $authorized = $service->authorizePosting($published->fresh(), $admin, true);
        app(LevelIncomeRuleResolver::class)->clearCache();

        $live = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(LevelIncomeRuleResolver::MODE_AUTHORIZED, $live['rule_mode']);
        $this->assertSame('combined', $live['qualification_breakdown']['mode']);
        // Activated + metric 1 (join-only direct) → proposed bands unlock 8; grandfather floor ok.
        $this->assertSame(1, $live['qualifying_direct_count']);
        $this->assertSame(8, $live['unlocked_levels']);
        $this->assertTrue($authorized->posting_authorized);

        // Cleanup authorization for isolation of later suite tests is handled by RefreshDatabase.
    }

    public function test_admin_can_save_combined_settings_without_authorizing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $draft = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $bands = [];
        foreach ($draft->bands as $i => $band) {
            $bands[$i] = [
                'start_level' => $band->start_level,
                'end_level' => $band->end_level,
                'min_qualifying_directs' => $band->min_qualifying_directs,
                'requires_personal_activation' => $band->requires_personal_activation ? '1' : null,
            ];
        }

        $this->actingAs($admin)
            ->post(route('admin.finance.level-income-rules.draft'), [
                'version_id' => $draft->id,
                'label' => $draft->label,
                'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASES,
                'combined_joining_enabled' => '1',
                'combined_purchase_enabled' => '1',
                'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
                'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_EACH_PURCHASE,
                'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_ALLOW_SUM,
                'combined_joining_unit_value' => 1,
                'combined_purchase_unit_value' => 2,
                'bands' => $bands,
                'rates' => CommissionPlan::RATE_BY_LEVEL,
            ])
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASES, $draft->direct_qualification_basis);
        $this->assertTrue($draft->combined_joining_enabled);
        $this->assertTrue($draft->combined_purchase_enabled);
        $this->assertSame(LevelIncomeDirectQualification::DOUBLE_ALLOW_SUM, $draft->combined_double_credit_policy);
        $this->assertSame(2, (int) $draft->combined_purchase_unit_value);
        $this->assertFalse($draft->posting_authorized);
    }

    public function test_commission_settings_shows_combined_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'unlock']))
            ->assertOk()
            ->assertSeeText('Combined joining + purchase contributions')
            ->assertSeeText('Double-credit policy')
            ->assertSeeText('Personal (own) approved purchases');
    }

    public function test_canonical_basis_key_is_singular_with_plural_alias(): void
    {
        $q = app(LevelIncomeDirectQualification::class);
        $this->assertSame('combined_joining_and_purchase', LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASE);
        $this->assertSame('combined_joining_and_purchase', $q->normalizeBasis('combined_joining_and_purchase'));
        $this->assertSame('combined_joining_and_purchase', $q->normalizeBasis('combined_joining_and_purchases'));
    }

    public function test_joining_only_without_purchase_contributes_metric(): void
    {
        $sponsor = $this->member('CMB060', 'ST666666');
        $this->member('CMB060A', $sponsor->member_id);
        $this->member('CMB060B', $sponsor->member_id);

        $version = $this->combinedVersion([
            'combined_purchase_enabled' => false,
            'combined_joining_enabled' => true,
        ]);

        $breakdown = app(LevelIncomeDirectQualification::class)->breakdownForVersion($sponsor->member_id, $version);
        $this->assertSame(2, $breakdown['joining_weighted']);
        $this->assertSame(0, $breakdown['purchase_weighted']);
        $this->assertSame(2, $breakdown['total_metric']);
    }

    public function test_personal_purchase_scope_counts_own_purchases_not_directs(): void
    {
        $sponsor = $this->member('CMB070', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage('CMB-SELF-1'), PackagePurchase::APPROVED, '100.0000');
        $this->purchase($sponsor, $this->roiPackage('CMB-SELF-2'), PackagePurchase::APPROVED, '50.0000');
        $direct = $this->member('CMB070A', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('CMB-DIR-1'), PackagePurchase::APPROVED, '80.0000');

        $unique = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_PERSONAL_APPROVED_PURCHASES,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
        ]);
        $each = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_PERSONAL_APPROVED_PURCHASES,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_EACH_PURCHASE,
            'version_code' => 'combined_personal_each_'.Str::lower(Str::random(4)),
        ]);

        $q = app(LevelIncomeDirectQualification::class);
        $this->assertSame(1, $q->countForVersion($sponsor->member_id, $unique));
        $this->assertSame(2, $q->countForVersion($sponsor->member_id, $each));
    }

    public function test_pending_and_rejected_purchases_do_not_count(): void
    {
        $sponsor = $this->member('CMB080', 'ST666666');
        $buyer = $this->member('CMB080A', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage('CMB-P1'), PackagePurchase::PENDING, '100.0000');
        $this->purchase($buyer, $this->roiPackage('CMB-R1'), PackagePurchase::REJECTED, '100.0000');

        $version = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'combined_purchase_enabled' => true,
        ]);

        $breakdown = app(LevelIncomeDirectQualification::class)->breakdownForVersion($sponsor->member_id, $version);
        $this->assertSame(0, $breakdown['purchase_raw']);
        $this->assertSame(0, $breakdown['total_metric']);
        // Joining-only still sees the registered direct when enabled.
        $joinOnly = $this->combinedVersion([
            'combined_purchase_enabled' => false,
            'version_code' => 'combined_join_only_'.Str::lower(Str::random(4)),
        ]);
        $this->assertSame(1, app(LevelIncomeDirectQualification::class)->countForVersion($sponsor->member_id, $joinOnly));
    }

    public function test_tour_only_qualification_respects_unlock_flag(): void
    {
        $sponsor = $this->member('CMB090', 'ST666666');
        $buyer = $this->member('CMB090A', $sponsor->member_id);
        $tour = Package::query()->firstOrCreate(
            ['code' => 'CMB-TOUR-1'],
            [
                'package_type' => Package::TYPE_TOUR,
                'name' => 'Tour Only',
                'price_usd' => '100.0000',
                'denomination' => 'USDT',
                'roi_eligible' => false,
                'level_commission_eligible' => false,
                'is_active' => true,
                'sort_order' => 9,
            ]
        );
        $this->purchase($buyer, $tour, PackagePurchase::APPROVED, '100.0000');

        $include = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'tour_only_qualifies_for_unlock' => true,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
        ]);
        $exclude = $this->combinedVersion([
            'combined_joining_enabled' => false,
            'tour_only_qualifies_for_unlock' => false,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'version_code' => 'combined_tour_off_'.Str::lower(Str::random(4)),
        ]);

        $q = app(LevelIncomeDirectQualification::class);
        $this->assertSame(1, $q->countForVersion($sponsor->member_id, $include));
        $this->assertSame(0, $q->countForVersion($sponsor->member_id, $exclude));
    }

    public function test_combined_existing_prospective_and_twenty_level_cap(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);

        $sponsor = $this->member('CMB100', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage('CMB-ACT'), PackagePurchase::APPROVED, '100.0000');
        for ($i = 1; $i <= 6; $i++) {
            $this->member('CMB100D'.$i, $sponsor->member_id);
        }

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft = $service->saveDraft([
            'label' => $draft->label,
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASE,
            'tour_only_qualifies_for_unlock' => true,
            'grandfather_existing_unlock' => false,
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => false,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_ALLOW_SUM,
            'combined_joining_unit_value' => 1,
            'combined_purchase_unit_value' => 1,
            'bands' => [
                [
                    'start_level' => 1,
                    'end_level' => 20,
                    'min_qualifying_directs' => 5,
                    'requires_personal_activation' => true,
                ],
            ],
            'rates' => $draft->rates->mapWithKeys(fn ($r) => [(int) $r->level => (string) $r->percentage])->all(),
        ], $admin, null, $draft);

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-01',
        ]);
        $authorized = $service->authorizePosting(
            $published->fresh(),
            $admin,
            true,
            null,
            true
        );
        app(LevelIncomeRuleResolver::class)->clearCache();

        $this->assertSame(LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE, $authorized->applicability);
        $live = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(LevelIncomeRuleResolver::MODE_AUTHORIZED, $live['rule_mode']);
        $this->assertSame(6, $live['qualifying_direct_count']);
        $this->assertSame(20, $live['unlocked_levels']);
        $this->assertLessThanOrEqual(20, $live['unlocked_levels']);

        $analysis = app(\App\Services\LevelIncomeUnlockImpactAnalyzer::class)
            ->analyzeMember($sponsor->member_id, $authorized);
        $this->assertCount(20, $analysis['level_lock_reasons']);
        $this->assertSame('unlocked', $analysis['level_lock_reasons'][19]['status']);
    }

    public function test_impact_analyzer_exposes_combined_credits_and_lock_reasons(): void
    {
        $sponsor = $this->member('CMB110', 'ST666666');
        $this->member('CMB110A', $sponsor->member_id);
        $buyer = $this->member('CMB110B', $sponsor->member_id);
        $this->purchase($buyer, $this->roiPackage('CMB-IMP'), PackagePurchase::APPROVED, '100.0000');

        $candidate = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();
        $candidate->fill([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASE,
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => true,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => 1,
            'combined_purchase_unit_value' => 1,
            'tour_only_qualifies_for_unlock' => true,
        ])->save();

        $row = app(\App\Services\LevelIncomeUnlockImpactAnalyzer::class)->analyzeMember($sponsor->member_id, $candidate->fresh('bands'));
        $this->assertSame('combined', $row['qualification_breakdown']['mode']);
        $this->assertSame(1, $row['counts']['joining_weighted']);
        $this->assertSame(1, $row['counts']['purchase_weighted']);
        $this->assertSame(2, $row['counts']['combined_total_metric']);
        $this->assertNotEmpty($row['level_lock_reasons']);
        $this->assertSame(20, count($row['level_lock_reasons']));
    }

    private function combinedVersion(array $overrides = []): LevelIncomeRuleVersion
    {
        $code = $overrides['version_code'] ?? ('combined_'.Str::lower(Str::random(6)));
        unset($overrides['version_code']);

        return LevelIncomeRuleVersion::query()->create(array_merge([
            'version_code' => $code,
            'label' => 'Combined test '.$code,
            'status' => LevelIncomeRuleVersion::STATUS_DRAFT,
            'posting_authorized' => false,
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED,
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASES,
            'tour_only_qualifies_for_unlock' => true,
            'combined_joining_enabled' => true,
            'combined_purchase_enabled' => true,
            'combined_purchase_scope' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => 1,
            'combined_purchase_unit_value' => 1,
            'grandfather_existing_unlock' => false,
        ], $overrides));
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

    private function roiPackage(string $code = 'DST-ROI-CMB'): Package
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
