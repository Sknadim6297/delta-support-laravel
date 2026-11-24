<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\User;
use App\Services\CommissionPlan;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\ReferralLevelEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LevelIncomeRuleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_legacy_published_is_not_posting_authorized(): void
    {
        $legacy = LevelIncomeRuleVersion::query()->where('version_code', 'legacy_live_v1')->first();
        $this->assertNotNull($legacy);
        $this->assertSame(LevelIncomeRuleVersion::STATUS_PUBLISHED, $legacy->status);
        $this->assertFalse($legacy->posting_authorized);

        $resolver = app(LevelIncomeRuleResolver::class);
        $this->assertNull($resolver->authorizedVersion());
        $this->assertSame(CommissionPlan::RATE_BY_LEVEL, $resolver->rateChart());
        $this->assertSame(0, $resolver->legacyLevelsForCount(0));
        $this->assertSame(4, app(ReferralLevelEligibility::class)->levelsForCount(1));
        $this->assertSame(0, app(ReferralLevelEligibility::class)->levelsForCount(0));
    }

    public function test_proposed_draft_activation_unlock_is_not_live(): void
    {
        $proposed = LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->first();
        $this->assertNotNull($proposed);
        $this->assertSame(LevelIncomeRuleVersion::STATUS_DRAFT, $proposed->status);

        $resolver = app(LevelIncomeRuleResolver::class);
        $bands = $proposed->bands->map(fn ($b) => [
            'start_level' => (int) $b->start_level,
            'end_level' => (int) $b->end_level,
            'min_qualifying_directs' => (int) $b->min_qualifying_directs,
            'requires_personal_activation' => (bool) $b->requires_personal_activation,
        ])->all();

        $this->assertSame(0, $resolver->simulateUnlockedLevels($bands, 0, false));
        $this->assertSame(4, $resolver->simulateUnlockedLevels($bands, 0, true));
        $this->assertSame(8, $resolver->simulateUnlockedLevels($bands, 1, true));
        $this->assertSame(12, $resolver->simulateUnlockedLevels($bands, 2, true));
        $this->assertSame(16, $resolver->simulateUnlockedLevels($bands, 3, true));
        $this->assertSame(20, $resolver->simulateUnlockedLevels($bands, 4, true));

        // Live eligibility still uses legacy until authorized.
        $this->assertSame(0, app(ReferralLevelEligibility::class)->levelsForCount(0));
    }

    public function test_invalid_bands_and_rate_totals_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);

        $this->assertNotEmpty($service->validateBands([
            ['start_level' => 1, 'end_level' => 10, 'min_qualifying_directs' => 0],
            ['start_level' => 8, 'end_level' => 20, 'min_qualifying_directs' => 1],
        ]));

        $this->assertNotEmpty($service->validateBands([
            ['start_level' => 1, 'end_level' => 10, 'min_qualifying_directs' => 1],
            ['start_level' => 11, 'end_level' => 20, 'min_qualifying_directs' => 0],
        ]));

        $badRates = CommissionPlan::RATE_BY_LEVEL;
        $badRates[1] = '30.0000';
        $this->assertNotEmpty($service->validateRates($badRates));

        try {
            $service->saveDraft([
                'label' => 'Bad draft',
                'bands' => [
                    ['start_level' => 1, 'end_level' => 20, 'min_qualifying_directs' => 0],
                ],
                'rates' => $badRates,
            ], $admin);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('configuration', $e->errors());
        }
    }

    public function test_publish_does_not_authorize_posting_and_authorize_requires_policy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $resolver = app(LevelIncomeRuleResolver::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );

        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED,
        ]);

        $this->assertSame(LevelIncomeRuleVersion::STATUS_PUBLISHED, $published->status);
        $this->assertFalse($published->posting_authorized);
        $this->assertNull($resolver->authorizedVersion());
        $this->assertSame(0, app(ReferralLevelEligibility::class)->levelsForCount(0));

        try {
            $service->authorizePosting($published, $admin, true);
            $this->fail('Expected ValidationException for unspecified applicability');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('applicability', $e->errors());
        }

        $published->forceFill([
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => now()->toDateString(),
        ])->save();

        $authorized = $service->authorizePosting($published->fresh(), $admin, true);
        $resolver->clearCache();
        $this->assertTrue($authorized->posting_authorized);
        $this->assertNotNull($resolver->authorizedVersion());
        $this->assertSame(4, $resolver->unlockedLevels(0, true));
        $this->assertSame(0, $resolver->unlockedLevels(0, false));

        // Display ladder may show authorized bands; live accrual remains purchase-scoped.
        $this->assertSame(4, app(ReferralLevelEligibility::class)->levelsForCount(0));

        $service->revokePostingAuthorization($authorized, $admin);
        $resolver->clearCache();
        $this->assertNull($resolver->authorizedVersion());
        $this->assertSame(0, app(ReferralLevelEligibility::class)->levelsForCount(0));
    }

    public function test_admin_commission_settings_tabs_render(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'unlock']))
            ->assertOk()
            ->assertSeeText('Level Unlock Rules')
            ->assertSeeText('Hard-coded legacy');

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'rates']))
            ->assertOk()
            ->assertSeeText('Commission percentages');

        $this->actingAs($admin)
            ->get(route('admin.finance.commission-settings', ['tab' => 'packages']))
            ->assertOk()
            ->assertSeeText('Package Commission Preview')
            ->assertSeeText('Active, 0 directs');

        $this->actingAs($admin)
            ->get(route('admin.finance.level-unlock-rules'))
            ->assertOk()
            ->assertSeeText('proposed_activation_unlock_v1');
    }

    public function test_impact_preview_is_read_only(): void
    {
        $preview = app(LevelIncomeRuleAdministration::class)->impactPreview(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->first()
        );

        $this->assertTrue($preview['live_uses_hardcoded']);
        $this->assertSame(
            'Historical ledger, wallet balances, and accrued Level Income rows are not recalculated.',
            $preview['historical_protection']
        );
        $activeZero = collect($preview['scenarios'])->firstWhere('label', 'Active, 0 directs');
        $this->assertSame(0, $activeZero['legacy_unlocked']);
        $this->assertSame(4, $activeZero['candidate_unlocked']);
    }
}
