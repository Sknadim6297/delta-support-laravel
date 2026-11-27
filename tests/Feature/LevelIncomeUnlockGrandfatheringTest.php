<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\LevelIncomeUnlockGrandfatherSnapshot;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeRun;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Services\LevelIncomeUnlockImpactAnalyzer;
use App\Services\ReferralLevelEligibility;
use Carbon\Carbon;
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
        // Option B: activated + 1 join + 1 personal purchase → metric 2 → 8; legacy was 4; protected = max(8, min(4,4)) = 8.
        $this->assertSame(2, $live['qualifying_direct_count']);
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
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_AS_OF_TRANSITION, $version->grandfather_capture_mode);
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_AS_OF_TRANSITION, $result['capture_mode']);
        $this->assertSame($result['captured'], LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->count());
        $this->assertDatabaseHas('level_income_rule_audits', [
            'level_income_rule_version_id' => $version->id,
            'action' => 'grandfather_baseline_captured',
        ]);
        $this->assertFalse($version->posting_authorized);
    }

    public function test_preview_and_runtime_match_for_members_without_snapshot_after_capture(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $existing = $this->member('GFNOSNAP1', 'ST666666');
        $direct = $this->member('GFNOSNAP1D', $existing->member_id);
        $this->purchase($direct, $this->roiPackage('ROI-NOSNAP'), PackagePurchase::APPROVED, '100.0000');

        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);

        // Capture before the new member exists — future cutover freezes current members only.
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');
        $this->assertSame(
            LevelIncomeUnlockGrandfathering::CAPTURE_MODE_PRE_CUTOVER_CURRENT,
            $published->fresh()->grandfather_capture_mode
        );

        $newMember = $this->member('GFNOSNAP2', 'ST666666');
        $newDirect = $this->member('GFNOSNAP2D', $newMember->member_id);
        $this->purchase($newDirect, $this->roiPackage('ROI-NOSNAP2'), PackagePurchase::APPROVED, '100.0000');

        // Proposed raw for joining-only without personal activation is 0.
        $preview = $grandfathering->previewMember($newMember->member_id, $published->fresh(), 0);
        $runtime = $grandfathering->apply($newMember->member_id, $published->fresh(), 0);
        $this->assertFalse($preview['using_provisional_snapshot']);
        $this->assertNull($preview['snapshot_levels']);
        $this->assertSame(0, $preview['protected']);
        $this->assertSame(0, $runtime);
        $this->assertStringContainsString('No grandfather snapshot', $preview['reason']);

        Carbon::setTestNow();
    }

    public function test_existing_prospective_requires_transition_match_effective_from(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);

        // Capture with a mismatched transition date.
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-27');

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, true);
            $this->fail('Expected ValidationException for mismatched transition/effective dates');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grandfather_transition_date', $e->errors());
        }

        Carbon::setTestNow();
    }

    public function test_future_dated_capture_is_labeled_pre_cutover_not_exact_as_of(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $sponsor = $this->member('GFFUTURE1', 'ST666666');
        $direct = $this->member('GFFUTURE1D', $sponsor->member_id);
        $this->purchase($direct, $this->roiPackage('ROI-FUTURE'), PackagePurchase::APPROVED, '100.0000');

        $version = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        $result = app(LevelIncomeUnlockGrandfathering::class)
            ->captureBaseline($version, $admin, '2026-11-28');

        $version->refresh();
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_PRE_CUTOVER_CURRENT, $result['capture_mode']);
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_PRE_CUTOVER_CURRENT, $version->grandfather_capture_mode);
        $this->assertStringContainsString('Not an exact future-date', $result['capture_note']);

        $snap = LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->where('member_id', $sponsor->member_id)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame(4, (int) $snap->legacy_unlocked_levels);

        Carbon::setTestNow();
    }

    public function test_as_of_capture_excludes_members_and_purchases_after_transition_date(): void
    {
        Carbon::setTestNow('2026-11-27 12:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $sponsor = $this->member('GFASOF1', 'ST666666');
        $sponsor->forceFill(['created_at' => '2026-11-01 08:00:00'])->save();

        $earlyDirect = $this->member('GFASOF1A', $sponsor->member_id);
        $earlyDirect->forceFill(['created_at' => '2026-11-10 08:00:00'])->save();
        $this->purchase($earlyDirect, $this->roiPackage('ROI-ASOF-A'), PackagePurchase::APPROVED, '100.0000', '2026-11-10 09:00:00');

        $lateDirect = $this->member('GFASOF1B', $sponsor->member_id);
        $lateDirect->forceFill(['created_at' => '2026-11-26 08:00:00'])->save();
        $this->purchase($lateDirect, $this->roiPackage('ROI-ASOF-B'), PackagePurchase::APPROVED, '100.0000', '2026-11-26 09:00:00');

        $version = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();

        app(LevelIncomeUnlockGrandfathering::class)->captureBaseline($version, $admin, '2026-11-20');
        $version->refresh();
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_AS_OF_TRANSITION, $version->grandfather_capture_mode);

        $snap = LevelIncomeUnlockGrandfatherSnapshot::query()
            ->where('level_income_rule_version_id', $version->id)
            ->where('member_id', $sponsor->member_id)
            ->firstOrFail();
        // Only the early direct existed/qualified as of 2026-11-20 → legacy 4, not 8.
        $this->assertSame(1, (int) $snap->income_eligible_direct_count);
        $this->assertSame(4, (int) $snap->legacy_unlocked_levels);

        Carbon::setTestNow();
    }

    public function test_cannot_recapture_after_authorization_and_preserves_audit_history(): void
    {
        $version = $this->prepareAuthorizedVersionWithCapture('2026-11-24');
        $admin = User::factory()->create(['is_admin' => true]);
        $auditCount = \App\Models\LevelIncomeRuleAudit::query()
            ->where('level_income_rule_version_id', $version->id)
            ->count();

        try {
            app(LevelIncomeUnlockGrandfathering::class)
                ->captureBaseline($version->fresh(), $admin, '2026-11-24');
            $this->fail('Expected ValidationException when recapturing after authorization');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grandfather', $e->errors());
        }

        $this->assertSame(
            $auditCount,
            \App\Models\LevelIncomeRuleAudit::query()
                ->where('level_income_rule_version_id', $version->id)
                ->count()
        );
        $this->assertTrue($version->fresh()->posting_authorized);
    }

    public function test_authorization_rejects_effective_date_on_or_before_processed_business_date(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        PackageIncomeRun::query()->create([
            'business_date' => '2026-11-26',
            'status' => PackageIncomeRun::SUCCEEDED,
            'credited_roi' => 0,
            'credited_commissions' => 0,
            'message' => 'test fixture',
        ]);

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
            'effective_from' => '2026-11-26',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-26');

        try {
            $service->authorizePosting($published->fresh(), $admin, true);
            $this->fail('Expected ValidationException for retroactive effective date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effective_from', $e->errors());
            $this->assertStringContainsString('last succeeded financial business date', $e->errors()['effective_from'][0]);
        }

        Carbon::setTestNow();
    }

    public function test_new_members_after_capture_require_ack_or_recapture_before_authorize(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);

        $this->member('GFDRIFT1', 'ST666666');

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');

        // Ensure created_at is after captured_at for freshness detection.
        Carbon::setTestNow('2026-11-27 11:00:00');
        $this->member('GFDRIFT2', 'ST666666');

        $freshness = $grandfathering->assessBaselineFreshness($published->fresh());
        $this->assertGreaterThan(0, $freshness['new_members_since_capture']);
        $this->assertTrue($freshness['requires_pre_cutover_ack']);
        $this->assertFalse($freshness['requires_recapture']);

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, true, false);
            $this->fail('Expected ValidationException requiring pre-cutover acknowledgment');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm_pre_cutover_baseline', $e->errors());
        }

        $authorized = $service->authorizePosting($published->fresh(), $admin, true, null, true, true);
        $this->assertTrue($authorized->posting_authorized);

        Carbon::setTestNow();
    }

    public function test_pre_cutover_baseline_requires_recapture_once_transition_date_arrives(): void
    {
        Carbon::setTestNow('2026-11-27 10:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');

        Carbon::setTestNow('2026-11-28 06:00:00');
        $freshness = $grandfathering->assessBaselineFreshness($published->fresh());
        $this->assertTrue($freshness['requires_recapture']);

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, true, true);
            $this->fail('Expected ValidationException requiring as-of recapture on transition day');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grandfather', $e->errors());
        }

        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');
        $this->assertSame(
            LevelIncomeUnlockGrandfathering::CAPTURE_MODE_AS_OF_TRANSITION,
            $published->fresh()->grandfather_capture_mode
        );
        $authorized = $service->authorizePosting($published->fresh(), $admin, true, null, true);
        $this->assertTrue($authorized->posting_authorized);

        Carbon::setTestNow();
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

        // As-of capture only includes members/purchases on or before the transition date.
        Member::query()->update(['created_at' => $transitionDate.' 08:00:00', 'updated_at' => $transitionDate.' 08:00:00']);
        PackagePurchase::query()->update([
            'submitted_at' => $transitionDate.' 09:00:00',
            'reviewed_at' => $transitionDate.' 09:00:00',
            'created_at' => $transitionDate.' 09:00:00',
            'updated_at' => $transitionDate.' 09:00:00',
        ]);

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
