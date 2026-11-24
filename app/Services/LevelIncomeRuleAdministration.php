<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\LevelIncomeCommissionRate;
use App\Models\LevelIncomeRuleAudit;
use App\Models\LevelIncomeRuleVersion;
use App\Models\LevelIncomeUnlockBand;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft / validate / publish Level Income unlock bands and commission percentages.
 * Never credits wallets. Never sets posting_authorized without explicit admin action.
 */
class LevelIncomeRuleAdministration
{
    public function __construct(
        private readonly LevelIncomeRuleResolver $resolver,
        private readonly PackageIncomePolicy $packagePolicy,
    ) {
    }

    /**
     * @param  array{
     *   version_code?: string,
     *   label: string,
     *   notes?: ?string,
     *   change_note?: ?string,
     *   direct_qualification_basis?: string,
     *   tour_only_qualifies_for_unlock?: bool,
     *   bands: list<array{start_level:int,end_level:int,min_qualifying_directs:int,requires_personal_activation?:bool,sort_order?:int}>,
     *   rates: array<int, string|float|int>
     * }  $payload
     */
    public function saveDraft(array $payload, User $admin, ?string $ip = null, ?LevelIncomeRuleVersion $existing = null): LevelIncomeRuleVersion
    {
        $bands = array_values($payload['bands'] ?? []);
        $rates = $this->normalizeRates($payload['rates'] ?? []);
        $basis = app(LevelIncomeDirectQualification::class)->normalizeBasis(
            $payload['direct_qualification_basis'] ?? LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS
        );
        $tourOnlyUnlock = (bool) ($payload['tour_only_qualifies_for_unlock'] ?? false);
        $grandfather = (bool) ($payload['grandfather_existing_unlock'] ?? false);
        $qualification = app(LevelIncomeDirectQualification::class);
        $combined = [
            'combined_joining_enabled' => (bool) ($payload['combined_joining_enabled'] ?? false),
            'combined_purchase_enabled' => (bool) ($payload['combined_purchase_enabled'] ?? false),
            'combined_purchase_scope' => $qualification->normalizePurchaseScope($payload['combined_purchase_scope'] ?? null),
            'combined_repeat_purchase_mode' => $qualification->normalizeRepeatMode($payload['combined_repeat_purchase_mode'] ?? null),
            'combined_double_credit_policy' => $qualification->normalizeDoubleCreditPolicy($payload['combined_double_credit_policy'] ?? null),
            'combined_joining_unit_value' => max(0, min(100, (int) ($payload['combined_joining_unit_value'] ?? 1))),
            'combined_purchase_unit_value' => max(0, min(100, (int) ($payload['combined_purchase_unit_value'] ?? 1))),
        ];
        if ($basis === LevelIncomeDirectQualification::BASIS_COMBINED_JOINING_AND_PURCHASES
            && ! $combined['combined_joining_enabled']
            && ! $combined['combined_purchase_enabled']) {
            throw ValidationException::withMessages([
                'combined' => 'Combined qualification requires at least one of joining or purchase contributions enabled.',
            ]);
        }
        $errors = array_merge(
            $this->validateBands($bands),
            $this->validateRates($rates)
        );
        if ($errors !== []) {
            throw ValidationException::withMessages(['configuration' => $errors]);
        }

        return DB::transaction(function () use ($payload, $admin, $ip, $existing, $bands, $rates, $basis, $tourOnlyUnlock, $grandfather, $combined): LevelIncomeRuleVersion {
            if ($existing !== null) {
                $version = LevelIncomeRuleVersion::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                if (! $version->isDraft()) {
                    throw ValidationException::withMessages([
                        'status' => 'Only draft versions can be edited. Clone to create a new draft.',
                    ]);
                }
                $before = $this->snapshot($version);
            } else {
                $code = trim((string) ($payload['version_code'] ?? ''));
                if ($code === '') {
                    $code = 'draft_'.now()->format('Ymd_His');
                }
                if (LevelIncomeRuleVersion::query()->where('version_code', $code)->exists()) {
                    throw ValidationException::withMessages(['version_code' => 'Version code already exists.']);
                }
                $version = LevelIncomeRuleVersion::query()->create([
                    'version_code' => $code,
                    'label' => (string) $payload['label'],
                    'status' => LevelIncomeRuleVersion::STATUS_DRAFT,
                    'posting_authorized' => false,
                    'applicability' => LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED,
                    'direct_qualification_basis' => $basis,
                    'tour_only_qualifies_for_unlock' => $tourOnlyUnlock,
                    'grandfather_existing_unlock' => $grandfather,
                    ...$combined,
                    'effective_from' => null,
                    'notes' => $payload['notes'] ?? null,
                    'change_note' => $payload['change_note'] ?? null,
                    'created_by' => $admin->id,
                ]);
                $before = null;
            }

            $version->fill([
                'label' => (string) $payload['label'],
                'notes' => $payload['notes'] ?? $version->notes,
                'change_note' => $payload['change_note'] ?? $version->change_note,
                'direct_qualification_basis' => $basis,
                'tour_only_qualifies_for_unlock' => $tourOnlyUnlock,
                'grandfather_existing_unlock' => $grandfather,
                ...$combined,
            ])->save();

            LevelIncomeUnlockBand::query()->where('level_income_rule_version_id', $version->id)->delete();
            LevelIncomeCommissionRate::query()->where('level_income_rule_version_id', $version->id)->delete();

            foreach ($bands as $index => $band) {
                LevelIncomeUnlockBand::query()->create([
                    'level_income_rule_version_id' => $version->id,
                    'sort_order' => (int) ($band['sort_order'] ?? ($index + 1)),
                    'start_level' => (int) $band['start_level'],
                    'end_level' => (int) $band['end_level'],
                    'min_qualifying_directs' => (int) $band['min_qualifying_directs'],
                    'requires_personal_activation' => (bool) ($band['requires_personal_activation'] ?? true),
                ]);
            }

            foreach ($rates as $level => $percentage) {
                LevelIncomeCommissionRate::query()->create([
                    'level_income_rule_version_id' => $version->id,
                    'level' => (int) $level,
                    'percentage' => $percentage,
                ]);
            }

            $version = $version->fresh(['bands', 'rates']);
            $this->audit($version, 'draft_saved', $admin, $before, $this->snapshot($version), $ip);
            $this->activity($admin, 'level_income_draft_saved', $version->version_code);

            return $version;
        });
    }

