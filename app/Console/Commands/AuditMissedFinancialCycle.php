<?php

namespace App\Console\Commands;

use App\Models\PackageIncomeRun;
use App\Models\PackageRoiAccrual;
use App\Services\FinancialControls;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AuditMissedFinancialCycle extends Command
{
    protected $signature = 'financial:missed
        {--days=14 : How many Asia/Kolkata business dates to inspect, ending yesterday}
        {--detailed : List each eligible purchase, amount, and existing accrual status}';

    protected $description = 'Report business dates without a succeeded daily cycle and preview eligible ROI without writing wallets.';

    public function handle(PackageIncomeAccrual $accrual, FinancialControls $controls): int
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $scheduleTime = (string) config('deltasupport.package_income.schedule_time', '00:10');
        $days = max(1, min(90, (int) $this->option('days')));
        $yesterday = CarbonImmutable::now($timezone)->subDay()->startOfDay();
        $detailed = (bool) $this->option('detailed');

        $this->line('Timezone: '.$timezone);
        $this->line('Configured schedule time: '.$scheduleTime);
        $this->line('Automation armed: '.($controls->automationArmed() ? 'yes' : 'no'));
        $this->line('Emergency stop: '.($controls->emergencyStopped() ? 'yes' : 'no'));
        $this->line('Posting allowed: '.($controls->automaticPostingAllowed() ? 'yes' : 'no'));
        $this->newLine();

        $summary = [];
        $detailRows = [];
        $totalAmount = '0.0000';
        $totalEligible = 0;

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $yesterday->subDays($offset);
            $businessDate = $date->toDateString();
            $status = PackageIncomeRun::query()
                ->whereDate('business_date', $businessDate)
                ->latest('id')
                ->value('status');
            $status = is_string($status) ? $status : 'missing';
            if ($status === PackageIncomeRun::SUCCEEDED) {
                continue;
            }

            $preview = $accrual->previewReal($businessDate);
            $dayAmount = '0.0000';
            foreach ($preview['roi'] as $row) {
                $dayAmount = bcadd($dayAmount, (string) $row['amount'], 4);
                $totalAmount = bcadd($totalAmount, (string) $row['amount'], 4);
                $totalEligible++;
                if ($detailed) {
                    $existing = PackageRoiAccrual::query()
                        ->where('package_purchase_id', (int) $row['purchase_id'])
                        ->whereDate('business_date', $businessDate)
                        ->first();
                    $detailRows[] = [
                        $businessDate,
                        $row['purchase_id'],
                        $row['member_id'],
                        $row['package_code'],
                        $row['amount'],
                        $existing?->status ?? 'none',
                        $existing?->reference ?? '',
                        $this->recoveryHint($businessDate, $yesterday->toDateString()),
                    ];
                }
            }

            $summary[] = [
                $businessDate,
                $status,
                (string) count($preview['roi']),
                $dayAmount,
                (string) count($preview['commissions']),
                $this->recoveryHint($businessDate, $yesterday->toDateString()),
            ];
        }

        if ($summary === []) {
            $this->info('No missed or unfinished business dates were found in the last '.$days.' days.');

            return self::SUCCESS;
        }

        $this->table(
            ['Business date', 'Latest status', 'Eligible ROI', 'Expected ROI USDT', 'Eligible commissions', 'Safe recovery'],
            $summary
        );
        $this->line('Total unfinished eligible ROI rows: '.$totalEligible);
        $this->line('Total expected ROI amount (USDT): '.$totalAmount);

        if ($detailed && $detailRows !== []) {
            $this->newLine();
            $this->table(
                ['Business date', 'Purchase ID', 'Member', 'Package', 'Amount', 'Accrual status', 'Reference', 'Safe recovery'],
                $detailRows
            );
        }

        $this->warn('This command does not credit wallets. financial:recover can safely retry yesterday only. Older dates need an approved recovery.');

        return self::SUCCESS;
    }

    private function recoveryHint(string $businessDate, string $yesterday): string
    {
        if ($businessDate === $yesterday) {
            return 'financial:recover (when armed)';
        }

        return 'Requires approved recovery. Do not backfill without approval.';
    }
}
