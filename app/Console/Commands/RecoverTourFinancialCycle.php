<?php

namespace App\Console\Commands;

use App\Services\TourFinancialRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RecoverTourFinancialCycle extends Command
{
    protected $signature = 'financial:recover-tour
        {--date= : Single Asia/Kolkata business date YYYY-MM-DD (required for apply; optional for preview)}
        {--dates= : Comma-separated explicit preview dates YYYY-MM-DD (preview only)}
        {--database= : Exact connected database name}
        {--confirm= : Type recover-tour-package-income to apply one date}
        {--backup-confirmed= : Type database-backup-confirmed after a verified DB backup}
        {--rules-confirmed= : Type tour-recovery-rules-confirmed after written Tour recovery approval}
        {--preview : Force preview mode (default when confirm is omitted)}';

    protected $description = 'Explicit DST-TOUR-003 recovery for approved business dates only. Preview by default. Apply recovers one date per confirmation. Never recovers before the Tour activation floor. Standard packages are untouched.';

    public function handle(TourFinancialRecovery $recovery): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No wallet was credited.');

            return self::FAILURE;
        }

        $single = trim((string) $this->option('date'));
        $many = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('dates')))));
        $previewOnly = (bool) $this->option('preview')
            || trim((string) $this->option('confirm')) === '';

        $this->line('Connected database: '.$connected);
        $this->line('Package: DST-TOUR-003 only');
        $this->line('Standard packages: untouched');
        $this->line('Default mode: preview (no wallet writes)');
        $this->warn('Dates before the Tour activation floor are refused. Historical blanket Tour backfill remains forbidden.');

        try {
            if ($previewOnly) {
                $dates = $many;
                if ($single !== '') {
                    $dates[] = $single;
                }
                if ($dates === []) {
                    $this->error('Refused. Pass --date=YYYY-MM-DD and/or --dates=YYYY-MM-DD,YYYY-MM-DD for preview. No wallet was credited.');

                    return self::FAILURE;
                }

                $report = $recovery->preview($dates);
                $this->renderReport($report);
                $this->warn('Preview only. To apply ONE approved date after backup + written Tour recovery approval:');
                $this->line('  php artisan financial:recover-tour --database='.$connected.' --date=YYYY-MM-DD \\');
                $this->line('    --confirm='.TourFinancialRecovery::CONFIRM);
                $this->line('    --backup-confirmed='.TourFinancialRecovery::BACKUP_CONFIRM);
                $this->line('    --rules-confirmed='.TourFinancialRecovery::RULES_CONFIRM);
                $this->warn('No wallet was credited.');

                return self::SUCCESS;
            }

            if ($single === '') {
                $this->error('Refused. Apply requires exactly one --date=YYYY-MM-DD. No wallet was credited.');

                return self::FAILURE;
            }
            if ($many !== []) {
                $this->error('Refused. Apply does not accept --dates=. Use one --date per confirmation. No wallet was credited.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('confirm')) !== TourFinancialRecovery::CONFIRM) {
                $this->error('Refused. Repeat with --confirm='.TourFinancialRecovery::CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('backup-confirmed')) !== TourFinancialRecovery::BACKUP_CONFIRM) {
                $this->error('Refused. Pass --backup-confirmed='.TourFinancialRecovery::BACKUP_CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }
            if (trim((string) $this->option('rules-confirmed')) !== TourFinancialRecovery::RULES_CONFIRM) {
                $this->error('Refused. Pass --rules-confirmed='.TourFinancialRecovery::RULES_CONFIRM.'. No wallet was credited.');

                return self::FAILURE;
            }

            $report = $recovery->apply($single);
            $this->renderReport($report);
            if (in_array($report['status'], ['failed', 'partial', 'refused'], true)) {
                $this->error('Tour recovery finished with status '.$report['status'].'. Idempotent retry is safe.');

                return self::FAILURE;
            }
            $this->info('Tour recovery apply completed for '.$single.'. Reconcile with financial:audit-posted.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Tour recovery failed. '.$exception->getMessage());

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
            ['Package', $report['package_code']],
            ['Requested dates', implode(', ', $report['requested_dates'])],
            ['Activation floor', (string) ($report['activation_floor'] ?? '(missing)')],
            ['Tour posting authorized', $report['tour_posting_authorized'] ? 'yes' : 'no'],
            ['Armed', $report['automation_armed'] ? 'yes' : 'no'],
            ['Emergency stop', $report['emergency_stop'] ? 'yes' : 'no'],
            ['Posting allowed', $report['posting_allowed'] ? 'yes' : 'no'],
            ['Standard packages touched', $report['standard_packages_touched'] ? 'yes' : 'no'],
            ['Total Tour ROI rows', (string) $report['totals']['roi_records']],
            ['Total Tour ROI amount', $report['totals']['roi_amount']],
            ['Total commission pool (eligible purchases)', $report['totals']['commission_pool_amount']],
            ['Total payable commission (eligible uplines)', $report['totals']['commission_amount']],
            ['Total financial liability (ROI + payable commission)', $report['totals']['financial_liability']],
            ['Wallet writes', $report['writes'] ? 'yes' : 'no'],
        ]);

        foreach ($report['days'] as $day) {
            $this->newLine();
            $this->info('Business date '.$day['business_date'].' ['.$day['status'].']');
            if (($day['error'] ?? null) !== null) {
                $this->error((string) $day['error']);
            }

            if (($day['purchases'] ?? []) !== []) {
                $this->table(
                    ['Purchase', 'Member', 'ROI daily', 'Commission pool'],
                    array_map(fn (array $row): array => [
                        $row['reference'],
                        $row['member_id'],
                        $row['roi_daily'],
                        $row['commission_pool'],
                    ], $day['purchases'])
                );
            }

            if (($day['roi_lines'] ?? []) !== []) {
                $this->table(
                    ['Purchase', 'Member', 'ROI amount', 'Ledger ref'],
                    array_map(fn (array $row): array => [
                        $row['purchase_reference'],
                        $row['member_id'],
                        $row['amount'],
                        $row['ledger_reference'],
                    ], $day['roi_lines'])
                );
            }

            if (($day['commission_lines'] ?? []) !== []) {
                $this->table(
                    ['Purchase', 'From', 'Recipient', 'Lvl', 'Rate', 'Amount'],
                    array_map(fn (array $row): array => [
                        $row['purchase_reference'],
                        $row['from_member_id'],
                        $row['recipient_member_id'],
                        $row['level'],
                        $row['rate'],
                        $row['amount'],
                    ], $day['commission_lines'])
                );
            } else {
                $this->line('Payable commission recipients: none (pool may be unpaid when uplines are ineligible).');
            }

            $this->line(
                'Day totals — ROI '.$day['roi_amount']
                .' / pool '.$day['commission_pool_amount']
                .' / payable commission '.$day['commission_amount']
                .' / existing Tour ROI accrued '.$day['existing_roi_accrued']
            );
        }

        $this->newLine();
        $this->line($report['message']);
    }
}
