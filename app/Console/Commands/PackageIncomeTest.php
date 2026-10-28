<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomeCalculationPreview;
use App\Services\PackageIncomeIsolatedTest;
use App\Services\WalletBalanceReconciliation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PackageIncomeTest extends Command
{
    protected $signature = 'package-income:test
        {action : preview, run, status, prepare, or commission-chain}
        {--date= : One Asia/Kolkata business date (YYYY-MM-DD)}
        {--confirm= : Type isolated-test before prepare, commission-chain, or run}';

    protected $description = 'Preview or accrue package income on delta_support_income_test only. This is not a scheduled payout.';

    public function handle(PackageIncomeIsolatedTest $isolated, PackageIncomeCalculationPreview $preview, WalletBalanceReconciliation $reconciliation): int
    {
        if (app()->environment('production')) {
            $this->error('Refused. This command is not available in production. No wallet was credited.');

            return self::FAILURE;
        }

        $action = (string) $this->argument('action');
        try {
            $isolated->assertConfigured();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        if (in_array($action, ['prepare', 'run', 'commission-chain'], true) && $this->option('confirm') !== PackageIncomeIsolatedTest::CONFIRM) {
            $this->error('Refused. Repeat the command with --confirm=isolated-test. No wallet was credited.');

            return 1;
        }

        try {
            return match ($action) {
                'prepare' => $this->prepare($isolated),
                'preview' => $this->preview($isolated, $preview),
                'run' => $this->runDate($isolated),
                'commission-chain' => $this->chain($isolated),
                'status' => $this->status($isolated, $reconciliation),
                default => $this->unknown(),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }
    }

    private function prepare(PackageIncomeIsolatedTest $isolated): int
    {
        $result = $isolated->prepare();
        $this->info('Copied records into '.$result['database'].'. Main database rows were not changed.');
        foreach ($result['copied'] as $table => $count) {
            $this->line($table.': '.$count);
        }

        return 0;
    }

    private function preview(PackageIncomeIsolatedTest $isolated, PackageIncomeCalculationPreview $preview): int
    {
        $date = (string) $this->option('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Pass --date=YYYY-MM-DD. No preview was calculated.');

            return 1;
        }
        $isolated->useTestConnection();
        $report = $preview->forDate($date);
        $this->info($report['stored_notice']);
        $this->line('Database: '.PackageIncomeIsolatedTest::DATABASE);
        $this->line('Date: '.$report['date']);
        $this->line('Pending ROI: '.$report['totals']['pending_roi'].' USDT');
        $this->line('Payable commission: '.$report['totals']['payable_commission'].' USDT');
        $this->line('Theoretical pool: '.$report['totals']['theoretical_pool'].' USDT');
        foreach ($report['rows'] as $row) {
            if (($row['pending_roi'] ?? '0.0000') === '0.0000' && ($row['payable_commission'] ?? '0.0000') === '0.0000' && ! in_array('Eligible for ROI', $row['labels'], true)) {
                continue;
            }
            $this->line($row['member_id'].' '.$row['package_code'].' ROI '.$row['pending_roi'].' payable '.$row['payable_commission'].' '.implode(', ', $row['labels']));
        }

        return $report['valid'] ? 0 : 1;
    }

    private function runDate(PackageIncomeIsolatedTest $isolated): int
    {
        $date = (string) $this->option('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Pass --date=YYYY-MM-DD. No wallet was credited.');

            return 1;
        }
        $isolated->useTestConnection();
        config(['deltasupport.package_income.enabled' => true]);
        $exit = $this->call('package-income:accrue', ['--date' => $date]);
        $this->status($isolated, app(WalletBalanceReconciliation::class));

        return $exit;
    }

    private function chain(PackageIncomeIsolatedTest $isolated): int
    {
        $result = $isolated->commissionChain();
        $this->info($result['created']
            ? 'Disposable chain created. Copied sponsor ids were not changed.'
            : 'Disposable chain already exists. Copied sponsor ids were not changed.');
        $this->line('Hills buyer: '.$result['buyer']);
        $this->line('Coastal buyer: '.$result['coastal']);

        return 0;
    }

    private function status(PackageIncomeIsolatedTest $isolated, WalletBalanceReconciliation $reconciliation): int
    {
        $isolated->useTestConnection();
        $wallets = Member::query()->selectRaw('COALESCE(SUM(roi_wallet_amount), 0) as roi_total, COALESCE(SUM(working_wallet_amount), 0) as working_total')->first();
        $variance = 0;
        Member::query()->orderBy('id')->chunkById(100, function ($members) use (&$variance, $reconciliation): void {
            foreach ($members as $member) {
                $report = $reconciliation->inspect($member);
                if (bccomp($report['roi']['variance'], '0', 4) !== 0 || bccomp($report['working']['variance'], '0', 4) !== 0) {
                    $variance++;
                }
            }
        });
        $this->line('Database: '.DB::connection()->getDatabaseName());
        $this->line('ROI wallet sum: '.bcadd((string) $wallets->roi_total, '0', 4));
        $this->line('Working wallet sum: '.bcadd((string) $wallets->working_total, '0', 4));
        $this->line('ROI accrual rows: '.PackageRoiAccrual::query()->count());
        $this->line('Level accrual rows: '.PackageLevelAccrual::query()->count());
        $this->line('Ledger rows: '.WalletLedgerEntry::query()->count());
        $this->line('Members with ROI or Working variance: '.$variance);
        foreach (PackageIncomeRun::query()->orderByDesc('id')->limit(10)->get() as $run) {
            $this->line('#'.$run->id.' '.$run->status.' '.($run->business_date?->toDateString() ?? 'none').' ROI '.$run->credited_roi.' commission '.$run->credited_commissions.' '.$run->message);
        }

        return 0;
    }

    private function unknown(): int
    {
        $this->error('Unknown action. Use preview, run, status, prepare, or commission-chain.');

        return 1;
    }
}
