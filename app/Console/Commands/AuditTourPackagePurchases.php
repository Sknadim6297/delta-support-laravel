<?php

namespace App\Console\Commands;

use App\Services\TourPackagePurchaseAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AuditTourPackagePurchases extends Command
{
    protected $signature = 'financial:audit-tour-purchases
        {--database= : Exact connected database name}
        {--date= : Asia/Kolkata business date for waiting/eligibility (default today)}';

    protected $description = 'Read-only DST-TOUR-003 purchase impact report including forward-only activation floor. Never credits wallets or changes flags.';

    public function handle(TourPackagePurchaseAudit $audit): int
    {
        $connected = (string) DB::connection()->getDatabaseName();
        $requested = trim((string) $this->option('database'));
        if ($requested === '' || $requested !== $connected) {
            $this->error('Refused. --database must exactly match the connected database ('.$connected.'). No change was made.');

            return self::FAILURE;
        }

        $date = $this->option('date');
        if ($date !== null && $date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $this->error('The business date is not a YYYY-MM-DD value. No change was made.');

            return self::FAILURE;
        }

        try {
            $report = $audit->report(is_string($date) && $date !== '' ? $date : null);
        } catch (RuntimeException|Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Connected database: '.$report['database']);
        $this->line('As of: '.$report['as_of'].' (wait_days='.$report['wait_days'].')');
        $this->line('Activation business date: '.($report['activation_business_date'] ?? '(missing — fail closed)'));
        $this->line($report['duration_rule']);
        $this->line('Read-only. No wallet, flag, or ledger row was written.');
        $this->newLine();

        if ($report['package'] === null) {
            $this->warn('DST-TOUR-003 package row is missing.');
        } else {
            $this->table(['Field', 'Value'], [
                ['package_type', $report['package']['type']],
                ['price_usd', $report['package']['price']],
                ['roi_eligible', (string) $report['package']['roi_eligible']],
                ['level_commission_eligible', (string) $report['package']['level_commission_eligible']],
            ]);
        }

        $this->line('Income definition: '.($report['definition'] === null ? 'MISSING' : json_encode($report['definition'])));
        $this->table(['Metric', 'Count'], [
            ['All purchases', (string) $report['totals']['all']],
            ['Approved', (string) $report['totals']['approved']],
            ['Pending', (string) $report['totals']['pending']],
            ['Rejected', (string) $report['totals']['rejected']],
            ['Other', (string) $report['totals']['other']],
            ['Waiting period', (string) $report['totals']['waiting']],
            ['Before activation floor', (string) $report['totals']['before_activation']],
            ['Window ended before activation', (string) $report['totals']['window_ended_before_activation']],
            ['Eligible today', (string) $report['totals']['eligible_today']],
        ]);

        if ($report['approved'] !== []) {
            $this->table(
                ['Purchase', 'Member', 'Original first', 'Allowed first', 'Waiting', 'Eligible today'],
                array_map(fn (array $row): array => [
                    $row['reference'],
                    $row['member_id'],
                    (string) $row['original_first_accrual'],
                    (string) $row['first_allowed_income'],
                    $row['waiting'] ? 'yes' : 'no',
                    $row['eligible_today'] ? 'yes' : 'no',
                ], $report['approved'])
            );
        }

        $this->line('Historical business dates excluded (estimate across approved purchases): '.$report['historical_business_dates_excluded_estimate']);
        $this->line('Projected daily ROI (eligible today): '.$report['potential_daily_roi']);
        $this->line('Projected daily commission pool (eligible sources today): '.$report['potential_daily_commission_pool']);
        $this->line('Potential remaining ROI liability from as-of forward: '.$report['potential_remaining_roi_liability']);
        $this->line('Potential remaining commission-pool liability from as-of forward: '.$report['potential_remaining_commission_pool_liability']);
        $this->warn('Historical backfill is forbidden. Effective activation date and terms still need written approval before financial:activate-tour-income.');

        return self::SUCCESS;
    }
}
