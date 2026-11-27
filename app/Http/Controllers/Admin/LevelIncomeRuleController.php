<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LevelIncomeRuleAudit;
use App\Models\LevelIncomeRuleVersion;
use App\Services\AssociateCommissionCalculator;
use App\Services\AssociateCommissionStructure;
use App\Services\CommissionPlan;
use App\Services\LevelIncomeDirectQualification;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Services\PackageIncomePolicy;
use App\Services\ReferralLevelEligibility;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LevelIncomeRuleController extends Controller
{
    public function settings(
        Request $request,
        CommissionPlan $plan,
        PackageIncomePolicy $policy,
        AssociateCommissionStructure $associate,
        ReferralLevelEligibility $eligibility,
        LevelIncomeRuleAdministration $admin,
        LevelIncomeRuleResolver $resolver,
    ): View {
        $tab = (string) $request->query('tab', 'unlock');
        if (! in_array($tab, ['unlock', 'rates', 'packages', 'history'], true)) {
            $tab = 'unlock';
        }

        $versions = LevelIncomeRuleVersion::query()
            ->with(['bands', 'rates', 'creator', 'publisher'])
            ->orderByDesc('id')
            ->get();

        $draft = $versions->firstWhere('status', LevelIncomeRuleVersion::STATUS_DRAFT)
            ?? $versions->firstWhere('version_code', 'proposed_activation_unlock_v1');

        $published = $resolver->publishedBaseline();
        $authorized = $resolver->authorizedVersion();
        $editing = null;
        if ($request->filled('version')) {
            $editing = $versions->firstWhere('id', (int) $request->query('version'));
        }
        $editing ??= $draft ?? $published;

        $preview = $admin->impactPreview($editing);
        $audits = LevelIncomeRuleAudit::query()
            ->with(['actor', 'version'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $storedLevels = $plan->levels();
        $storedTotal = '0.0000';
        foreach ($storedLevels as $level) {
            $storedTotal = bcadd($storedTotal, (string) $level->percentage, 4);
        }

        $editRates = [];
        $editTotal = '0.0000';
        if ($editing !== null) {
            foreach ($editing->rates as $rate) {
                $editRates[(int) $rate->level] = bcadd((string) $rate->percentage, '0', 4);
                $editTotal = bcadd($editTotal, (string) $rate->percentage, 4);
            }
        }
        if ($editRates === []) {
            $editRates = CommissionPlan::RATE_BY_LEVEL;
            $editTotal = '100.0000';
        }

        $bandValidation = $editing
            ? $admin->validateBands($editing->bands->map(fn ($b) => $b->toArray())->all())
            : [];
        $rateValidation = $admin->validateRates($editRates);

        $unlockLadder = [];
        foreach ([0, 1, 2, 3, 4, 5] as $count) {
            $unlockLadder[] = [
                'directs' => $count === 5 ? '5 or more' : (string) $count,
                'legacy' => $resolver->legacyLevelsForCount($count),
                'live' => $eligibility->levelsForCount($count, true),
                'live_inactive' => $eligibility->levelsForCount($count, false),
                'draft_active' => $editing
                    ? $resolver->simulateUnlockedLevels(
                        $editing->bands->map(fn ($b) => [
                            'start_level' => (int) $b->start_level,
                            'end_level' => (int) $b->end_level,
                            'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                            'requires_personal_activation' => (bool) $b->requires_personal_activation,
                        ])->all(),
                        $count,
                        true
                    )
                    : null,
            ];
        }

        return view('admin.finance.commission-settings', [
            'tab' => $tab,
            'versions' => $versions,
            'editing' => $editing,
            'published' => $published,
            'authorized' => $authorized,
            'preview' => $preview,
            'audits' => $audits,
            'unlockLadder' => $unlockLadder,
            'editRates' => $editRates,
            'editTotal' => $editTotal,
            'bandValidation' => $bandValidation,
            'rateValidation' => $rateValidation,
            'levels' => $storedLevels,
            'total' => $storedTotal,
            'active' => $plan->isActive(),
            'integrityErrors' => $plan->integrityErrors(),
            'associate' => $associate->preview(),
            'associateEngine' => [
                'name' => AssociateCommissionCalculator::ENGINE,
                'mode' => 'dry_run_only',
                'posting_enabled' => $associate->postingEnabled(),
                'snapshot_on_approval' => $associate->snapshotOnApprovalEnabled(),
                'mutates' => false,
            ],
            'liveUsesHardcoded' => $authorized === null,
            'maxLevels' => ReferralLevelEligibility::MAX_LEVELS,
            'legacyRates' => CommissionPlan::RATE_BY_LEVEL,
            'qualificationBases' => LevelIncomeDirectQualification::labels(),
            'purchaseScopeLabels' => LevelIncomeDirectQualification::purchaseScopeLabels(),
            'repeatModeLabels' => LevelIncomeDirectQualification::repeatModeLabels(),
            'doubleCreditPolicyLabels' => LevelIncomeDirectQualification::doubleCreditPolicyLabels(),
        ]);
    }

    public function saveDraft(Request $request, LevelIncomeRuleAdministration $admin): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => ['nullable', 'integer', 'exists:level_income_rule_versions,id'],
            'version_code' => ['nullable', 'string', 'max:64'],
            'label' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'change_note' => ['nullable', 'string', 'max:1000'],
            'direct_qualification_basis' => ['required', 'string', Rule::in(LevelIncomeDirectQualification::BASES)],
            'tour_only_qualifies_for_unlock' => ['nullable', 'boolean'],
            'grandfather_existing_unlock' => ['nullable', 'boolean'],
            'combined_joining_enabled' => ['nullable', 'boolean'],
            'combined_purchase_enabled' => ['nullable', 'boolean'],
            'combined_purchase_scope' => ['nullable', 'string', Rule::in(LevelIncomeDirectQualification::PURCHASE_SCOPES)],
            'combined_repeat_purchase_mode' => ['nullable', 'string', Rule::in(LevelIncomeDirectQualification::REPEAT_MODES)],
            'combined_double_credit_policy' => ['nullable', 'string', Rule::in(LevelIncomeDirectQualification::DOUBLE_CREDIT_POLICIES)],
            'combined_joining_unit_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'combined_purchase_unit_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.start_level' => ['required', 'integer', 'min:1', 'max:20'],
            'bands.*.end_level' => ['required', 'integer', 'min:1', 'max:20'],
            'bands.*.min_qualifying_directs' => ['required', 'integer', 'min:0', 'max:100'],
            'bands.*.requires_personal_activation' => ['nullable', 'boolean'],
            'rates' => ['required', 'array'],
            'rates.*' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = null;
        if (! empty($data['version_id'])) {
            $existing = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        }

        $bands = [];
        foreach (array_values($data['bands']) as $index => $band) {
            $bands[] = [
                'sort_order' => $index + 1,
                'start_level' => (int) $band['start_level'],
                'end_level' => (int) $band['end_level'],
                'min_qualifying_directs' => (int) $band['min_qualifying_directs'],
                'requires_personal_activation' => $request->boolean("bands.$index.requires_personal_activation"),
            ];
        }

        $version = $admin->saveDraft([
            'version_code' => $data['version_code'] ?? null,
            'label' => $data['label'],
            'notes' => $data['notes'] ?? null,
            'change_note' => $data['change_note'] ?? null,
            'direct_qualification_basis' => $data['direct_qualification_basis'],
            'tour_only_qualifies_for_unlock' => $request->boolean('tour_only_qualifies_for_unlock'),
            'grandfather_existing_unlock' => $request->boolean('grandfather_existing_unlock'),
            'combined_joining_enabled' => $request->boolean('combined_joining_enabled'),
            'combined_purchase_enabled' => $request->boolean('combined_purchase_enabled'),
            'combined_purchase_scope' => $data['combined_purchase_scope'] ?? LevelIncomeDirectQualification::BASIS_APPROVED_PACKAGE_DIRECTS,
            'combined_repeat_purchase_mode' => $data['combined_repeat_purchase_mode'] ?? LevelIncomeDirectQualification::REPEAT_UNIQUE_DIRECTS,
            'combined_double_credit_policy' => $data['combined_double_credit_policy'] ?? LevelIncomeDirectQualification::DOUBLE_PREVENT_OVERLAP,
            'combined_joining_unit_value' => (int) ($data['combined_joining_unit_value'] ?? 1),
            'combined_purchase_unit_value' => (int) ($data['combined_purchase_unit_value'] ?? 1),
            'bands' => $bands,
            'rates' => $data['rates'],
        ], $request->user(), $request->ip(), $existing);

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'unlock', 'version' => $version->id])
            ->with('status', 'Draft Level Income rules saved. Financial posting is unchanged.');
    }

    public function publish(Request $request, LevelIncomeRuleAdministration $admin): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:level_income_rule_versions,id'],
            'applicability' => ['required', 'in:unspecified,future_purchases_only,existing_prospective'],
            'effective_from' => ['nullable', 'date'],
            'change_note' => ['nullable', 'string', 'max:1000'],
            'confirm_publish' => ['accepted'],
        ]);

        $version = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        $admin->publish($version, $request->user(), [
            'applicability' => $data['applicability'],
            'effective_from' => $data['effective_from'] ?? null,
            'change_note' => $data['change_note'] ?? null,
        ], $request->ip());

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'history', 'version' => $version->id])
            ->with('status', 'Configuration published. This version is not posting-authorized — financial:daily Level Income stays on hard-coded legacy until explicit authorize. Publish is refused while another version is still posting-authorized (revoke first).');
    }

    public function authorizePosting(Request $request, LevelIncomeRuleAdministration $admin): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:level_income_rule_versions,id'],
            'confirm_authorize' => ['accepted'],
            'confirm_existing_prospective' => ['sometimes', 'accepted'],
            'confirm_pre_cutover_baseline' => ['sometimes', 'accepted'],
            'confirm_impact_preview' => ['accepted'],
        ]);

        $version = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        $admin->authorizePosting(
            $version,
            $request->user(),
            true,
            $request->ip(),
            $request->boolean('confirm_existing_prospective'),
            $request->boolean('confirm_pre_cutover_baseline')
        );

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'history', 'version' => $version->id])
            ->with('status', 'Posting authorization recorded. Review financial controls before relying on daily runs.');
    }

    public function revokePosting(Request $request, LevelIncomeRuleAdministration $admin): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:level_income_rule_versions,id'],
        ]);

        $version = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        $admin->revokePostingAuthorization($version, $request->user(), $request->ip());

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'history', 'version' => $version->id])
            ->with('status', 'Posting authorization revoked. Live accrual uses hard-coded legacy rates/unlock again.');
    }

    public function cloneDraft(Request $request, LevelIncomeRuleAdministration $admin): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:level_income_rule_versions,id'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        $source = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        $draft = $admin->cloneAsDraft($source, $request->user(), $request->ip(), $data['label'] ?? null);

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'unlock', 'version' => $draft->id])
            ->with('status', 'Draft cloned. Edit and validate before publishing.');
    }

    public function captureGrandfatherBaseline(
        Request $request,
        LevelIncomeUnlockGrandfathering $grandfathering
    ): RedirectResponse {
        $data = $request->validate([
            'version_id' => ['required', 'integer', 'exists:level_income_rule_versions,id'],
            'transition_date' => ['required', 'date'],
            'confirm_capture' => ['accepted'],
        ]);

        $version = LevelIncomeRuleVersion::query()->findOrFail((int) $data['version_id']);
        if (! $version->grandfather_existing_unlock) {
            $version->forceFill(['grandfather_existing_unlock' => true])->save();
        }

        $result = $grandfathering->captureBaseline(
            $version->fresh(),
            $request->user(),
            $data['transition_date'],
            $request->ip()
        );

        return redirect()
            ->route('admin.finance.commission-settings', ['tab' => 'unlock', 'version' => $version->id])
            ->with(
                'status',
                sprintf(
                    'Grandfather baseline captured for %s on %s (%d members, %d with legacy unlock, mode=%s). Posting remains unauthorized. %s',
                    $result['version_code'],
                    $result['transition_date'],
                    $result['captured'],
                    $result['with_unlock'],
                    $result['capture_mode'],
                    $result['capture_note']
                )
            );
    }

    public function levelUnlockRules(
        ReferralLevelEligibility $eligibility,
        LevelIncomeRuleResolver $resolver,
        LevelIncomeRuleAdministration $admin,
    ): View {
        $published = $resolver->publishedBaseline();
        $proposed = LevelIncomeRuleVersion::query()
            ->with('bands')
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->first();

        $rules = [];
        foreach ([0, 1, 2, 3, 4, 5] as $count) {
            $rules[] = [
                'directs' => $count === 5 ? '5 or more' : (string) $count,
                'levels' => $eligibility->levelsForCount($count, true),
                'levels_inactive' => $eligibility->levelsForCount($count, false),
                'proposed_active' => $proposed
                    ? $resolver->simulateUnlockedLevels(
                        $proposed->bands->map(fn ($b) => [
                            'start_level' => (int) $b->start_level,
                            'end_level' => (int) $b->end_level,
                            'min_qualifying_directs' => (int) $b->min_qualifying_directs,
                            'requires_personal_activation' => (bool) $b->requires_personal_activation,
                        ])->all(),
                        $count,
                        true
                    )
                    : null,
            ];
        }

        return view('admin.finance.level-rules', [
            'rules' => $rules,
            'published' => $published,
            'proposed' => $proposed,
            'preview' => $admin->impactPreview($proposed),
            'liveUsesHardcoded' => $resolver->authorizedVersion() === null,
            'qualifyingTypes' => array_map(
                fn (string $type): string => \App\Models\Package::TYPES[$type] ?? $type,
                ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES
            ),
        ]);
    }
}
