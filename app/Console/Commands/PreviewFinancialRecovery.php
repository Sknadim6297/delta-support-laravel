<?php

namespace App\Console\Commands;

use App\Services\FinancialRecoveryPreview;
use Illuminate\Console\Command;

class PreviewFinancialRecovery extends Command
{
    protected $signature = 'financial:recovery-preview
        {--days=14 : Asia/Kolkata lookback window including today}
        {--detailed : Member/date/package/amount line breakdown}';

    protected $description = 'Stage A read-only recovery preview. Never writes wallets, accruals, ledgers, controls, or run rows.';

    public function handle(FinancialRecoveryPreview $preview): int
    {
        $detailed = (bool) $this->option('detailed');
        $report = $preview->build((int) $this->option('days'), $detailed);

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
            ['DST-ROI-002 terms', json_encode($report['package_terms']['DST-ROI-002'] ?? null)],
            ['DST-TOUR-003 terms', json_encode($report['package_terms']['DST-TOUR-003'] ?? null)],
            ['Last succeeded business date', $report['last_succeeded_business_date'] ?? '(none)'],
            ['Writes performed', $report['writes'] ? 'yes' : 'no'],
        ]);

        if ($report['generation_blockers'] !== []) {
            $this->newLine();
            $this->warn('Why income is not generating under current gates:');
            foreach ($report['generation_blockers'] as $blocker) {
                $this->line('- '.$blocker);
            }
        }

        $this->newLine();
        $this->table(
            [
                'Date',
                'Run',
                'Recovery',
                'Exp $100 ROI',
                'Exp $1000 ROI',
                'Gate ROI',
                'Exp Comm',
                'Gate Comm',
                'ROI a/c',
                'Lvl a/c',
            ],
            array_map(fn (array $day): array => [
                $day['business_date'],
                $day['latest_run_status'],
                $day['recovery_eligibility'],
                $day['expected_normal_roi'].' / '.$day['expected_normal_roi_amount'],
                $day['expected_tour_roi'].' / '.$day['expected_tour_roi_amount'],
                $day['actual_gate_roi'].' / '.$day['actual_gate_roi_amount'],
                $day['expected_commissions'].' / '.$day['expected_commission_amount'],
                $day['actual_gate_commissions'].' / '.$day['actual_gate_commission_amount'],
                $day['existing_roi_accrued'].'/'.$day['existing_roi_calculated'],
                $day['existing_commission_accrued'].'/'.$day['existing_commission_calculated'],
            ], $report['dates'])
        );

        $totals = $report['totals'];
        $this->newLine();
        $this->table(['Total', 'Value'], [
            ['Expected DST-ROI-002 ($100) ROI amount', $totals['expected_roi_100_amount']],
            ['Expected DST-TOUR-003 ($1000) ROI amount', $totals['expected_roi_1000_amount']],
            ['Expected ROI amount (all)', $totals['expected_roi_amount']],
            ['Expected commission amount', $totals['expected_commission_amount']],
            ['Missing ROI rows', (string) $totals['missing_roi']],
            ['Calculated-only ROI rows', (string) $totals['calculated_roi']],
            ['Posted ROI rows', (string) $totals['posted_roi']],
            ['Missing commission rows', (string) $totals['missing_commission']],
            ['Calculated-only commission rows', (string) $totals['calculated_commission']],
            ['Posted commission rows', (string) $totals['posted_commission']],
            ['Duplicate ROI key groups', (string) $totals['duplicate_roi_keys']],
            ['Duplicate commission key groups', (string) $totals['duplicate_commission_keys']],
        ]);

        if ($detailed && $report['roi_lines'] !== []) {
            $this->newLine();
            $this->info('ROI lines (member / date / package / amount)');
            $this->table(
                ['Date', 'Member', 'Purchase', 'Package', 'Amount', 'Gates', 'Status', 'Ledger', 'Block'],
                array_map(fn (array $line): array => [
                    $line['business_date'],
                    $line['member_id'],
                    $line['purchase_id'],
                    $line['package_code'],
                    $line['amount'],
                    $line['allowed_under_current_gates'],
                    $line['record_status'],
                    $line['ledger'],
                    $line['block_reason'],
                ], $report['roi_lines'])
            );
        }

        if ($detailed && $report['commission_lines'] !== []) {
            $this->newLine();
            $this->info('Commission lines (recipient / date / package / level / amount)');
            $this->table(
                ['Date', 'Member', 'From', 'Purchase', 'Package', 'Lvl', 'Amount', 'Gates', 'Status', 'Ledger'],
                array_map(fn (array $line): array => [
                    $line['business_date'],
                    $line['member_id'],
                    $line['from_member_id'],
                    $line['purchase_id'],
                    $line['package_code'],
                    $line['level'],
                    $line['amount'],
                    $line['allowed_under_current_gates'],
                    $line['record_status'],
                    $line['ledger'],
                ], $report['commission_lines'])
            );
        }

        $this->newLine();
        $this->table(['Unique index', 'Present'], [
            ['package_roi_purchase_date_unique', $report['unique_indexes']['roi'] ? 'yes' : 'no'],
            ['package_level_purchase_member_date_unique', $report['unique_indexes']['level'] ? 'yes' : 'no'],
            ['wallet_ledger_entries_reference_unique', $report['unique_indexes']['ledger_reference'] ? 'yes' : 'no'],
            ['wallet_ledger_entries_idempotency_key_unique', $report['unique_indexes']['ledger_idempotency'] ? 'yes' : 'no'],
        ]);

        if ($report['future_schedule_simulation'] !== []) {
            $this->newLine();
            $this->info('Future schedule simulation (verify mode — would_execute=no)');
            $this->table(
                ['At', 'Command', 'Due', 'Would execute', 'Skip'],
                array_map(fn (array $row): array => [
                    $row['at'],
                    $row['command'],
                    $row['due'] ? 'yes' : 'no',
                    $row['would_execute'] ? 'yes' : 'no',
                    $row['skip'],
                ], $report['future_schedule_simulation'])
            );
        }

        $this->warn('Stage A only. No wallet, accrual, ledger, control, or run row was written.');
        $this->warn('Expected Tour amounts assume activation floor + hypothetical posting authorization; production Tour auth remains unchanged.');
        $this->warn('Stage B requires separate approval, database backup confirmation, and business-rules confirmation.');

        return self::SUCCESS;
    }
}
