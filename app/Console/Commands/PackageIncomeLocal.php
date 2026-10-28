<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\LocalDatabaseGuard;
use App\Services\LocalTestCleanup;
use App\Services\LocalTestMarker;
use App\Services\LocalTestRoster;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeRunRecorder;
use App\Services\WalletBalanceReconciliation;
use Illuminate\Console\Command;
use Throwable;

class PackageIncomeLocal extends Command
{
    protected $signature = 'package-income:local
        {action : seed, preview, run, status, or cleanup}
        {--date= : One Asia/Kolkata business date (YYYY-MM-DD)}
        {--confirm= : local-delta-support for seed and run; delete-local-test-data for cleanup}';

    protected $description = 'Create and accrue local test package income on delta_support only. Production generation stays disabled.';

    public function handle(
        LocalDatabaseGuard $guard,
        LocalTestMarker $marker,
        LocalTestRoster $roster,
        LocalTestCleanup $cleanup,
        PackageIncomeAccrual $accrual,
        PackageIncomeRunRecorder $recorder,
        WalletBalanceReconciliation $reconciliation,
    ): int {
        $action = (string) $this->argument('action');
        if (in_array($action, ['seed', 'run'], true) && $this->option('confirm') !== LocalDatabaseGuard::CONFIRM) {
            $this->error('Refused. Repeat the command with --confirm=local-delta-support. No wallet was credited.');

            return 1;
        }

        try {
            $guard->assertOriginalLocal();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        try {
            return match ($action) {
                'seed' => $this->seed($roster),
                'preview' => $this->preview($marker, $accrual),
                'run' => $this->runDate($marker, $accrual, $recorder, $reconciliation),
                'status' => $this->status($marker, $reconciliation),
                'cleanup' => $this->cleanup($cleanup),
                default => $this->unknown(),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }
    }

    private function seed(LocalTestRoster $roster): int
    {
        $result = $roster->seed();
        $this->info('Local test members are in delta_support. Existing sponsor ids were not changed.');
        $this->line('Members: '.$result['members']);
        $this->line('Purchases: '.$result['purchases']);
        $this->line('Ram: '.$result['ram']);
        $this->line('Sam: '.$result['sam']);
        $this->line('Rahul: '.$result['rahul']);
        $this->line('First accrual date: '.$result['first_date']);
        $this->line('Login password for these test members: '.LocalTestRoster::PASSWORD);

        return 0;
    }

    private function preview(LocalTestMarker $marker, PackageIncomeAccrual $accrual): int
    {
        $date = $this->dateOption();
        if ($date === null) {
            return 1;
        }
        $report = $accrual->previewPurchases($date, $marker->purchaseIds());
        $roi = $this->sum($report['roi']);
        $commission = $this->sum($report['commissions']);
        $this->info('Preview only. These figures are not stored credits.');
        $this->line('Database: '.LocalDatabaseGuard::DATABASE);
        $this->line('Date: '.$report['date']);
        $this->line('Local test ROI: '.$roi.' USDT');
        $this->line('Local test commission: '.$commission.' USDT');
        $this->line('ROI rows: '.count($report['roi']));
        $this->line('Commission rows: '.count($report['commissions']));
        foreach ($report['roi'] as $row) {
            if (in_array($row['member_id'], $this->namedIds(), true)) {
                $this->line($row['member_id'].' '.$row['package_code'].' ROI '.$row['amount']);
            }
        }

        return 0;
    }

    private function runDate(LocalTestMarker $marker, PackageIncomeAccrual $accrual, PackageIncomeRunRecorder $recorder, WalletBalanceReconciliation $reconciliation): int
    {
        $date = $this->dateOption();
        if ($date === null) {
            return 1;
        }
        $ids = $marker->purchaseIds();
        try {
            $result = $accrual->accruePurchases($date, $ids);
        } catch (Throwable $exception) {
            $recorder->record($date, 'failed', 0, 0, 'LOCAL TEST '.$exception->getMessage());
            throw $exception;
        }
        $recorder->record(
            $date,
            'succeeded',
            $result['credited_roi'],
            $result['credited_commissions'],
            'LOCAL TEST ROI credits: '.$result['credited_roi'].'. Commission credits: '.$result['credited_commissions'].'. Existing members were excluded.'
        );
        $this->info('LOCAL TEST ROI credits: '.$result['credited_roi'].'. Commission credits: '.$result['credited_commissions'].'.');
        $this->status($marker, $reconciliation);

        return 0;
    }

    private function status(LocalTestMarker $marker, WalletBalanceReconciliation $reconciliation): int
    {
        $ids = $marker->memberIds();
        $variance = 0;
        Member::query()->whereIn('member_id', $ids === [] ? ['__none__'] : $ids)->orderBy('id')->chunkById(100, function ($members) use (&$variance, $reconciliation): void {
            foreach ($members as $member) {
                $report = $reconciliation->inspect($member);
                if (bccomp($report['roi']['variance'], '0', 4) !== 0 || bccomp($report['working']['variance'], '0', 4) !== 0) {
                    $variance++;
                }
            }
        });
        $real = Member::query()
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', 'not like', '%'.LocalTestMarker::EMAIL_DOMAIN);
            })
            ->selectRaw('COUNT(*) as members, COALESCE(SUM(roi_wallet_amount), 0) as roi_total, COALESCE(SUM(working_wallet_amount), 0) as working_total')
            ->first();
        $this->line('Database: '.LocalDatabaseGuard::DATABASE);
        $this->line('Production generation remains disabled.');
        $this->line('Existing members: '.$real->members.' ROI '.$real->roi_total.' Working '.$real->working_total);
        $this->line('Local test ROI rows: '.PackageRoiAccrual::query()->whereIn('package_purchase_id', $marker->purchaseIds() ?: [0])->count());
        $this->line('Local test commission rows: '.PackageLevelAccrual::query()->whereIn('package_purchase_id', $marker->purchaseIds() ?: [0])->count());
        $this->line('Local test members with variance: '.$variance);
        foreach (Member::query()->whereIn('email', ['ram@local-test.invalid', 'sam@local-test.invalid', 'rahul@local-test.invalid'])->orderBy('member_name')->get() as $member) {
            $this->line($member->member_name.' '.$member->member_id.' ROI '.$member->roi_wallet_amount.' Working '.$member->working_wallet_amount);
        }

        return 0;
    }

