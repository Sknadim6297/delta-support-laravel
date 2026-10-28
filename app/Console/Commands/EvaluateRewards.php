<?php

namespace App\Console\Commands;

use App\Models\RewardEvaluationRun;
use App\Services\Rewards\RewardEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EvaluateRewards extends Command
{
    protected $signature = 'rewards:evaluate {--campaign= : Limit the run to one campaign id}';

    protected $description = 'Recalculate reward eligibility from approved USDT package business. This does not credit a wallet.';

    public function handle(RewardEligibilityService $eligibility): int
    {
        $businessDate = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $run = RewardEvaluationRun::query()->create([
            'business_date' => $businessDate,
            'status' => RewardEvaluationRun::RUNNING,
            'message' => 'Reward evaluation started. No wallet will be credited.',
            'started_at' => now(),
        ]);

        try {
            $campaign = $this->option('campaign');
            $campaignId = is_numeric($campaign) ? (int) $campaign : null;
            $result = $eligibility->evaluate($campaignId);
            $skipped = $result['skipped'] === true;

            $run->forceFill([
                'status' => $skipped ? RewardEvaluationRun::SKIPPED : RewardEvaluationRun::COMPLETED,
                'members_reviewed' => $result['members'],
                'selected_count' => $result['selected'],
                'finished_at' => now(),
                'message' => $skipped
                    ? 'Overlapping reward run skipped. No rows were changed and no wallet was credited.'
                    : 'Reward evaluation completed. No wallet was credited.',
            ])->save();

            if ($skipped) {
                $this->warn($run->message);

                return self::SUCCESS;
            }

            $this->info('Business date: '.$businessDate);
            $this->info('Members reviewed: '.$result['members']);
            $this->info('Selected gifts: '.$result['selected']);
            $this->line('No wallet was credited.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => RewardEvaluationRun::FAILED,
                'finished_at' => now(),
                'message' => 'Reward evaluation failed. '.$exception->getMessage(),
            ])->save();
            $this->error($run->message);
            report($exception);

            return self::FAILURE;
        }
    }
}
