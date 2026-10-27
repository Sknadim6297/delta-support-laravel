<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RefusesLegacyFinancialActions;
use App\Services\FinancialCycleService;
use Illuminate\Console\Command;

class RunFinancialCycle extends Command
{
    use RefusesLegacyFinancialActions;

    protected $signature = 'financial:cycle
        {--date= : Re-run one business date without touching the checkpoint}
        {--fail-after= : Test hook (non-production): fail after roi|commission|rank}';

    protected $description = 'Accelerated testing only: run ROI, Level Commission and Rank for the next simulated business date, then advance the checkpoint.';

    public function handle(): int
    {
        if ($this->legacyFinancialActionsAreBlocked()) {
            return self::FAILURE;
        }

        try {
            $result = FinancialCycleService::make()->run($this->option('date') ?: null, $this->option('fail-after') ?: null);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['status'] === FinancialCycleService::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}
