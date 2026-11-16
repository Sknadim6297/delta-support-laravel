<?php

namespace App\Console\Commands;

use App\Services\FinancialRecoveryPreview;
use Illuminate\Console\Command;

class PreviewFinancialRecovery extends Command
{
    protected $signature = 'financial:recovery-preview
        {--days=14 : Asia/Kolkata lookback window including today}';

    protected $description = 'Stage A read-only recovery preview. Never writes wallets, accruals, ledgers, controls, or run rows.';

    public function handle(FinancialRecoveryPreview $preview): int
    {
        $report = $preview->build((int) $this->option('days'));

        $this->table(['Check', 'Value'], [
            ['Connected database', $report['database']],
            ['Timezone / now', $report['timezone'].' / '.$report['now']],
            ['Schedule time', $report['schedule_time']],
            ['Scheduler mode', $report['scheduler_mode']],
            ['Scheduler allow financial', $report['scheduler_allow_financial'] ? 'yes' : 'no'],
            ['Automation armed', $report['automation_armed'] ? 'yes' : 'no'],
            ['Emergency stop', $report['emergency_stop'] ? 'yes' : 'no'],
            ['Posting allowed', $report['posting_allowed'] ? 'yes' : 'no'],
            ['Tour activation floor', $report['tour_activation_date'] ?? '(missing)'],
            ['Tour posting authorized', $report['tour_posting_authorized'] ? 'yes' : 'no (fail-closed)'],
            ['Last succeeded business date', $report['last_succeeded_business_date'] ?? '(none)'],
            ['Writes performed', $report['writes'] ? 'yes' : 'no'],
        ]);

        $this->newLine();
        $this->table(
            [
                'Date',
                'Run',
                'Recovery',
                'Normal ROI',
                'Tour ROI',
                'Comm',
                'ROI accrued/calc',
                'Lvl accrued/calc',
            ],
            array_map(fn (array $day): array => [
                $day['business_date'],
                $day['latest_run_status'],
                $day['recovery_eligibility'],
                $day['preview_normal_roi'].' / '.$day['preview_normal_roi_amount'],
                $day['preview_tour_roi'].' / '.$day['preview_tour_roi_amount'],
                $day['preview_commissions'].' / '.$day['preview_commission_amount'],
                $day['existing_roi_accrued'].'/'.$day['existing_roi_calculated'],
                $day['existing_commission_accrued'].'/'.$day['existing_commission_calculated'],
            ], $report['dates'])
        );

        $this->newLine();
        $this->table(['Unique index', 'Present'], [
            ['package_roi_purchase_date_unique', $report['unique_indexes']['roi'] ? 'yes' : 'no'],
            ['package_level_purchase_member_date_unique', $report['unique_indexes']['level'] ? 'yes' : 'no'],
            ['wallet_ledger_entries_reference_unique', $report['unique_indexes']['ledger_reference'] ? 'yes' : 'no'],
            ['wallet_ledger_entries_idempotency_key_unique', $report['unique_indexes']['ledger_idempotency'] ? 'yes' : 'no'],
        ]);

        $this->warn('Stage A only. No wallet, accrual, ledger, control, or run row was written.');
        $this->warn('Stage B posting requires separate explicit approval. Older dates than yesterday are not auto-recoverable.');

        return self::SUCCESS;
    }
}
