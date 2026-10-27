<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RefusesLegacyFinancialActions;
use App\Models\Investment;
use App\Services\LevelCommissionGenerationService;
use App\Services\BusinessDateGuard;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLevelCommission extends Command
{
    use RefusesLegacyFinancialActions;

    private const BATCH_SIZE = 500;

    protected $signature = 'commission:generate-level {--date= : Business date (YYYY-MM-DD)} {--investment= : Specific Investment ID to (re)process}';

    protected $description = 'Generate Level Commission for active investments that have not yet been processed.';

    public function handle(LevelCommissionGenerationService $service, BusinessDateGuard $businessDateGuard): int
    {
        if ($this->legacyFinancialActionsAreBlocked()) {
            return self::FAILURE;
        }

        $startedAt = microtime(true);
        $phase = 'date_resolution';
        $businessDate = null;
        $currentInvestmentId = null;
        $currentChunk = 0;
        $processed = 0;
        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $total = 0;
        $queryCount = 0;
        $skipReasons = [];

        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            $queryCount++;
            if ($query->time >= 250) {
                Log::warning('Level Commission slow query.', ['time_ms' => $query->time, 'sql' => $query->sql]);
            }
        });

        Log::info('Level Commission command started.', [
            'phase' => $phase,
            'requested_date' => $this->option('date'),
        ]);

        try {
            $businessDate = $this->option('date')
                ? CarbonImmutable::parse($this->option('date'), 'Asia/Kolkata')->startOfDay()
                : CarbonImmutable::now('Asia/Kolkata')->startOfDay();

            $businessDateGuard->assertNotFuture($businessDate);

            $phase = 'eligibility_count';
            $query = Investment::query()->where('status', 'active');

            if ($investmentId = $this->option('investment')) {
                $query->where('investment_id', $investmentId);
            }

            $total = (clone $query)->count();
            $eligibleMembers = (clone $query)->distinct()->count('member_id');
            $memberIds = (clone $query)->orderBy('id')->pluck('member_id');
            Log::info('Level Commission eligible investments counted.', [
                'phase' => $phase,
                'business_date' => $businessDate->toDateString(),
                'total_eligible_investments' => $total,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            $phase = 'rank_preparation';
            Log::info('Level Commission rank preparation started.', [
                'phase' => $phase,
                'business_date' => $businessDate->toDateString(),
                'total_eligible_investments' => $total,
            ]);
            $service->prepareForInvestments($memberIds->unique()->values(), $businessDate);
            Log::info('Level Commission rank preparation completed.', [
                'phase' => $phase,
                'business_date' => $businessDate->toDateString(),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            $phase = 'commission_generation';
            $query->orderBy('id')->chunkById(self::BATCH_SIZE, function (EloquentCollection $investments) use ($service, $businessDate, $startedAt, $total, &$currentChunk, &$currentInvestmentId, &$processed, &$generated, &$skipped, &$failed, &$queryCount, &$skipReasons): void {
                $currentChunk++;
                Log::info('Level Commission chunk started.', [
                    'business_date' => $businessDate->toDateString(),
                    'current_chunk' => $currentChunk,
                    'chunk_investments' => $investments->count(),
                    'processed_investments' => $processed,
                    'total_eligible_investments' => $total,
                ]);

                try {
                    $batch = $service->generateForInvestments($investments, $businessDate);
                    $processed += $investments->count();
                    $generated += $batch['generated'];
                    $skipped += $batch['skipped'];
                    $this->mergeReasons($skipReasons, $batch['reasons']);
                } catch (\Throwable $batchException) {
                    // The batch transaction rolled back; isolate the failure by retrying one investment at a time.
                    Log::warning('Level Commission batch failed; retrying investments individually.', [
                        'current_chunk' => $currentChunk,
                        'exception_class' => get_class($batchException),
                        'exception_message' => $batchException->getMessage(),
                    ]);
                    foreach ($investments as $investment) {
                        $currentInvestmentId = $investment->investment_id;
                        $processed++;
                        try {
                            $single = $service->generateForInvestments([$investment], $businessDate);
                            $generated += $single['generated'];
                            $skipped += $single['skipped'];
                            $this->mergeReasons($skipReasons, $single['reasons']);
                        } catch (\Throwable $exception) {
                            $failed++;
                            Log::error('Level Commission investment failed.', [
                                'investment_id' => $currentInvestmentId,
                                'failed_investments' => $failed,
                                'exception_class' => get_class($exception),
                                'exception_message' => $exception->getMessage(),
                            ]);
                            if ($failed >= 25) {
                                throw $exception;
                            }
                        }
                    }
                }

                Log::info('Level Commission generation progress.', [
                    'business_date' => $businessDate->toDateString(),
                    'phase' => 'commission_generation',
                    'current_chunk' => $currentChunk,
                    'processed_investments' => $processed,
                    'total_eligible_investments' => $total,
                    'generated_transactions' => $generated,
                    'skipped_transactions' => $skipped,
                    'skipped_by_reason' => $skipReasons,
                    'failed_investments' => $failed,
                    'query_count' => $queryCount,
                    'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                ]);
                Log::info('Level Commission chunk completed.', [
                    'business_date' => $businessDate->toDateString(),
                    'current_chunk' => $currentChunk,
                    'processed_investments' => $processed,
                    'total_eligible_investments' => $total,
                    'generated_transactions' => $generated,
                    'skipped_transactions' => $skipped,
                    'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                ]);
                $currentInvestmentId = null;
            });

            $duration = round(microtime(true) - $startedAt, 3);
            $this->info("Level commission processing completed: {$total} eligible investments across {$eligibleMembers} members, {$generated} transactions generated, {$skipped} skipped, {$failed} failed, {$queryCount} queries, {$duration} seconds, skipped by reason " . json_encode($skipReasons) . ", peak " . round(memory_get_peak_usage(true) / 1048576, 1) . ' MB.');
            Log::info('Level Commission command completed.', [
                'business_date' => $businessDate->toDateString(),
                'total_eligible_investments' => $total,
                'eligible_members' => $eligibleMembers,
                'processed_investments' => $processed,
                'generated_transactions' => $generated,
                'skipped_transactions' => $skipped,
                'skipped_by_reason' => $skipReasons,
                'failed_investments' => $failed,
                'query_count' => $queryCount,
                'chunks' => $currentChunk,
                'elapsed_seconds' => $duration,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error('Level Commission command failed.', [
                'phase' => $phase,
                'business_date' => $businessDate?->toDateString(),
                'total_eligible_investments' => $total,
                'processed_investments' => $processed,
                'current_chunk' => $currentChunk,
                'current_investment_id' => $currentInvestmentId,
                'generated_transactions' => $generated,
                'skipped_transactions' => $skipped,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 3),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
            $this->error("Level commission command stopped after {$processed} of {$total} eligible investments: {$generated} generated, {$skipped} skipped, 1 error, " . round(microtime(true) - $startedAt, 3) . ' seconds.');
            $this->error('Level commission processing failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function mergeReasons(array &$totals, array $reasons): void
    {
        foreach ($reasons as $reason => $count) {
            $totals[$reason] = ($totals[$reason] ?? 0) + $count;
        }
    }
}
