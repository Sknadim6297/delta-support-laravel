<?php

namespace App\Console\Commands;

use App\Services\PackageIncomeEligibilityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AuditPackageIncomeEligibility extends Command
{
    protected $signature = 'financial:eligibility {--date= : Asia/Kolkata business date YYYY-MM-DD}';

    protected $description = 'Explain why each package purchase is or is not eligible for ROI on one business date. Writes nothing.';

    public function handle(PackageIncomeEligibilityAudit $audit): int
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $date = $this->option('date') ?: CarbonImmutable::now($timezone)->toDateString();
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Provide a YYYY-MM-DD business date.');

            return self::FAILURE;
        }

        $report = $audit->forDate($date);

        $this->table(['Check', 'Value'], [
            ['Business date', $report['date'].' '.$report['timezone']],
            ['Wait days', (string) $report['wait_days']],
            ['Configured income packages', implode(', ', $report['configured_packages']) ?: '(none)'],
            ['Package catalog rows', (string) $report['package_rows']],
            ['Package purchases total', (string) $report['purchase_total']],
            ['Approved with reviewed_at', (string) $report['purchase_approved']],
            ['Pending purchases', (string) $report['purchase_pending']],
            ['Other non-approved purchases', (string) $report['purchase_other']],
            ['Legacy investments table rows', (string) $report['investment_total']],
            ['Eligible for this date', (string) count($report['eligible'])],
            ['Ineligible for this date', (string) count($report['ineligible'])],
        ]);

        if ($report['summary'] !== []) {
            $this->newLine();
            $this->info('Ineligibility / eligibility reason counts');
            $summaryRows = [];
            foreach ($report['summary'] as $reason => $count) {
                $summaryRows[] = [$reason, (string) $count];
            }
            $this->table(['Reason', 'Count'], $summaryRows);
        }

        if ($report['eligible'] !== []) {
            $this->newLine();
            $this->info('Eligible purchases');
            $this->table(
                ['Purchase', 'Member', 'Package', 'Amount', 'First accrual', 'Reason'],
                array_map(fn (array $row): array => [
                    $row['purchase_id'],
                    $row['member_id'],
                    $row['package_code'],
                    $row['amount'],
                    $row['first_accrual'],
                    $row['reason'],
                ], $report['eligible'])
            );
        }

        if ($report['ineligible'] !== []) {
            $this->newLine();
            $this->info('Ineligible purchases');
            foreach ($report['ineligible'] as $row) {
                $this->line(
                    '#'.$row['purchase_id']
                    .' '.$row['member_id']
                    .' '.$row['package_code']
                    .' status='.$row['status']
                    .' reviewed='.($row['reviewed_at'] !== '' ? $row['reviewed_at'] : 'none')
                    .' first='.($row['first_accrual'] !== '' ? $row['first_accrual'] : 'none')
                    .' :: '.$row['reason']
                );
            }
        }

        if ($report['purchase_total'] === 0 && $report['investment_total'] > 0) {
            $this->warn('Legacy investments exist, but financial:daily credits package purchases only. Legacy roi:generate stays off the scheduler.');
        }

        if ($report['purchase_total'] === 0 && $report['investment_total'] === 0) {
            $this->warn('No package purchases and no investments were found. Zero eligible ROI is expected.');
        }

        $this->line('This command does not credit wallets and does not change financial controls.');

        return self::SUCCESS;
    }
}
