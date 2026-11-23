<?php

namespace App\Console\Commands;

use App\Services\ProductionPackageRoiDiagnosis;
use Illuminate\Console\Command;

/**
 * Read-only diagnosis for the six production packages, missing ROI config,
 * and Associate Posting ENABLED discrepancy. Never posts financial data.
 */
class DiagnoseProductionPackageRoi extends Command
{
    protected $signature = 'financial:diagnose-production-packages {--json : Emit JSON only}';

    protected $description = 'Read-only audit of six production packages, package_income gaps, and associate posting flags. Writes nothing.';

    public function handle(ProductionPackageRoiDiagnosis $diagnosis): int
    {
        $report = $diagnosis->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Production package ROI diagnosis (READ-ONLY — no posting)');
        $this->table(['Check', 'Value'], [
            ['Database', $report['database']],
            ['package_income.enabled', $report['package_income_enabled'] ? 'true' : 'false'],
            ['tour_posting_authorized', $report['tour_posting_authorized'] ? 'true' : 'false'],
            ['wallet_ledger.enabled', $report['wallet_ledger_enabled'] ? 'true' : 'false'],
            ['wait_days', (string) $report['wait_days']],
            ['original_tour_plan.active', $report['original_tour_plan']['active'] ? 'true' : 'false'],
            ['candidate_merged', $report['original_tour_plan']['candidate_merged'] ? 'true' : 'false'],
            ['Associate posting_enabled', $report['associate']['posting_enabled_config'] ? 'ENABLED' : 'disabled'],
            ['Associate snapshot_on_approval', $report['associate']['snapshot_on_approval'] ? 'on' : 'disabled'],
            ['Env ASSOCIATE_POSTING raw', var_export($report['associate']['env_DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING'], true)],
            ['Config cache file present', $report['associate']['config_cache_file_present'] ? 'yes' : 'no'],
            ['Income keys', implode(', ', $report['package_income_keys']) ?: '(none)'],
            ['Missing ROI income keys', implode(', ', $report['missing_package_income_for_roi_tiers']) ?: '(none)'],
        ]);

        $this->newLine();
        $this->info('Six production packages + VERIFY-1');
        $rows = [];
        foreach ($report['packages'] as $pkg) {
            $rows[] = [
                $pkg['code'],
                $pkg['exists'] ? 'yes' : 'MISSING',
                $pkg['is_active'] === null ? '—' : ($pkg['is_active'] ? 'Active' : 'Inactive'),
                $pkg['roi_eligible'] === null ? '—' : ($pkg['roi_eligible'] ? 'yes' : 'no'),
                $pkg['admin_daily_roi_label'],
                $pkg['admin_roi_days_label'],
                $pkg['associate_tier'] ?? '—',
            ];
        }
        $this->table(
            ['Code', 'Exists', 'Catalog', 'roi_eligible', 'Daily ROI', 'ROI days', 'Associate'],
            $rows
        );

        $this->newLine();
        $this->info('Root causes');
        foreach ($report['root_causes'] as $cause) {
            $this->line('• '.$cause);
        }

        $this->newLine();
        $this->warn('Associate posting discrepancy note');
        $this->line($report['associate']['discrepancy_explanation']);
        $this->line($report['associate']['ui_source']);

        $this->newLine();
        $this->info('VERIFY-1 note');
        $this->line($report['verify_one']['note']);

        $this->newLine();
        $this->info('Read-only production commands');
        foreach ($report['read_only_production_commands'] as $cmd) {
            $this->line('  '.$cmd);
        }

        $this->newLine();
        $this->warn('Activation blockers (do not bypass)');
        foreach ($report['activation_blockers'] as $blocker) {
            $this->line('• '.$blocker);
        }

        $this->newLine();
        $this->info('Counts (read-only)');
        $countRows = [];
        foreach ($report['counts'] as $key => $value) {
            $countRows[] = [$key, (string) $value];
        }
        $this->table(['Metric', 'Count'], $countRows);

        return self::SUCCESS;
    }
}
