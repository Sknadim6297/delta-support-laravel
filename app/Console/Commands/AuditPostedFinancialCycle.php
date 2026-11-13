<?php

namespace App\Console\Commands;

use App\Services\PostedFinancialCycleAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AuditPostedFinancialCycle extends Command
{
    protected $signature = 'financial:audit-posted
        {--database= : Exact connected database name}
        {--dates=2026-11-11,2026-11-12 : Comma-separated Asia/Kolkata business dates}';

    protected $description = 'Read-only audit of posted ROI/commission days, wallet/ledger alignment, unique indexes, and approved rates. Never credits wallets.';

    public function handle(PostedFinancialCycleAudit $audit): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No change was made.');

            return self::FAILURE;
        }

        $dates = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('dates')))));
        try {
            $report = $audit->inspect($dates);
        } catch (RuntimeException|Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Connected database: '.$report['database']);
        $this->line('Read-only audit. No wallet, accrual, control, or ledger row was written.');
        $this->newLine();

        $this->table(
            ['Index', 'Present'],
            [
                ['package_roi_purchase_date_unique', $report['unique_indexes']['roi'] ? 'yes' : 'NO'],
                ['package_level_purchase_member_date_unique', $report['unique_indexes']['level'] ? 'yes' : 'NO'],
                ['wallet_ledger_entries_reference_unique', $report['unique_indexes']['ledger_reference'] ? 'yes' : 'NO'],
                ['wallet_ledger_entries_idempotency_key_unique', $report['unique_indexes']['ledger_idempotency'] ? 'yes' : 'NO'],
            ]
        );

        $roiConfig = $report['package_income']['DST-ROI-002'];
        $this->line('DST-ROI-002 config: '.($roiConfig === null ? 'MISSING' : json_encode($roiConfig)));
        $tourConfig = $report['package_income']['DST-TOUR-003'];
        $this->line('DST-TOUR-003 config: '.($tourConfig === null ? 'MISSING' : json_encode($tourConfig)));

        $rateMismatch = array_values(array_filter($report['rates'], fn (array $rate): bool => ! $rate['matches']));
        if ($rateMismatch === []) {
            $this->info('20-level commission chart matches the approved inactive rates.');
        } else {
            $this->error('Commission rate mismatches:');
            $this->table(['Level', 'Stored', 'Expected', 'Active'], array_map(fn (array $rate): array => [
                (string) $rate['level'],
                $rate['percentage'],
                $rate['expected'],
                (string) $rate['is_active'],
            ], $rateMismatch));
        }

        foreach ($report['dates'] as $day) {
            $this->newLine();
            $this->info('Business date '.$day['business_date'].': '.($day['ok'] ? 'OK' : 'ISSUES'));
            $this->table(
                ['Metric', 'ROI', 'Commission'],
                [
                    ['Accrual rows', (string) $day['roi']['rows'], (string) $day['commissions']['rows']],
                    ['Accrued', (string) $day['roi']['accrued'], (string) $day['commissions']['accrued']],
                    ['Calculated (unposted)', (string) $day['roi']['calculated'], (string) $day['commissions']['calculated']],
                    ['Accrual amount', $day['roi']['amount'], $day['commissions']['amount']],
                    ['Ledger credit rows', (string) $day['roi']['ledger_rows'], (string) $day['commissions']['ledger_rows']],
                    ['Ledger amount', $day['roi']['ledger_amount'], $day['commissions']['ledger_amount']],
                    ['Accrued missing ledger', (string) $day['roi']['missing_ledger'], (string) $day['commissions']['missing_ledger']],
                    ['Duplicate accrual keys', (string) $day['roi']['duplicate_keys'], (string) $day['commissions']['duplicate_keys']],
                ]
            );
            $this->line('Duplicate ledger references: '.$day['duplicate_ledger_references']);
            $this->line('Wallet vs ledger variances for touched members: '.count($day['wallet_variances']));
            foreach ($day['runs'] as $run) {
                $this->line('Run #'.$run['id'].' '.$run['status'].' roi='.$run['credited_roi'].' commissions='.$run['credited_commissions'].' :: '.$run['message']);
            }
            foreach ($day['wallet_variances'] as $variance) {
                $this->warn(json_encode($variance));
            }
        }

        $failed = $rateMismatch !== []
            || ! $report['unique_indexes']['roi']
            || ! $report['unique_indexes']['level']
            || ! $report['unique_indexes']['ledger_reference']
            || ! $report['unique_indexes']['ledger_idempotency']
            || $report['package_income']['DST-ROI-002'] === null
            || $report['package_income']['DST-TOUR-003'] === null
            || collect($report['dates'])->contains(fn (array $day): bool => ! $day['ok']);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