    public function cloneAsDraft(LevelIncomeRuleVersion $source, User $admin, ?string $ip = null, ?string $label = null): LevelIncomeRuleVersion
    {
        $source->loadMissing(['bands', 'rates']);

        return $this->saveDraft([
            'version_code' => 'draft_from_'.$source->version_code.'_'.now()->format('His'),
            'label' => $label ?: ('Draft from '.$source->label),
            'notes' => $source->notes,
            'change_note' => 'Cloned from '.$source->version_code,
            'direct_qualification_basis' => $source->direct_qualification_basis
                ?? LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
            'tour_only_qualifies_for_unlock' => (bool) ($source->tour_only_qualifies_for_unlock ?? false),
            'grandfather_existing_unlock' => (bool) ($source->grandfather_existing_unlock ?? false),
            'combined_joining_enabled' => (bool) ($source->combined_joining_enabled ?? false),
            'combined_purchase_enabled' => (bool) ($source->combined_purchase_enabled ?? false),
            'combined_purchase_scope' => $source->combined_purchase_scope ?? LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => $source->combined_repeat_purchase_mode ?? LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => $source->combined_double_credit_policy ?? LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => (int) ($source->combined_joining_unit_value ?? 1),
            'combined_purchase_unit_value' => (int) ($source->combined_purchase_unit_value ?? 1),
            'bands' => $source->bands->map(fn (LevelIncomeUnlockBand $b): array => [
                'sort_order' => $b->sort_order,
                'start_level' => $b->start_level,
                'end_level' => $b->end_level,
                'min_qualifying_directs' => $b->min_qualifying_directs,
                'requires_personal_activation' => $b->requires_personal_activation,
            ])->all(),
            'rates' => $source->rates->mapWithKeys(
                fn (LevelIncomeCommissionRate $r): array => [(int) $r->level => (string) $r->percentage]
            )->all(),
        ], $admin, $ip);
    }

