<?php

    namespace App\Console\Commands;

    use App\Console\Commands\Concerns\RefusesLegacyFinancialActions;
    use App\Services\RoiGenerationService;
    use App\Services\BusinessDateGuard;
    use Carbon\CarbonImmutable;
    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\Log;

    class GenerateRoi extends Command
    {
        use RefusesLegacyFinancialActions;

        protected $signature = 'roi:generate {--date= : Business date (YYYY-MM-DD) for a safe manual run}';

        protected $description = 'Generate daily ROI for eligible investments.';

        public function handle(RoiGenerationService $roiGenerationService, BusinessDateGuard $businessDateGuard): int
        {
            if ($this->legacyFinancialActionsAreBlocked()) {
                return self::FAILURE;
            }

            $startedAt = microtime(true);
            Log::info('Scheduled ROI generation started.');

            $date = $this->option('date')
                ? CarbonImmutable::parse($this->option('date'), RoiGenerationService::TIMEZONE)
                : CarbonImmutable::now(RoiGenerationService::TIMEZONE);

            try {
                $businessDateGuard->assertNotFuture($date);
            } catch (\RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $result = $roiGenerationService->generateForDate($date->startOfDay());

            $duration = round(microtime(true) - $startedAt, 3);
            $this->info("ROI processing completed for {$date->toDateString()}: {$result['eligible_investments']} eligible investments, {$result['generated']} generated, {$result['expired']} expired, {$result['skipped']} skipped, {$result['failed']} errors, {$duration} seconds.");
            Log::info('Scheduled ROI generation finished.', $result + ['duration_seconds' => $duration, 'business_date' => $date->toDateString()]);

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

    }
