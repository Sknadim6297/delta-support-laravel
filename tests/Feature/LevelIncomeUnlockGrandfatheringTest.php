<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\LevelIncomeUnlockGrandfatherSnapshot;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Services\LevelIncomeUnlockImpactAnalyzer;
use App\Services\ReferralLevelEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LevelIncomeUnlockGrandfatheringTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_draft_recommends_grandfathering_without_authorizing(): void
    {
        $proposed = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $this->assertTrue($proposed->grandfather_existing_unlock);
        $this->assertFalse($proposed->posting_authorized);
        $this->assertNull($proposed->grandfather_captured_at);
        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());
    }

    public function test_st100026_protected_keeps_legacy_four_and_allows_no_silent_loss(): void
    {
        $sponsor = $this->member('ST100026', 'ST666666');
        $direct = $this->member('ST100026A', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $version = $this->prepareAuthorizedVersionWithCapture('2026-11-24');

        $live = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(LevelIncomeRuleResolver::MODE_AUTHORIZED, $live['rule_mode']);
        $this->assertSame(0, $live['raw_authorized_unlocked_levels']);
        $this->assertSame(4, $live['unlocked_levels'], 'Grandfathering must preserve ST100026 live entitlement');
        $this->assertSame(4, $live['grandfather_runtime']['protected']);
        $this->assertSame(4, $live['legacy_unlocked_levels']);
    }

    public function test_st100027_gains_under_new_rules_while_protection_does_not_cap_upward(): void
    {
        $sponsor = $this->member('ST100027', 'ST666666');
        $this->purchase($sponsor, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');
        $direct = $this->member('ST100027A', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('ROI-27A'), PackagePurchase::APPROVED, '100.0000');

        $version = $this->prepareAuthorizedVersionWithCapture('2026-11-24');

        $live = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        // Proposed: activated + 1 approved-package direct → 8; legacy was 4; protected = max(8, min(4,4)) = 8.
        $this->assertSame(8, $live['raw_authorized_unlocked_levels']);
        $this->assertSame(8, $live['unlocked_levels']);
        $this->assertGreaterThanOrEqual(4, $live['unlocked_levels']);
    }

    public function test_protection_shrinks_when_legacy_eligibility_is_lost(): void
    {
        $sponsor = $this->member('GFLOSS1', 'ST666666');
        $direct = $this->member('GFLOSS1D', $sponsor->member_id);
        $purchase = $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $version = $this->prepareAuthorizedVersionWithCapture('2026-11-24');
        $this->assertSame(4, app(ReferralLevelEligibility::class)->forMember($sponsor->member_id)['unlocked_levels']);

        // Normal eligibility change: remove qualifying direct purchase (reject).
        $purchase->forceFill([
            'status' => PackagePurchase::REJECTED,
            'reviewed_at' => now(),
        ])->save();

        $after = app(ReferralLevelEligibility::class)->forMember($sponsor->member_id);
        $this->assertSame(0, $after['legacy_unlocked_levels']);
        $this->assertSame(0, $after['unlocked_levels'], 'Protection must not preserve unlock after legacy eligibility is lost');
    }

    public function test_authorize_requires_capture_when_grandfathering_enabled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $this->assertTrue($draft->grandfather_existing_unlock);

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-11-24',
        ]);

        try {
            $service->authorizePosting($published->fresh(), $admin, true);
            $this->fail('Expected ValidationException for missing grandfather capture');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grandfather', $e->errors());
        }
        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());
    }

    public function test_admin_impact_preview_includes_current_proposed_protected(): void
    {
        $sponsor = $this->member('GFUI001', 'ST666666');
        $direct = $this->member('GFUI001D', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $proposed = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $preview = app(LevelIncomeRuleAdministration::class)->impactPreview($proposed);
        $this->assertTrue($preview['grandfather_existing_unlock']);
        $this->assertNotEmpty($preview['member_unlock_impact_sample']);
        $row = collect($preview['member_unlock_impact_sample'])->firstWhere('member_id', 'GFUI001');
        $this->assertNotNull($row);
        $this->assertSame(4, $row['current_legacy']);
        $this->assertSame(0, $row['proposed']);
        $this->assertSame(4, $row['protected']);
    }

    public function test_capture_is_audited_and_versioned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->member('GFCAP1', 'ST666666');
        $version = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $result = app(LevelIncomeUnlockGrandfathering::class)
            ->captureBaseline($version, $admin, '2026-11-20');

        $version->refresh();
        $this->assertSame('2026-11-20', $version->grandfather_transition_date->toDateString());
        $this->assertNotNull($version->grandfather_captured_at);
        $this->assertSame($result['captured'], LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->count());
        $this->assertDatabaseHas('level_income_rule_audits', [
            'level_income_rule_version_id' => $version->id,
            'action' => 'grandfather_baseline_captured',
        ]);
        $this->assertFalse($version->posting_authorized);
    }

    public function test_impact_analyzer_marks_st100026_protected_not_loss_when_grandfathering_on(): void
    {
        $sponsor = $this->member('ST100026', 'ST666666');
        $direct = $this->member('ST100026B', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage(), PackagePurchase::APPROVED, '100.0000');

        $proposed = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $row = app(LevelIncomeUnlockImpactAnalyzer::class)->analyzeMember($sponsor->member_id, $proposed);
        $this->assertSame(4, $row['live_unlocked_levels']);
        $this->assertSame(0, $row['proposed_unlocked_levels']);
        $this->assertSame(4, $row['protected_unlocked_levels']);
        $this->assertTrue($row['raw_proposed_reduction']);
        $this->assertFalse($row['entitlement_reduction']);
        $this->assertSame(LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED, $row['impact']);
        $this->assertStringContainsString('PROTECTED', $row['delta_reason']);
    }

    public function test_admin_can_capture_via_http_without_authorizing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $version = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.finance.level-income-rules.grandfather-capture'), [
                'version_id' => $version->id,
                'transition_date' => '2026-11-24',
                'confirm_capture' => '1',
            ])
            ->assertRedirect();

        $version->refresh();
        $this->assertNotNull($version->grandfather_captured_at);
        $this->assertFalse($version->posting_authorized);
    }

    private function prepareAuthorizedVersionWithCapture(string $transitionDate): LevelIncomeRuleVersion
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => $transitionDate,
        ]);

        $grandfathering->captureBaseline($published->fresh(), $admin, $transitionDate);
        $authorized = $service->authorizePosting($published->fresh(), $admin, true);
        app(LevelIncomeRuleResolver::class)->clearCache();

        return $authorized;
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

    private function roiPackage(string $code = 'DST-ROI-GF'): Package
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