    /**
     * Publish a validated draft. Does NOT authorize financial posting.
     *
     * @param  array{applicability?: string, effective_from?: ?string, change_note?: ?string}  $options
     */
    public function publish(LevelIncomeRuleVersion $version, User $admin, array $options = [], ?string $ip = null): LevelIncomeRuleVersion
    {
        return DB::transaction(function () use ($version, $admin, $options, $ip): LevelIncomeRuleVersion {
            $locked = LevelIncomeRuleVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Only drafts can be published.']);
            }

            $locked->load(['bands', 'rates']);
            $errors = array_merge(
                $this->validateBands($locked->bands->map(fn ($b) => $b->toArray())->all()),
                $this->validateRates(
                    $locked->rates->mapWithKeys(fn ($r) => [(int) $r->level => (string) $r->percentage])->all()
                )
            );
            if ($errors !== []) {
                throw ValidationException::withMessages(['configuration' => $errors]);
            }

            $applicability = (string) ($options['applicability'] ?? LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED);
            if (! in_array($applicability, [
                LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED,
                LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
                LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            ], true)) {
                throw ValidationException::withMessages(['applicability' => 'Invalid applicability policy.']);
            }

            $before = $this->snapshot($locked);

            LevelIncomeRuleVersion::query()
                ->where('status', LevelIncomeRuleVersion::STATUS_PUBLISHED)
                ->where('id', '!=', $locked->id)
                ->update(['status' => LevelIncomeRuleVersion::STATUS_SUPERSEDED]);

            $locked->fill([
                'status' => LevelIncomeRuleVersion::STATUS_PUBLISHED,
                'posting_authorized' => false,
                'applicability' => $applicability,
                'effective_from' => $options['effective_from'] ?? null,
                'change_note' => $options['change_note'] ?? $locked->change_note,
                'published_by' => $admin->id,
                'published_at' => now(),
            ])->save();

            $this->resolver->clearCache();
            $fresh = $locked->fresh(['bands', 'rates']);
            $this->audit($fresh, 'published', $admin, $before, $this->snapshot($fresh), $ip);
            $this->activity($admin, 'level_income_published', $fresh->version_code);

            return $fresh;
        });
    }

    /**
     * Explicitly authorize a published version for financial:daily posting.
     * Requires valid applicability, effective date, valid bands/rates, and confirmation.
     * existing_prospective additionally requires confirm_existing_prospective.
     */
    public function authorizePosting(
        LevelIncomeRuleVersion $version,
        User $admin,
        bool $confirm,
        ?string $ip = null,
        bool $confirmExistingProspective = false
    ): LevelIncomeRuleVersion {
        if (! $confirm) {
            throw ValidationException::withMessages([
                'confirm' => 'Explicit confirmation is required to authorize Level Income posting rules.',
            ]);
        }

        if (app(FinancialControls::class)->emergencyStopped()) {
            throw ValidationException::withMessages([
                'financial_controls' => 'Cannot authorize Level Income posting while the global emergency stop is active.',
            ]);
        }

        return DB::transaction(function () use ($version, $admin, $ip, $confirmExistingProspective): LevelIncomeRuleVersion {
            $locked = LevelIncomeRuleVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $locked->load(['bands', 'rates']);

            if (! $locked->isPublished()) {
                throw ValidationException::withMessages(['status' => 'Only published versions can be authorized for posting.']);
            }
            if (! in_array($locked->applicability, [
                LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
                LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            ], true)) {
                throw ValidationException::withMessages([
                    'applicability' => 'Choose future_purchases_only or existing_prospective before authorizing posting.',
                ]);
            }
            if ($locked->effective_from === null) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Set an effective date before authorizing posting.',
                ]);
            }

            $configErrors = array_merge(
                $this->validateBands($locked->bands->map(fn ($b) => $b->toArray())->all()),
                $this->validateRates(
                    $locked->rates->mapWithKeys(fn ($r) => [(int) $r->level => (string) $r->percentage])->all()
                )
            );
            if ($configErrors !== []) {
                throw ValidationException::withMessages(['configuration' => $configErrors]);
            }

            if ($locked->applicability === LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE
                && ! $confirmExistingProspective) {
                throw ValidationException::withMessages([
                    'confirm_existing_prospective' => 'existing_prospective requires a separate confirmation after reviewing the impact preview. It applies new rules from the effective business date for all source purchases without historical backfill.',
                ]);
            }

            app(LevelIncomeUnlockGrandfathering::class)->assertReadyForAuthorization($locked);

            $before = $this->snapshot($locked);

            LevelIncomeRuleVersion::query()
                ->where('posting_authorized', true)
                ->where('id', '!=', $locked->id)
                ->update(['posting_authorized' => false]);

            $locked->forceFill(['posting_authorized' => true])->save();
            $this->resolver->clearCache();

            $fresh = $locked->fresh(['bands', 'rates']);
            $this->audit($fresh, 'posting_authorized', $admin, $before, $this->snapshot($fresh), $ip);
            $this->activity($admin, 'level_income_posting_authorized', $fresh->version_code);

            return $fresh;
        });
    }

    public function revokePostingAuthorization(LevelIncomeRuleVersion $version, User $admin, ?string $ip = null): LevelIncomeRuleVersion
    {
        return DB::transaction(function () use ($version, $admin, $ip): LevelIncomeRuleVersion {
            $locked = LevelIncomeRuleVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->forceFill(['posting_authorized' => false])->save();
            $this->resolver->clearCache();
            $fresh = $locked->fresh(['bands', 'rates']);
            $this->audit($fresh, 'posting_authorization_revoked', $admin, $before, $this->snapshot($fresh), $ip);
            $this->activity($admin, 'level_income_posting_revoked', $fresh->version_code);

            return $fresh;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     * @return list<string>
     */
    public function validateBands(array $bands): array
    {
        $errors = [];
        if ($bands === []) {
            return ['At least one unlock band is required.'];
        }

        $normalized = [];
        foreach ($bands as $index => $band) {
            $start = (int) ($band['start_level'] ?? 0);
            $end = (int) ($band['end_level'] ?? 0);
            $directs = (int) ($band['min_qualifying_directs'] ?? -1);
            if ($start < 1 || $end < 1 || $start > ReferralLevelEligibility::MAX_LEVELS || $end > ReferralLevelEligibility::MAX_LEVELS) {
                $errors[] = "Band #".($index + 1)." levels must be between 1 and ".ReferralLevelEligibility::MAX_LEVELS.".";
            }
            if ($start > $end) {
                $errors[] = "Band #".($index + 1)." start_level cannot exceed end_level.";
            }
            if ($directs < 0) {
                $errors[] = "Band #".($index + 1)." direct requirement cannot be negative.";
            }
            $normalized[] = [
                'start' => $start,
                'end' => $end,
                'directs' => $directs,
            ];
        }

        usort($normalized, fn ($a, $b) => $a['start'] <=> $b['start']);

        $expected = 1;
        $prevDirects = -1;
        foreach ($normalized as $i => $band) {
            if ($band['start'] !== $expected) {
                $errors[] = $expected === 1
                    ? 'Unlock bands must begin at level 1.'
                    : "Missing levels before band starting at {$band['start']} (expected {$expected}).";
            }
            if ($band['directs'] < $prevDirects) {
                $errors[] = 'Direct requirements must be logically progressive (non-decreasing by level range).';
            }
            $prevDirects = $band['directs'];
            $expected = $band['end'] + 1;

            for ($j = $i + 1; $j < count($normalized); $j++) {
                $other = $normalized[$j];
                if ($band['start'] <= $other['end'] && $other['start'] <= $band['end']) {
                    $errors[] = "Unlock bands overlap between levels {$band['start']}-{$band['end']} and {$other['start']}-{$other['end']}.";
                }
            }
        }

        if ($expected - 1 !== ReferralLevelEligibility::MAX_LEVELS) {
            $errors[] = 'Unlock bands must cover levels 1–'.ReferralLevelEligibility::MAX_LEVELS.' without gaps.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<int, string>  $rates
     * @return list<string>
     */
    public function validateRates(array $rates): array
    {
        $errors = [];
        $sum = '0.0000';
        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            if (! array_key_exists($level, $rates)) {
                $errors[] = "Missing commission percentage for level {$level}.";
                continue;
            }
            $value = (string) $rates[$level];
            if (! is_numeric($value) || bccomp($value, '0', 4) < 0) {
                $errors[] = "Level {$level} percentage cannot be negative.";
                continue;
            }
            $sum = bcadd($sum, bcadd($value, '0', 4), 4);
        }

        if (count($rates) !== ReferralLevelEligibility::MAX_LEVELS) {
            $errors[] = 'Exactly '.ReferralLevelEligibility::MAX_LEVELS.' level percentages are required.';
        }

        if (bccomp($sum, '100.0000', 4) !== 0) {
            $errors[] = "Total commission allocation must equal 100.0000% (currently {$sum}%).";
        }

        return array_values(array_unique($errors));
    }

    /**
     * Read-only impact preview — never mutates financial state.
     *
     * @return array<string, mixed>
     */
    public function impactPreview(?LevelIncomeRuleVersion $candidate = null): array
    {
        $candidate = $candidate?->loadMissing(['bands', 'rates']) ?? $this->resolver->publishedBaseline();
        $liveRates = CommissionPlan::RATE_BY_LEVEL;
        $candidateRates = [];
        if ($candidate !== null) {
            foreach ($candidate->rates as $row) {
                $candidateRates[(int) $row->level] = bcadd((string) $row->percentage, '0', 4);
            }
        }

        $scenarios = [];
        $bands = $candidate?->bands->map(fn ($b) => [
            'start_level' => (int) $b->start_level,
            'end_level' => (int) $b->end_level,
            'min_qualifying_directs' => (int) $b->min_qualifying_directs,
            'requires_personal_activation' => (bool) $b->requires_personal_activation,
        ])->all() ?? [];

        foreach ([
            ['label' => 'Inactive, 0 directs', 'activated' => false, 'directs' => 0],
            ['label' => 'Active, 0 directs', 'activated' => true, 'directs' => 0],
            ['label' => 'Active, 1 direct', 'activated' => true, 'directs' => 1],
            ['label' => 'Active, 2 directs', 'activated' => true, 'directs' => 2],
            ['label' => 'Active, 3 directs', 'activated' => true, 'directs' => 3],
            ['label' => 'Active, 4 directs', 'activated' => true, 'directs' => 4],
            ['label' => 'Active, 5+ directs', 'activated' => true, 'directs' => 5],
        ] as $row) {
            $scenarios[] = [
                'label' => $row['label'],
                'legacy_unlocked' => $this->resolver->legacyLevelsForCount($row['directs']),
                'candidate_unlocked' => $bands === []
                    ? $this->resolver->legacyLevelsForCount($row['directs'])
                    : $this->resolver->simulateUnlockedLevels($bands, $row['directs'], $row['activated']),
            ];
        }

        $packagePreviews = [];
        foreach (Package::query()->where('level_commission_eligible', true)->orderBy('sort_order')->get() as $package) {
            $definition = $this->packagePolicy->definition((string) $package->code);
            if ($definition === null || ($definition['commission_pool'] ?? null) === null) {
                continue;
            }
            $pool = (string) $definition['commission_pool'];
            $levels = [];
            $sum = '0.0000';
            $chart = $candidateRates !== [] ? $candidateRates : $liveRates;
            foreach ($chart as $level => $rate) {
                $amount = bcdiv(bcmul($pool, $rate, 4), '100', 4);
                $levels[] = ['level' => $level, 'rate' => $rate, 'amount' => $amount];
                $sum = bcadd($sum, $amount, 4);
            }
            $packagePreviews[] = [
                'code' => $package->code,
                'name' => $package->name,
                'pool' => $pool,
                'days' => $definition['commission_days'] ?? null,
                'source' => $definition['source'] ?? null,
                'levels' => $levels,
                'daily_sum' => $sum,
            ];
        }

        $approvedWithIncome = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->whereNotNull('reviewed_at')
            ->count();

        $applicability = (string) ($candidate?->applicability ?? LevelIncomeRuleVersion::APPLICABILITY_UNSPECIFIED);
        $effectiveFrom = optional($candidate?->effective_from)?->toDateString();
        $existingImpact = match ($applicability) {
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY => 'Existing approved purchases (reviewed before effective date) keep hard-coded legacy unlock bands and commission rates.',
            LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE => 'From the effective business date onward, authorized unlock/rates apply to commissions from existing and new purchases. Dates before effective keep legacy. No historical backfill.',
            default => 'Applicability unspecified — posting cannot be authorized; live accrual stays on hard-coded legacy rules.',
        };
        $futureImpact = match ($applicability) {
            LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY => 'Purchases approved on/after the effective date use this configuration’s unlock bands and rates once posting is authorized.',
            LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE => 'Future purchases also use authorized rules for business dates on/after the effective date.',
            default => 'Choose future_purchases_only (recommended default) or existing_prospective before publish/authorize.',
        };

        $qualification = app(LevelIncomeDirectQualification::class);
        $qualBasis = $qualification->normalizeBasis($candidate?->direct_qualification_basis ?? null);
        $tourUnlock = (bool) ($candidate?->tour_only_qualifies_for_unlock ?? false);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);
        $grandfatherSummary = $grandfathering->versionSummary($candidate);

        $memberImpactSample = [];
        if ($candidate !== null) {
            $sampleIds = \App\Models\Member::query()->orderBy('id')->limit(40)->pluck('member_id')->all();
            $analyzer = app(LevelIncomeUnlockImpactAnalyzer::class);
            foreach ($sampleIds as $memberId) {
                $row = $analyzer->analyzeMember($memberId, $candidate);
                if ($row['live_unlocked_levels'] === 0
                    && $row['proposed_unlocked_levels'] === 0
                    && $row['protected_unlocked_levels'] === 0) {
                    continue;
                }
                $memberImpactSample[] = [
                    'member_id' => $row['member_id'],
                    'current_legacy' => $row['live_unlocked_levels'],
                    'proposed' => $row['proposed_unlocked_levels'],
                    'protected' => $row['protected_unlocked_levels'],
                    'impact' => $row['impact'],
                    'reason' => $row['delta_reason'],
                ];
                if (count($memberImpactSample) >= 15) {
                    break;
                }
            }
        }

        return [
            'candidate' => $candidate,
            'posting_authorized' => (bool) ($candidate?->posting_authorized),
            'live_uses_hardcoded' => $this->resolver->authorizedVersion() === null,
            'scenarios' => $scenarios,
            'package_previews' => $packagePreviews,
            'approved_purchase_count' => $approvedWithIncome,
            'historical_protection' => 'Historical ledger, wallet balances, and accrued Level Income rows are not recalculated.',
            'rate_delta_levels' => $this->rateDeltas($liveRates, $candidateRates !== [] ? $candidateRates : $liveRates),
            'applicability' => $applicability,
            'effective_from' => $effectiveFrom,
            'version_code' => $candidate?->version_code,
            'publication_status' => $candidate?->status,
            'authorization_status' => ($candidate?->posting_authorized ?? false) ? 'authorized' : 'unauthorized',
            'existing_purchase_impact' => $existingImpact,
            'future_purchase_impact' => $futureImpact,
            'recommended_default_policy' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'direct_qualification_basis' => $qualBasis,
            'direct_qualification_basis_label' => LevelIncomeDirectQualification::labels()[$qualBasis] ?? $qualBasis,
            'tour_only_qualifies_for_unlock' => $tourUnlock,
            'qualification_note' => 'Single bases remain available. Combined joining+purchase is a separate basis with its own contribution, scope, repeat, and double-credit settings. Tour Only unlock flag does not enable ROI/Level Income generation. Live accrual ignores draft settings until posting_authorized.',
            'combined_joining_enabled' => (bool) ($candidate?->combined_joining_enabled ?? false),
            'combined_purchase_enabled' => (bool) ($candidate?->combined_purchase_enabled ?? false),
            'combined_purchase_scope' => $candidate?->combined_purchase_scope,
            'combined_repeat_purchase_mode' => $candidate?->combined_repeat_purchase_mode,
            'combined_double_credit_policy' => $candidate?->combined_double_credit_policy,
            'combined_joining_unit_value' => (int) ($candidate?->combined_joining_unit_value ?? 1),
            'combined_purchase_unit_value' => (int) ($candidate?->combined_purchase_unit_value ?? 1),
            'combined_requires_business_approval' => true,
            'grandfather_existing_unlock' => (bool) ($candidate?->grandfather_existing_unlock ?? false),
            'grandfather_summary' => $grandfatherSummary,
            'grandfather_formula' => $grandfatherSummary['formula'] ?? null,
            'member_unlock_impact_sample' => $memberImpactSample,
            'grandfather_note' => 'protected = max(proposed, min(snapshot_legacy, current_legacy)). Capture baseline at transition date before authorize when enabled. Does not backfill history or bypass payment controls.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(LevelIncomeRuleVersion $version): array
    {
        $version->loadMissing(['bands', 'rates']);

        return [
            'id' => $version->id,
            'version_code' => $version->version_code,
            'label' => $version->label,
            'status' => $version->status,
            'posting_authorized' => $version->posting_authorized,
            'applicability' => $version->applicability,
            'direct_qualification_basis' => $version->direct_qualification_basis
                ?? LevelIncomeDirectQualification::BASIS_INCOME_ELIGIBLE_DIRECTS,
            'tour_only_qualifies_for_unlock' => (bool) ($version->tour_only_qualifies_for_unlock ?? false),
            'combined_joining_enabled' => (bool) ($version->combined_joining_enabled ?? false),
            'combined_purchase_enabled' => (bool) ($version->combined_purchase_enabled ?? false),
            'combined_purchase_scope' => $version->combined_purchase_scope,
            'combined_repeat_purchase_mode' => $version->combined_repeat_purchase_mode,
            'combined_double_credit_policy' => $version->combined_double_credit_policy,
            'combined_joining_unit_value' => (int) ($version->combined_joining_unit_value ?? 1),
            'combined_purchase_unit_value' => (int) ($version->combined_purchase_unit_value ?? 1),
            'grandfather_existing_unlock' => (bool) ($version->grandfather_existing_unlock ?? false),
            'grandfather_transition_date' => optional($version->grandfather_transition_date)?->toDateString(),
            'grandfather_captured_at' => optional($version->grandfather_captured_at)?->toIso8601String(),
            'effective_from' => optional($version->effective_from)?->toDateString(),
            'bands' => $version->bands->map(fn ($b) => [
                'start_level' => $b->start_level,
                'end_level' => $b->end_level,
                'min_qualifying_directs' => $b->min_qualifying_directs,
                'requires_personal_activation' => $b->requires_personal_activation,
            ])->all(),
            'rates' => $version->rates->mapWithKeys(
                fn ($r) => [(int) $r->level => (string) $r->percentage]
            )->all(),
        ];
    }

    /**
     * @param  array<int, string|float|int>  $rates
     * @return array<int, string>
     */
    private function normalizeRates(array $rates): array
    {
        $normalized = [];
        foreach ($rates as $level => $value) {
            $normalized[(int) $level] = bcadd((string) $value, '0', 4);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, string>  $live
     * @param  array<int, string>  $candidate
     * @return list<array{level:int,live:string,candidate:string}>
     */
    private function rateDeltas(array $live, array $candidate): array
    {
        $deltas = [];
        for ($level = 1; $level <= ReferralLevelEligibility::MAX_LEVELS; $level++) {
            $a = $live[$level] ?? '0.0000';
            $b = $candidate[$level] ?? '0.0000';
            if (bccomp($a, $b, 4) !== 0) {
                $deltas[] = ['level' => $level, 'live' => $a, 'candidate' => $b];
            }
        }

        return $deltas;
    }

    private function audit(
        LevelIncomeRuleVersion $version,
        string $action,
        User $admin,
        ?array $before,
        ?array $after,
        ?string $ip
    ): void {
        LevelIncomeRuleAudit::query()->create([
            'level_income_rule_version_id' => $version->id,
            'action' => $action,
            'actor_id' => $admin->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $ip,
        ]);
    }

    private function activity(User $admin, string $action, string $subject): void
    {
        try {
            AdminActivityLog::query()->create([
                'user_id' => $admin->id,
                'action' => $action,
                'subject' => $subject,
                'format' => 'json',
                'scope' => 'level_income_rules',
                'filters' => ['version_code' => $subject],
                'row_count' => 1,
            ]);
        } catch (\Throwable) {
            // Activity log must never block rule administration.
        }
    }
}