    private function cleanup(LocalTestCleanup $cleanup): int
    {
        $report = $cleanup->inspect();
        $this->info('Dry run. The preferred cleanup is restoring storage/app/backups/delta_support-2026-10-28-before-local-income.sql.');
        foreach ($report as $key => $value) {
            $this->line($key.': '.$value);
        }
        if ($this->option('confirm') !== 'delete-local-test-data') {
            $this->line('No record was deleted. Repeat with --confirm=delete-local-test-data to delete the marked rows.');

            return $report['blocked'] === '' ? 0 : 1;
        }
        if ($report['blocked'] !== '') {
            $this->error($report['blocked'].' No record was deleted.');

            return 1;
        }
        $after = $cleanup->delete();
        $this->info('Marked local test rows were deleted. Existing members were kept.');
        $this->line('Members remaining in the local test set: '.$after['members']);

        return 0;
    }

    private function dateOption(): ?string
    {
        $date = (string) $this->option('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Pass --date=YYYY-MM-DD. No wallet was credited.');

            return null;
        }

        return $date;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function sum(array $rows): string
    {
        $total = '0.0000';
        foreach ($rows as $row) {
            $total = bcadd($total, $row['amount'], 4);
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function namedIds(): array
    {
        return Member::query()
            ->whereIn('email', ['ram@local-test.invalid', 'sam@local-test.invalid'])
            ->pluck('member_id')
            ->all();
    }

    private function unknown(): int
    {
        $this->error('Unknown action. Use seed, preview, run, status, or cleanup.');

        return 1;
    }
}
