<?php

namespace App\Console\Commands;

use App\Services\HistoricalFinancialRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RecoverHistoricalFinancialCycle extends Command
{
    protected $signature = 'financial:recover-historical
        {--from= : Asia/Kolkata start date YYYY-MM-DD}
        {--to= : Asia/Kolkata end date YYYY-MM-DD}
        {--database= : Exact connected database name}
        {--packages=DST-ROI-002 : Comma-separated Standard package allowlist}
        {--confirm= : Type recover-historical-standard-income to apply}
        {--backup-confirmed= : Type database-backup-confirmed after a verified DB backup}
        {--rules-confirmed= : Type business-rules-confirmed after written approval}
        {--preview : Force preview mode (default when confirm is omitted)}';

    protected $description = 'Controlled Standard-package historical recovery. Preview by default. Tour income is never historically backfilled.';

    public function handle(HistoricalFinancialRecovery $recovery): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No wallet was credited.');

            return self::FAILURE;
        }

        $from = trim((string) $this->option('from'));
        $to = trim((string) $this->option('to'));
        if ($from === '' || $to === '') {
            $this->error('Refused. --from and --to are required. No wallet was credited.');

            return self::FAILURE;
        }

        $packages = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('packages')))));
        $previewOnly = (bool) $this->option('preview')
            || trim((string) $this->option('confirm')) === '';

        $this->line('Connected database: '.$connected);
        $this->line('Range: '.$from.' → '.$to.' Asia/Kolkata');
        $this->line('Packages: '.implode(', ', $packages));
        $this->line('Tour historical backfill: forbidden');
        $this->line('Default mode: preview (no wallet writes)');

        try {
            if ($previewOnly) {
                $report = $recovery->preview($from, $to, $packages);
                $this->renderReport($report);
                $this->warn('Preview only. To apply after backup + written approval:');
                $this->line('  --confirm='.HistoricalFinancialRecovery::CONFIRM);
                $this->line('  --backup-confirmed='.HistoricalFinancialRecovery::BACKUP_CONFIRM);
                $this->line('  --rules-confirmed='.HistoricalFinancialRecovery::RULES_CONFIRM);
                $this->warn('No wallet was credited.');

                return self::SUCCESS;
            }

            if (trim((string) $this->option('confirm')) !== HistoricalFinancialRecovery::CONFIRM) {
                $this->error('Refused. Repeat with --confirm='.HistoricalFinancialRecovery::CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('backup-confirmed')) !== HistoricalFinancialRecovery::BACKUP_CONFIRM) {
                $this->error('Refused. Pass --backup-confirmed='.HistoricalFinancialRecovery::BACKUP_CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('rules-confirmed')) !== HistoricalFinancialRecovery::RULES_CONFIRM) {
                $this->error('Refused. Pass --rules-confirmed='.HistoricalFinancialRecovery::RULES_CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }

            $report = $recovery->apply($from, $to, $packages);
            $this->renderReport($report);
            if (in_array($report['status'], ['failed', 'partial'], true)) {
                $this->error('Historical recovery finished with status '.$report['status'].'. Idempotent retry is safe.');

                return self::FAILURE;
            }
            $this->info('Historical Standard recovery apply completed. Reconcile with financial:audit-posted.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Historical recovery failed. '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $this->table(['Check', 'Value'], [
            ['Database', $report['database']],
            ['Audit id', (string) ($report['audit_id'] ?? '(none)')],
            ['Mode', $report['dry_run'] ? 'preview' : 'apply'],
            ['Status', $report['status']],
            ['Packages', implode(', ', $report['packages'])],
            ['Tour excluded', $report['tour_excluded'] ? 'yes' : 'no'],
            ['Armed', $report['automation_armed'] ? 'yes' : 'no'],
            ['Emergency stop', $report['emergency_stop'] ? 'yes' : 'no'],
            ['Posting allowed', $report['posting_allowed'] ? 'yes' : 'no'],
            ['Tour posting authorized', $report['tour_posting_authorized'] ? 'yes' : 'no'],
            ['Total Standard ROI rows', (string) $report['totals']['roi_records']],
            ['Total Standard ROI amount', $report['totals']['roi_amount']],
            ['Total Standard commission rows', (string) $report['totals']['commission_records']],
            ['Total Standard commission amount', $report['totals']['commission_amount']],
            ['Wallet writes', $report['writes'] ? 'yes' : 'no'],
        ]);

        $this->newLine();
        $this->table(
            ['Date', 'Status', 'Prev run', 'ROI', 'ROI amt', 'Comm', 'Comm amt', 'Error'],
            array_map(fn (array $day): array => [
                $day['business_date'],
                $day['status'],
                $day['previous_run_status'],
                (string) $day['roi_records'],
                $day['roi_amount'],
                (string) $day['commission_records'],
                $day['commission_amount'],
                (string) ($day['error'] ?? '-'),
            ], $report['days'])
        );

        $this->line($report['message']);
    }
}
