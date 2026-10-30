<?php

namespace App\Console\Commands;

use App\Models\PackageIncomeRun;
use App\Services\FinancialControls;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RecoverDailyFinancialCycle extends Command
{
    protected $signature = 'financial:recover';

    protected $description = 'Retry today after 00:10, or yesterday after a failed run. Successful dates are not credited again.';

    public function handle(FinancialControls $controls): int
    {
        if (! $controls->automaticPostingAllowed()) {
            $this->warn('Financial automation is not posting. No wallet was credited.');

            return self::SUCCESS;
        }

        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $yesterday = $now->subDay()->toDateString();

        if ($this->latestStatus($yesterday) === PackageIncomeRun::FAILED) {
            $this->line('Retrying the failed run for '.$yesterday.'.');
            Artisan::call('financial:daily', ['--date' => $yesterday, '--recover-yesterday' => true]);
            $this->output->write(Artisan::output());
        }

        if ($now->format('H:i') < '00:10') {
            $this->line('Today\'s scheduled run has not started. No wallet was credited.');

            return self::SUCCESS;
        }

        if ($this->latestStatus($today) === PackageIncomeRun::SUCCEEDED) {
            $this->line('Today already succeeded. No wallet was credited again.');

            return self::SUCCESS;
        }

        Artisan::call('financial:daily', ['--date' => $today]);
        $this->output->write(Artisan::output());

        return self::SUCCESS;
    }

    private function latestStatus(string $date): ?string
    {
        $status = PackageIncomeRun::query()
            ->whereDate('business_date', $date)
            ->latest('id')
            ->value('status');

        return is_string($status) ? $status : null;
    }
}
