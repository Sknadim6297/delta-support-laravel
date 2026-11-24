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
use App\Services\ReferralLevelEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LevelIncomeQualificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_draft_stores_qualification_settings_without_authorizing(): void
    {
        $proposed = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $this->assertSame(LevelIncomeRuleVersion::STATUS_DRAFT, $proposed->status);
        $this->assertFalse($proposed->posting_authorized);
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            $proposed->direct_qualification_basis
        );
        $this->assertTrue($proposed->tour_only_qualifies_for_unlock);

        $legacy = LevelIncomeRuleVersion::query()
            ->where('version_code', 'legacy_live_v1')
            ->firstOrFail();
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
            $legacy->direct_qualification_basis
        );
        $this->assertFalse($legacy->tour_only_qualifies_for_unlock);

        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());
    }

    public function test_live_unlock_ignores_draft_qualification_until_authorized(): void
    {
        $sponsor = $this->member('QLF001', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('QLF002', $sponsor->member_id);
        $this->purchase($direct, $this->tourPackage(), PackagePurchase::APPROVED, '200.0000');

        $summary = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(1, $summary['registered_direct_count']);
        $this->assertSame(0, $summary['qualifying_direct_count']);
        $this->assertSame(0, $summary['unlocked_levels']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $summary['rule_mode']);
        $this->assertNotNull($summary['qualification_preview']);
        $this->assertSame(1, $summary['qualification_preview']['tour_only_purchasing_direct_count']);
        $this->assertSame(1, $summary['qualification_preview']['approved_package_direct_count']);
        $this->assertSame(
            8,
            $summary['qualification_preview']['candidate_expected']['expected_unlocked_levels']
        );
    }

    public function test_unique_member_vs_purchase_count_bases_are_distinct(): void
    {
        $sponsor = $this->member('QLF010', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('QLF011', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('QLF-ROI-A'), PackagePurchase::APPROVED, '50.0000');
        $this->purchase($direct, $this->roiPackage('QLF-ROI-B'), PackagePurchase::APPROVED, '75.0000');

        $qualification = app(LevelIncomeDirectQualification::class);
        $uniqueVersion = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'tour_only_qualifies_for_unlock' => false,
        ]);
        $purchaseVersion = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_PURCHASE_COUNT,
            'tour_only_qualifies_for_unlock' => false,
        ]);

        $this->assertSame(1, $qualification->countForVersion($sponsor->member_id, $uniqueVersion));
        $this->assertSame(2, $qualification->countForVersion($sponsor->member_id, $purchaseVersion));
    }

    public function test_tour_only_unlock_flag_independent_of_income_eligible_basis(): void
    {
        $sponsor = $this->member('QLF020', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('QLF021', $sponsor->member_id);
        $this->purchase($direct, $this->tourPackage(), PackagePurchase::APPROVED, '200.0000');

        $qualification = app(LevelIncomeDirectQualification::class);
        $withoutTour = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'tour_only_qualifies_for_unlock' => false,
        ]);
        $withTour = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'tour_only_qualifies_for_unlock' => true,
        ]);
        $incomeOnly = new LevelIncomeRuleVersion([
            'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
            'tour_only_qualifies_for_unlock' => false,
        ]);

        $this->assertSame(0, $qualification->countForVersion($sponsor->member_id, $withoutTour));
        $this->assertSame(1, $qualification->countForVersion($sponsor->member_id, $withTour));
        $this->assertSame(0, $qualification->countForVersion($sponsor->member_id, $incomeOnly));
    }

    public function test_authorized_version_applies_qualification_basis_without_backfill(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $resolver = app(LevelIncomeRuleResolver::class);

        $sponsor = $this->member('QLF030', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('QLF031', $sponsor->member_id);
        $this->purchase($direct, $this->tourPackage(), PackagePurchase::APPROVED, '200.0000');

        $before = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(0, $before['unlocked_levels']);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            $draft->direct_qualification_basis
        );
        $this->assertTrue($draft->tour_only_qualifies_for_unlock);

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-01-01',
        ]);
        $published->forceFill(['grandfather_existing_unlock' => false])->save();
        $this->assertFalse($published->posting_authorized);
        $this->assertSame(0, app(ReferralLevelEligibility::class)->forMember($sponsor->member_id)['unlocked_levels']);

        $authorized = $service->authorizePosting($published->fresh(), $admin, true);
        $resolver->clearCache();
        $this->assertTrue($authorized->posting_authorized);

        $after = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(LevelIncomeRuleResolver::MODE_AUTHORIZED, $after['rule_mode']);
        $this->assertSame(1, $after['qualifying_direct_count']);
        $this->assertSame(8, $after['unlocked_levels']);

        $service->revokePostingAuthorization($authorized, $admin);
        $resolver->clearCache();
        $this->assertSame(0, app(ReferralLevelEligibility::class)->forMember($sponsor->member_id)['unlocked_levels']);
    }

    public function test_admin_can_save_qualification_settings_on_draft(): void
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
        $rates = [];
        foreach (CommissionPlan::RATE_BY_LEVEL as $level => $pct) {
            $rates[$level] = $pct;
        }

        $this->actingAs($admin)
            ->post(route('admin.finance.level-income-rules.draft'), [
                'version_id' => $draft->id,
                'label' => $draft->label,
                'direct_qualification_basis' => LevelIncomeDirectQualification::BASIS_REGISTERED_DIRECTS,
                'tour_only_qualifies_for_unlock' => '0',
                'bands' => $bands,
                'rates' => $rates,
            ])
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(
            LevelIncomeDirectQualification::BASIS_REGISTERED_DIRECTS,
            $draft->direct_qualification_basis
        );
        $this->assertFalse($draft->tour_only_qualifies_for_unlock);
        $this->assertFalse($draft->posting_authorized);
    }

    public function test_member_preview_lists_modes_and_locked_reason(): void
    {
        $sponsor = $this->member('QLF040', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $this->member('QLF041', $sponsor->member_id);

        $preview = app(LevelIncomeDirectQualification::class)->memberPreview($sponsor->member_id);
        $this->assertSame(1, $preview['registered_direct_count']);
        $this->assertSame(0, $preview['income_eligible_direct_count']);
        $this->assertSame(0, $preview['currently_unlocked_levels']);
        $this->assertArrayHasKey('registered_directs', $preview['expected_under_each_mode']);
        $this->assertArrayHasKey('approved_package_directs+tour', $preview['expected_under_each_mode']);
        $this->assertNotEmpty($preview['locked_reason']);
        $this->assertFalse($preview['candidate_expected']['posting_authorized']);
    }

    public function test_commission_settings_shows_qualification_controls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'unlock']))
            ->assertOk()
            ->assertSeeText('Direct qualification basis')
            ->assertSeeText('Approved Tour Only packages qualify for level unlocking');
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

    private function roiPackage(string $code = 'DST-ROI-QLF'): Package
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

    private function tourPackage(string $code = 'DST-TOUR-QLF'): Package
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
