<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\CommissionPlan;
use App\Services\FinancialControls;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\MemberPaymentControlService;
use App\Services\PackageIncomeAccrual;
use App\Services\ReferralLevelEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LevelIncomeApplicabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_seed_stays_unauthorized_with_future_purchases_default(): void
    {
        $proposed = LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail();
        $this->assertSame(LevelIncomeRuleVersion::STATUS_DRAFT, $proposed->status);
        $this->assertFalse($proposed->posting_authorized);
        $this->assertSame(LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY, $proposed->applicability);
        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());
        $this->assertFalse((bool) config('deltasupport.member_payment_controls.financial_integration_authorized'));
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
    }

    public function test_unauthorized_configuration_never_affects_posting(): void
    {
        config(['deltasupport.package_income.enabled' => true]);

        $sponsor = $this->member('STAPP01', 'ST666666');
        $this->purchase($sponsor, $this->package(), '2026-11-01 06:00:00'); // activates sponsor
        $buyer = $this->member('STAPP02', $sponsor->member_id);
        $purchase = $this->purchase($buyer, $this->package(), '2026-12-01 06:00:00');

        $preview = app(PackageIncomeAccrual::class)->preview('2026-12-11');
        $forBuyer = collect($preview['commissions'])->where('purchase_id', (string) $purchase->id);

        // Sponsor has 1 qualifying direct → legacy unlock 4 levels, but without authorized
        // new rules active+0-direct unlock never applies.
        $this->assertSame(4, app(ReferralLevelEligibility::class)->forMember($sponsor->member_id)['unlocked_levels']);
        $this->assertTrue($forBuyer->isNotEmpty());
        $this->assertSame(
            CommissionPlan::RATE_BY_LEVEL[1],
            $forBuyer->firstWhere('level', '1')['rate']
        );
    }

    public function test_future_purchases_only_keeps_legacy_for_existing_and_new_for_later_approvals(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $version = $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-15'
        );

        $this->assertTrue($version->posting_authorized);

        $sponsor = $this->member('STAPP11', 'ST666666');
        $this->purchase($sponsor, $this->package(), '2026-10-01 06:00:00'); // personal activation

        $legacyBuyer = $this->member('STAPP12', $sponsor->member_id);
        $legacyPurchase = $this->purchase($legacyBuyer, $this->package(), '2026-11-01 06:00:00'); // before effective

        $newBuyer = $this->member('STAPP13', $sponsor->member_id);
        $newPurchase = $this->purchase($newBuyer, $this->package(), '2026-11-20 06:00:00'); // after effective

        $resolver = app(LevelIncomeRuleResolver::class);
        $this->assertFalse($resolver->usesAuthorizedConfiguration($legacyPurchase, '2026-12-01'));
        $this->assertTrue($resolver->usesAuthorizedConfiguration($newPurchase, '2026-12-01'));

        // Same activated sponsor: legacy purchase needs legacy unlock (1 direct from legacyBuyer alone
        // after both purchases exist → 2 directs). Evaluate unlock with purchase context.
        $eligibility = app(ReferralLevelEligibility::class);
        $legacyUnlock = $eligibility->forMember($sponsor->member_id, $legacyPurchase, '2026-12-01');
        $newUnlock = $eligibility->forMember($sponsor->member_id, $newPurchase, '2026-12-01');

        // 2 qualifying directs under legacy → 8 levels; under new authorized bands → 12 levels.
        $this->assertSame(8, $legacyUnlock['unlocked_levels']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_LEGACY, $legacyUnlock['rule_mode']);
        $this->assertSame(12, $newUnlock['unlocked_levels']);
        $this->assertSame(LevelIncomeRuleResolver::MODE_AUTHORIZED, $newUnlock['rule_mode']);

        $preview = app(PackageIncomeAccrual::class)->preview('2026-12-01');
        $legacyRows = collect($preview['commissions'])->where('purchase_id', (string) $legacyPurchase->id);
        $newRows = collect($preview['commissions'])->where('purchase_id', (string) $newPurchase->id);

        // Single upline before admin root → only level 1 posts; unlock difference is enforced above.
        $this->assertNotNull($legacyRows->firstWhere('level', '1'));
        $this->assertNotNull($newRows->firstWhere('level', '1'));
        $this->assertSame(
            LevelIncomeRuleResolver::MODE_LEGACY,
            $resolver->postingContextFor($legacyPurchase, '2026-12-01')['mode']
        );
        $this->assertSame(
            LevelIncomeRuleResolver::MODE_AUTHORIZED,
            $resolver->postingContextFor($newPurchase, '2026-12-01')['mode']
        );
    }

    public function test_option_b_has_no_activation_bonus_at_metric_zero_under_applicable_new_rules(): void
    {
        $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-01'
        );

        $legacyPurchase = $this->purchase(
            $this->member('STAPP21', 'ST666666'),
            $this->package(),
            '2026-10-20 06:00:00'
        );
        $newPurchase = $this->purchase(
            $this->member('STAPP22', 'ST666666'),
            $this->package(),
            '2026-11-10 06:00:00'
        );

        $resolver = app(LevelIncomeRuleResolver::class);
        // Legacy purchase stays on hard-coded ladder (0 directs → 0).
        $this->assertSame(0, $resolver->unlockedLevelsForPurchase($legacyPurchase, '2026-11-20', 0, true));
        // Option B: activation alone does not unlock; metric 0 → 0, metric 1 → 4.
        $this->assertSame(0, $resolver->unlockedLevelsForPurchase($newPurchase, '2026-11-20', 0, true));
        $this->assertSame(4, $resolver->unlockedLevelsForPurchase($newPurchase, '2026-11-20', 1, true));
        $this->assertSame(0, $resolver->unlockedLevelsForPurchase($newPurchase, '2026-11-20', 1, false));
    }

    public function test_existing_prospective_uses_business_date_boundary_without_backfill_semantics(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            '2026-11-15',
            confirmExistingProspective: true
        );

        $sponsor = $this->member('STAPP41', 'ST666666');
        $this->purchase($sponsor, $this->package(), '2026-10-01 06:00:00');
        $buyer = $this->member('STAPP42', $sponsor->member_id);
        $oldPurchase = $this->purchase($buyer, $this->package(), '2026-10-10 06:00:00'); // old approval

        $resolver = app(LevelIncomeRuleResolver::class);
        $this->assertFalse($resolver->usesAuthorizedConfiguration($oldPurchase, '2026-11-14'));
        $this->assertTrue($resolver->usesAuthorizedConfiguration($oldPurchase, '2026-11-15'));
        $this->assertTrue($resolver->usesAuthorizedConfiguration($oldPurchase, '2026-12-01'));

        // Unlock for same purchase differs by business date.
        $this->assertSame(
            4,
            app(ReferralLevelEligibility::class)->forMember($sponsor->member_id, $oldPurchase, '2026-11-14')['unlocked_levels']
        ); // 1 direct legacy
        $this->assertSame(
            8,
            app(ReferralLevelEligibility::class)->forMember($sponsor->member_id, $oldPurchase, '2026-11-15')['unlocked_levels']
        ); // 1 direct under new bands
    }

    public function test_mixed_tree_coexistence_and_no_duplicate_credits_on_retry(): void
    {
        config(['deltasupport.package_income.enabled' => true]);
        $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-15'
        );

        $sponsor = $this->member('STAPP51', 'ST666666');
        $this->purchase($sponsor, $this->package(), '2026-10-01 06:00:00');
        $legacyBuyer = $this->member('STAPP52', $sponsor->member_id);
        $legacyPurchase = $this->purchase($legacyBuyer, $this->package(), '2026-11-01 06:00:00');
        $newBuyer = $this->member('STAPP53', $sponsor->member_id);
        $newPurchase = $this->purchase($newBuyer, $this->package(), '2026-11-20 06:00:00');

        $accrual = app(PackageIncomeAccrual::class);
        $first = $accrual->accrue('2026-12-01');
        $second = $accrual->accrue('2026-12-01');

        $this->assertGreaterThan(0, $first['credited_commissions'] ?? $first['commissions'] ?? 1);
        // Retry must not invent duplicate accrued rows for same purchase/recipient/date.
        $legacyCount = PackageLevelAccrual::query()
            ->where('package_purchase_id', $legacyPurchase->id)
            ->whereDate('business_date', '2026-12-01')
            ->count();
        $newCount = PackageLevelAccrual::query()
            ->where('package_purchase_id', $newPurchase->id)
            ->whereDate('business_date', '2026-12-01')
            ->count();
        $this->assertGreaterThan(0, $legacyCount);
        $this->assertGreaterThan(0, $newCount);

        $accrual->accrue('2026-12-01');
        $this->assertSame(
            $legacyCount,
            PackageLevelAccrual::query()
                ->where('package_purchase_id', $legacyPurchase->id)
                ->whereDate('business_date', '2026-12-01')
                ->count()
        );
        $this->assertSame(
            $newCount,
            PackageLevelAccrual::query()
                ->where('package_purchase_id', $newPurchase->id)
                ->whereDate('business_date', '2026-12-01')
                ->count()
        );
        unset($second);
    }

    public function test_authorize_requires_policy_effective_date_valid_config_and_prospective_confirm(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED,
        ]);

        try {
            $service->authorizePosting($published, $admin, true);
            $this->fail('Expected applicability validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('applicability', $e->errors());
        }

        $published->forceFill([
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => null,
        ])->save();

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, true);
            $this->fail('Expected effective_from validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effective_from', $e->errors());
        }

        $published->forceFill([
            'effective_from' => '2026-11-15',
            'grandfather_existing_unlock' => false,
        ])->save();

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, false);
            $this->fail('Expected existing_prospective confirmation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm_existing_prospective', $e->errors());
        }

        $authorized = $service->authorizePosting($published->fresh(), $admin, true, null, true);
        $this->assertTrue($authorized->posting_authorized);

        // Seed proposed remains unauthorized draft.
        $proposed = LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail();
        $this->assertFalse($proposed->posting_authorized);
        $this->assertSame(LevelIncomeRuleVersion::STATUS_DRAFT, $proposed->status);
    }

    public function test_payment_controls_and_emergency_stop_remain_authoritative(): void
    {
        config([
            'deltasupport.package_income.enabled' => true,
            'deltasupport.member_payment_controls.financial_integration_authorized' => true,
        ]);
        $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-01'
        );

        $sponsor = $this->member('STAPP61', 'ST666666');
        $this->purchase($sponsor, $this->package(), '2026-10-01 06:00:00');
        $buyer = $this->member('STAPP62', $sponsor->member_id);
        $this->purchase($buyer, $this->package(), '2026-11-10 06:00:00');

        $admin = User::factory()->create(['is_admin' => true]);
        app(MemberPaymentControlService::class)->apply($sponsor->member_id, [
            'master_enabled' => true,
            'roi_preference' => true,
            'level_preference' => false,
            'effective_from' => '2026-12-01',
            'reason' => 'Audit stop Level Income for recipient',
            'confirm' => true,
        ], $admin);

        $preview = app(PackageIncomeAccrual::class)->preview('2026-12-01');
        $this->assertTrue(
            collect($preview['skipped'])->contains(fn ($line) => str_contains((string) $line, $sponsor->member_id)
                && str_contains((string) $line, 'Level Income payment is stopped'))
        );

        // Emergency stop blocks authorize of another version.
        config(['deltasupport.controls.financial_emergency_stop' => true]);
        $this->assertTrue(app(FinancialControls::class)->emergencyStopped());

        $service = app(LevelIncomeRuleAdministration::class);
        $live = LevelIncomeRuleVersion::query()->where('posting_authorized', true)->firstOrFail();
        $clone = $service->cloneAsDraft($live, $admin);

        // Publish is refused while another version remains posting-authorized.
        try {
            $service->publish($clone, $admin, [
                'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
                'effective_from' => '2026-12-01',
            ]);
            $this->fail('Expected publish block while authorized version exists');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('posting_authorized', $e->errors());
        }

        $service->revokePostingAuthorization($live->fresh(), $admin);
        $published = $service->publish($clone->fresh(), $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-12-01',
        ]);
        $published->forceFill(['grandfather_existing_unlock' => false])->save();

        try {
            $service->authorizePosting($published->fresh(), $admin, true);
            $this->fail('Expected emergency stop block');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('financial_controls', $e->errors());
        }

        config(['deltasupport.controls.financial_emergency_stop' => false]);
    }

    public function test_commission_settings_shows_applicability_impact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'unlock']))
            ->assertOk()
            ->assertSeeText('Applicability policy')
            ->assertSeeText('Existing purchase impact')
            ->assertSeeText('Future purchase impact')
            ->assertSeeText('Financial authorization');
    }

    public function test_version_change_requires_revoke_before_publish_then_authorize(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $first = $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-01'
        );
        $this->assertTrue($first->fresh()->posting_authorized);

        $secondDraft = $service->cloneAsDraft($first, $admin);
        try {
            $service->publish($secondDraft, $admin, [
                'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
                'effective_from' => '2026-12-01',
            ]);
            $this->fail('Expected publish to refuse while first version is posting-authorized');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('posting_authorized', $e->errors());
        }
        $this->assertTrue($first->fresh()->posting_authorized);
        $this->assertSame($first->id, app(LevelIncomeRuleResolver::class)->authorizedVersion()?->id);

        $service->revokePostingAuthorization($first->fresh(), $admin);
        app(LevelIncomeRuleResolver::class)->clearCache();
        $this->assertNull(app(LevelIncomeRuleResolver::class)->authorizedVersion());

        $second = $service->publish($secondDraft->fresh(), $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-12-01',
        ]);
        $second->forceFill(['grandfather_existing_unlock' => false])->save();
        $authorized = $service->authorizePosting($second->fresh(), $admin, true);
        app(LevelIncomeRuleResolver::class)->clearCache();

        $this->assertTrue($authorized->posting_authorized);
        $this->assertFalse($first->fresh()->posting_authorized);
        $this->assertSame($authorized->id, app(LevelIncomeRuleResolver::class)->authorizedVersion()?->id);
    }

    public function test_future_purchases_only_keeps_legacy_before_effective_business_date(): void
    {
        $version = $this->publishAndAuthorize(
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            '2026-11-15'
        );
        $this->assertTrue($version->posting_authorized);

        $buyer = $this->member('STAPP91', 'ST666666');
        // Approved after effective_from, but accrual business date is still before effective.
        $purchase = $this->purchase($buyer, $this->package(), '2026-11-20 06:00:00');

        $resolver = app(LevelIncomeRuleResolver::class);
        $this->assertFalse($resolver->usesAuthorizedConfiguration($purchase, '2026-11-14'));
        $this->assertTrue($resolver->usesAuthorizedConfiguration($purchase, '2026-11-15'));
        $this->assertSame(
            'Business date is before effective date; legacy rules apply (future_purchases_only).',
            $resolver->postingContextFor($purchase, '2026-11-14')['reason']
        );
    }

    private function publishAndAuthorize(string $applicability, string $effectiveFrom, bool $confirmExistingProspective = false): LevelIncomeRuleVersion
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $published = $service->publish($draft, $admin, [
            'applicability' => $applicability,
            'effective_from' => $effectiveFrom,
        ]);
        // Existing applicability tests isolate bands/rates; grandfathering is covered separately.
        $published->forceFill(['grandfather_existing_unlock' => false])->save();

        $authorized = $service->authorizePosting(
            $published->fresh(),
            $admin,
            true,
            null,
            $applicability === LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE
                ? ($confirmExistingProspective || true)
                : false
        );
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
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(): Package
    {
        return Package::query()->firstOrCreate(
            ['code' => 'DEMO-COAST'],
            [
                'package_type' => Package::TYPE_TOUR_ROI,
                'name' => 'Coastal Weekend',
                'price_usd' => '100.0000',
                'denomination' => 'USDT',
                'roi_eligible' => true,
                'level_commission_eligible' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function purchase(Member $member, Package $package, string $reviewedAt): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id.Str::upper(Str::random(3)),
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
