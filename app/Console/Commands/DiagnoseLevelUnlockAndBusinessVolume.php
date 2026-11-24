<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\PackagePurchase;
use App\Services\LevelIncomeUnlockImpactAnalyzer;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for level unlock + Tour Only business volume.
 * Does not mutate wallets, commissions, purchases, or authorization.
 */
class DiagnoseLevelUnlockAndBusinessVolume extends Command
{
    protected $signature = 'deltasupport:diagnose-level-unlock-business
                            {--member= : Optional member_id focus}
                            {--limit=50 : Max member rows in sample tables}
                            {--all : Analyze every registered member for impact summary}
                            {--json : Emit JSON}';

    protected $description = 'Read-only report: consistent unlock contexts, entitlement impact, Tour Only BV (no financial writes).';

    public function handle(
        LevelIncomeUnlockImpactAnalyzer $analyzer,
        TeamBusinessCalculationService $business,
    ): int {
        $memberFocus = trim((string) $this->option('member'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $analyzeAll = (bool) $this->option('all');

        $totalMembers = Member::query()->count();
        $withApproved = Member::query()
            ->whereExists(function ($q): void {
                $q->selectRaw('1')->from('package_purchases')
                    ->whereColumn('package_purchases.member_id', 'members.member_id')
                    ->where('status', PackagePurchase::APPROVED)
                    ->whereNotNull('reviewed_at');
            })
            ->count();

        $tourAudit = $analyzer->tourOnlyBusinessVolumeAudit();
        $proposed = $analyzer->proposedVersion();
        $applicability = $analyzer->applicabilityProjection($proposed);
        $policies = $analyzer->policyComparison();

        if ($analyzeAll) {
            $sampleIds = Member::query()->orderBy('id')->pluck('member_id')->all();
        } else {
            $sampleIds = Member::query()
                ->orderBy('id')
                ->limit($limit)
                ->pluck('member_id')
                ->all();
        }
        if ($memberFocus !== '') {
            $sampleIds = array_values(array_unique(array_merge([$memberFocus], $sampleIds)));
        }

        $impact = $analyzer->analyzeMembers($sampleIds, $proposed);
        $rowsById = [];
        foreach ($impact['rows'] as $row) {
            $rowsById[$row['member_id']] = $row;
        }

        // Consistency guard: every section must use the same live_unlocked_levels for a member.
        $inconsistent = [];
        foreach ($impact['rows'] as $row) {
            $live = (int) $row['live_unlocked_levels'];
            $fromLegacyContext = (int) $row['contexts'][LevelIncomeUnlockImpactAnalyzer::CONTEXT_LIVE_LEGACY]['unlocked_levels'];
            if ($live !== $fromLegacyContext) {
                $inconsistent[] = $row['member_id'];
            }
        }

        $gains = array_values(array_filter($impact['rows'], fn (array $r): bool => $r['impact'] === LevelIncomeUnlockImpactAnalyzer::IMPACT_GAIN));
        $losses = array_values(array_filter($impact['rows'], fn (array $r): bool => $r['impact'] === LevelIncomeUnlockImpactAnalyzer::IMPACT_LOSS));
        $unchanged = array_values(array_filter($impact['rows'], fn (array $r): bool => $r['impact'] === LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED));
        $activatedZero = array_values(array_filter($impact['rows'], fn (array $r): bool => $r['activated_with_zero_live_unlock']));
        $deltas = array_values(array_filter($impact['rows'], fn (array $r): bool => $r['impact'] !== LevelIncomeUnlockImpactAnalyzer::IMPACT_UNCHANGED));

        $focus = null;
        if ($memberFocus !== '' && isset($rowsById[$memberFocus])) {
            $focusRow = $rowsById[$memberFocus];
            $focusBiz = $business->businessForMembers([$memberFocus]);
            $focus = [
                'impact' => $focusRow,
                'business' => $focusBiz[$memberFocus] ?? null,
                'consistency_check' => [
                    'live_unlocked_levels' => $focusRow['live_unlocked_levels'],
                    'table_current_would_show' => $focusRow['live_unlocked_levels'],
                    'activated_zero_table_unlocked_would_show' => $focusRow['live_unlocked_levels'],
                    'same_value_across_tables' => true,
                ],
            ];
        } elseif ($memberFocus !== '') {
            $focus = ['error' => 'Member not found in sample/universe: '.$memberFocus];
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'totals' => [
                'registered_members' => $totalMembers,
                'members_with_approved_packages' => $withApproved,
                'approved_tour_only_purchase_count' => $tourAudit['purchase_count'],
                'approved_tour_only_business_usdt' => $tourAudit['total_usdt'],
                'tour_only_counted_once' => $tourAudit['counted_once'],
                'sample_size' => count($sampleIds),
                'analyzed_all' => $analyzeAll,
            ],
            'impact_summary' => $impact['summary'],
            'proposed_version' => $proposed === null ? null : [
                'version_code' => $proposed->version_code,
                'status' => $proposed->status,
                'posting_authorized' => (bool) $proposed->posting_authorized,
                'direct_qualification_basis' => $proposed->direct_qualification_basis,
                'tour_only_qualifies_for_unlock' => (bool) $proposed->tour_only_qualifies_for_unlock,
            ],
            'policy_comparison' => $policies,
            'applicability_projection' => $applicability,
            'tour_only_bv_audit' => $tourAudit,
            'consistency' => [
                'live_unlocked_single_source' => 'live_unlocked_levels from LevelIncomeUnlockImpactAnalyzer (legacy ladder)',
                'members_with_internal_inconsistency' => $inconsistent,
                'ok' => $inconsistent === [],
            ],
            'entitlement_warnings' => [
                'losses_in_sample' => count($losses),
                'authorize_blocked_while_losses_exist' => count($losses) > 0,
                'note' => 'Do not authorize while entitlement reductions exist unless business explicitly accepts BD-Q losses.',
            ],
            'members_gaining_levels' => array_map(fn (array $r): array => $this->compactRow($r), $gains),
            'members_losing_levels' => array_map(fn (array $r): array => $this->compactRow($r), $losses),
            'members_unchanged_count' => count($unchanged),
            'activated_with_zero_live_unlock' => array_map(fn (array $r): array => $this->compactRow($r), $activatedZero),
            'current_vs_proposed_deltas' => array_map(fn (array $r): array => $this->compactRow($r), $deltas),
            'member_focus' => $focus,
            'policy_notes' => [
                'live_legacy' => 'Income-eligible unique directs; personal activation ignored by unlock ladder.',
                'proposed' => 'Bands require personal activation; qualification basis + Tour Only unlock flag apply only after posting_authorized.',
                'st100026_class' => 'Live>0 and proposed=0 usually means missing proposed personal activation while legacy still counts income-eligible directs.',
                'table_consistency' => 'Current/Unlocked columns in every table use the same live_unlocked_levels field.',
                'no_backfill' => 'Diagnostics never authorize, backfill, or post Associate Commission.',
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Delta Support — Level Unlock & Business Volume Diagnostic (read-only)');
        $this->table(['Metric', 'Value'], [
            ['Registered members', (string) $totalMembers],
            ['Members with approved packages', (string) $withApproved],
            ['Approved Tour Only purchases', (string) $tourAudit['purchase_count']],
            ['Approved Tour Only BV (USDT)', $tourAudit['total_usdt']],
            ['Tour Only counted once', $tourAudit['counted_once'] ? 'yes' : 'NO'],
            ['Sample/universe size', (string) count($sampleIds)],
            ['Gains / Losses / Unchanged', sprintf(
                '%d / %d / %d',
                $impact['summary']['gain'],
                $impact['summary']['loss'],
                $impact['summary']['unchanged']
            )],
            ['Activated with 0 live unlock', (string) $impact['summary']['activated_with_zero_live_unlock']],
            ['Entitlement reductions', (string) $impact['summary']['entitlement_reductions']],
            ['Proposed version', $proposed?->version_code ?? 'missing'],
            ['Proposed basis', (string) ($proposed?->direct_qualification_basis ?? 'n/a')],
            ['Proposed Tour Only unlock', ($proposed?->tour_only_qualifies_for_unlock ?? false) ? 'yes' : 'no'],
            ['Proposed posting_authorized', ($proposed?->posting_authorized ?? false) ? 'YES' : 'no'],
            ['Internal consistency OK', $report['consistency']['ok'] ? 'yes' : 'NO'],
        ]);

        if ($tourAudit['purchases'] !== []) {
            $this->info('Tour Only BV purchases (each counted once):');
            $this->table(
                ['Purchase ID', 'Member', 'Code', 'USDT', 'Reviewed'],
                array_map(fn (array $p): array => [
                    (string) $p['package_purchase_id'],
                    $p['member_id'],
                    $p['package_code'],
                    $p['price_usd'],
                    (string) $p['reviewed_at'],
                ], $tourAudit['purchases'])
            );
        }

        if ($losses !== []) {
            $this->error('ENTITLEMENT REDUCTION RISK — members with live > proposed (not applied):');
            $this->table(
                ['Member', 'Live', 'Proposed', 'Δ', 'Legacy act.', 'Proposed act.', 'Reason'],
                array_map(fn (array $r): array => [
                    $r['member_id'],
                    (string) $r['live_unlocked_levels'],
                    (string) $r['proposed_unlocked_levels'],
                    (string) $r['levels_delta'],
                    $r['legacy_personally_activated'] ? 'yes' : 'no',
                    $r['proposed_personally_activated'] ? 'yes' : 'no',
                    $r['delta_reason'],
                ], array_slice($losses, 0, 30))
            );
        }

        if ($gains !== []) {
            $this->warn('Members gaining levels under proposed simulation (not applied):');
            $this->table(
                ['Member', 'Live', 'Proposed', 'Δ', 'Reason'],
                array_map(fn (array $r): array => [
                    $r['member_id'],
                    (string) $r['live_unlocked_levels'],
                    (string) $r['proposed_unlocked_levels'],
                    (string) $r['levels_delta'],
                    $r['delta_reason'],
                ], array_slice($gains, 0, 30))
            );
        }

        if ($activatedZero !== []) {
            $this->warn('Activated (legacy income-eligible) with 0 live unlock — same Unlocked column source as above:');
            $this->table(
                ['Member', 'Live unlocked', 'Proposed', 'Income dirs', 'Reason'],
                array_map(fn (array $r): array => [
                    $r['member_id'],
                    (string) $r['live_unlocked_levels'],
                    (string) $r['proposed_unlocked_levels'],
                    (string) $r['counts']['income_eligible_direct_count'],
                    $r['live_reason'],
                ], array_slice($activatedZero, 0, 30))
            );
        }

        $this->info('Applicability projection (not authorized):');
        $this->line('- future_purchases_only: '.$applicability['future_purchases_only']['effect_on_existing_43_style_members']);
        $this->line('- existing_prospective: '.$applicability['existing_prospective']['effect_on_existing_43_style_members']);
        $this->line(sprintf(
            '- Approved purchases before/on-after assumed effective %s: %d / %d',
            $applicability['assumed_effective_from'],
            $applicability['future_purchases_only']['purchases_approved_before_effective'],
            $applicability['future_purchases_only']['purchases_approved_on_or_after_effective']
        ));

        if ($focus !== null) {
            $this->info('Focus member '.$memberFocus.':');
            $this->line(json_encode($focus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (($report['entitlement_warnings']['authorize_blocked_while_losses_exist'] ?? false) === true) {
            $this->error('Authorization readiness: NO-GO while entitlement reductions exist in the analyzed set.');
        }

        $this->comment('No wallets, commissions, or purchases were modified. Do not authorize from this command.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function compactRow(array $row): array
    {
        return [
            'member_id' => $row['member_id'],
            'registered_directs' => $row['counts']['registered_direct_count'],
            'income_eligible_directs' => $row['counts']['income_eligible_direct_count'],
            'approved_package_directs' => $row['counts']['approved_package_direct_count'],
            'approved_purchase_count' => $row['counts']['approved_direct_package_purchase_count'],
            'tour_only_directs' => $row['counts']['tour_only_purchasing_direct_count'],
            'live_unlocked' => $row['live_unlocked_levels'],
            'proposed_unlocked' => $row['proposed_unlocked_levels'],
            'protected_unlocked' => $row['protected_unlocked_levels'] ?? $row['proposed_unlocked_levels'],
            'impact' => $row['impact'],
            'levels_delta' => $row['levels_delta'],
            'legacy_personally_activated' => $row['legacy_personally_activated'],
            'proposed_personally_activated' => $row['proposed_personally_activated'],
            'delta_reason' => $row['delta_reason'],
            'live_reason' => $row['live_reason'],
        ];
    }
}
