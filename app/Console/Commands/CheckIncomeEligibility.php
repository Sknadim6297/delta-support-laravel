<?php

namespace App\Console\Commands;

use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckIncomeEligibility extends Command
{
    protected $signature = 'income:eligibility {--date= : Asia/Kolkata business date to preview. Defaults to today.}';

    protected $description = 'Preview ROI and level-commission eligibility for one business date. This command never writes a credit.';

    public function handle(PackageIncomeAccrual $accrual): int
    {
        $date = $this->option('date')
            ?: CarbonImmutable::now((string) config('deltasupport.package_income.timezone', 'Asia/Kolkata'))->toDateString();
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('The business date is not a YYYY-MM-DD value. No wallet was credited.');

            return 1;
        }

        $lock = Cache::lock('income-eligibility', 90);
        if (! $lock->get()) {
            $this->warn('Overlapping income eligibility check skipped. No wallet was credited.');

            return 0;
        }

        try {
            $beforeRoi = PackageRoiAccrual::query()->count();
            $beforeLevel = PackageLevelAccrual::query()->count();
            $preview = $accrual->preview($date);
            $afterRoi = PackageRoiAccrual::query()->count();
            $afterLevel = PackageLevelAccrual::query()->count();

            $this->info('Business date: '.$preview['date']);
            $this->line('Package income generation enabled: '.($preview['enabled'] ? 'yes' : 'no'));
            $this->line('Wallet ledger enabled: '.(config('deltasupport.wallet_ledger.enabled') ? 'yes' : 'no'));
            $this->line('ROI rows that would accrue: '.count($preview['roi']));
            $this->line('Level commission rows that would accrue: '.count($preview['commissions']));
            foreach ($preview['skipped'] as $reason) {
                $this->line('Skipped: '.$reason);
            }
            if ($preview['skipped'] === []) {
                $this->line('Skipped: none.');
            }
            $this->line('ROI accrual rows before/after: '.$beforeRoi.'/'.$afterRoi);
            $this->line('Level accrual rows before/after: '.$beforeLevel.'/'.$afterLevel);
            $this->line('No wallet was credited.');

            return 0;
        } finally {
            $lock->release();
        }
    }
}
