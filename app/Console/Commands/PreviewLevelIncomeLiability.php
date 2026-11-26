<?php

namespace App\Console\Commands;

use App\Services\LevelIncomeLiabilityProjection;
use Illuminate\Console\Command;

/**
 * Read-only Combined Level Unlock (Option B) financial impact preview.
 * Never writes wallets, accruals, purchases, authorization, or scheduler state.
 */
class PreviewLevelIncomeLiability extends Command
{
    protected $signature = 'deltasupport:preview-level-income-liability
                            {--date= : Business date YYYY-MM-DD (required for projection)}
                            {--rule-version=proposed_activation_unlock_v1 : Candidate rule version_code}
                            {--applicability=existing_prospective : Simulated applicability}
                            {--effective-from= : Simulated effective_from (defaults to candidate or date)}
                            {--detail-limit=100 : Max member/purchase detail rows}
                            {--json : Emit JSON}';

    protected $description = 'Read-only Level Income liability: legacy vs proposed combined unlock (no financial writes).';

    public function handle(LevelIncomeLiabilityProjection $projection): int
    {
        $date = trim((string) $this->option('date'));
        if ($date === '') {
            $this->error('Provide --date=YYYY-MM-DD for the future business date to project.');

            return self::FAILURE;
        }

        $report = $projection->project($date, [
            'version_code' => (string) $this->option('rule-version'),
            'applicability' => (string) $this->option('applicability'),
            'effective_from' => $this->option('effective-from') ?: null,
            'detail_limit' => (int) $this->option('detail-limit'),
            'include_roi_check' => true,
        ]);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return isset($report['error']) ? self::FAILURE : self::SUCCESS;
        }

        if (isset($report['error'])) {
            $this->error((string) $report['error']);

            return self::FAILURE;
        }

        $totals = $report['totals'];
        $candidate = $report['candidate'];
        $unlock = $report['unlock_summary'];

        $this->info('Delta Support — Combined Level Unlock Option B financial impact (READ-ONLY)');
        $this->table(['Field', 'Value'], [
            ['Business date', $date],
            ['Candidate version', (string) $candidate['version_code']],
            ['Candidate basis', (string) $candidate['direct_qualification_basis']],
            ['Simulated applicability', (string) $candidate['applicability_simulated']],
            ['Simulated effective_from', (string) $candidate['effective_from_simulated']],
            ['Candidate posting_authorized', ! empty($candidate['posting_authorized']) ? 'YES' : 'no'],
            ['Associate posting enabled', ! empty($totals['associate_commission_posting_enabled']) ? 'YES' : 'no'],
            ['Mutates', ($report['mutates'] ?? false) ? 'YES' : 'no'],
        ]);

        $this->info('Daily Level Income liability comparison');
        $this->table(['Metric', 'USDT'], [
            ['Legacy commission (daily)', (string) $totals['legacy_commission_daily']],
            ['Proposed commission (daily)', (string) $totals['proposed_commission_daily']],
            ['Additional daily liability', (string) $totals['additional_daily_liability']],
            ['Remaining projected legacy', (string) $totals['remaining_projected_legacy_liability']],
            ['Remaining projected proposed', (string) $totals['remaining_projected_proposed_liability']],
            ['Remaining projected additional', (string) $totals['remaining_projected_additional_liability']],
            ['ROI daily (unchanged check)', (string) $totals['roi_daily_unchanged_check_total']],
        ]);

        $this->info('Unlock impact summary (levels, not USDT)');
        $this->table(['Metric', 'Count'], [
            ['Members analyzed', (string) ($unlock['analyzed'] ?? 0)],
            ['Gaining levels', (string) ($unlock['gain'] ?? 0)],
            ['Losing levels', (string) ($unlock['loss'] ?? 0)],
            ['Unchanged', (string) ($unlock['unchanged'] ?? 0)],
            ['Activated with 0 live unlock', (string) ($unlock['activated_with_zero_live_unlock'] ?? 0)],
            ['Entitlement reductions', (string) ($unlock['entitlement_reductions'] ?? 0)],
        ]);

        $tour = $report['tour_only_business_volume'];
        $this->info(sprintf(
            'Tour Only BV: %s purchases / %s USDT (counted_once=%s) — separate from commission generation.',
            $tour['purchase_count'],
            $tour['total_usdt'],
            ! empty($tour['counted_once']) ? 'yes' : 'NO'
        ));

        if (($report['level_differences'] ?? []) !== []) {
            $this->info('Level-wise daily differences');
            $this->table(
                ['Level', 'Legacy', 'Proposed', 'Additional', 'Legacy lines', 'Proposed lines'],
                array_map(static fn (array $row): array => [
                    (string) $row['level'],
                    (string) $row['legacy_daily'],
                    (string) $row['proposed_daily'],
                    (string) $row['additional_daily'],
                    (string) $row['legacy_line_count'],
                    (string) $row['proposed_line_count'],
                ], $report['level_differences'])
            );
        }

        $memberDiffs = array_values(array_filter(
            $report['member_differences'] ?? [],
            static fn (array $row): bool => bccomp((string) $row['additional_daily'], '0', 4) !== 0
        ));
        if ($memberDiffs !== []) {
            $this->info('Member-wise additional daily liability (non-zero)');
            $this->table(
                ['Member', 'Legacy', 'Proposed', 'Additional'],
                array_map(static fn (array $row): array => [
                    (string) $row['member_id'],
                    (string) $row['legacy_daily'],
                    (string) $row['proposed_daily'],
                    (string) $row['additional_daily'],
                ], array_slice($memberDiffs, 0, 30))
            );
        }

        $purchaseDiffs = array_values(array_filter(
            $report['purchase_differences'] ?? [],
            static fn (array $row): bool => bccomp((string) $row['additional_daily'], '0', 4) !== 0
        ));
        if ($purchaseDiffs !== []) {
            $this->info('Purchase-wise additional daily liability (non-zero)');
            $this->table(
                ['Purchase', 'Source', 'Package', 'Remain days', 'Legacy', 'Proposed', 'Additional'],
                array_map(static fn (array $row): array => [
                    (string) $row['reference'],
                    (string) $row['source_member_id'],
                    (string) $row['package_code'],
                    (string) $row['remaining_commission_days'],
                    (string) $row['legacy_daily'],
                    (string) $row['proposed_daily'],
                    (string) $row['additional_daily'],
                ], array_slice($purchaseDiffs, 0, 30))
            );
        }

        $this->comment('Assumptions: unlock metric frozen as of business date; remaining liability = additional_daily × remaining commission days per source; no backfill; no authorize.');
        $this->comment('No wallets, ledger entries, accruals, purchases, authorization flags, or scheduler controls were modified.');

        if (! empty($totals['associate_commission_posting_enabled'])) {
            $this->error('WARNING: Associate Commission posting appears enabled. Option B preview expects it OFF.');
        }

        if (! empty($candidate['posting_authorized'])) {
            $this->warn('Candidate is already posting-authorized in this database. Preview still did not write.');
        } else {
            $this->info('Candidate remains unauthorized. Safe for local Option B impact review.');
        }

        return self::SUCCESS;
    }
}
