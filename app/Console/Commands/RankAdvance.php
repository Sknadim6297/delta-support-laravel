<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Services\BusinessDateGuard;
use App\Services\RankService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RankAdvance extends Command
{
    private const ACHIEVEMENT_INSERT_BATCH_SIZE = 500;

    protected $signature = 'rank:advance {--member-id= : Limit the one-step advancement to a specific member} {--date= : Evaluate ranks as of this business date}';

    protected $description = 'Advance each qualifying member by exactly one rank using the real rank rules.';

    public function handle(RankService $rankService, BusinessDateGuard $businessDateGuard): int
    {
        $startedAt = microtime(true);
        Log::info('Scheduled rank advancement started.');

        try {
            $dateOption = $this->option('date');
            $businessDate = $dateOption ? CarbonImmutable::parse($dateOption, 'Asia/Kolkata') : null;
            if ($businessDate !== null) {
                $businessDateGuard->assertNotFuture($businessDate);
            }

            return $this->advance($rankService, $startedAt, $businessDate);
        } catch (\Throwable $exception) {
            $duration = round(microtime(true) - $startedAt, 3);
            Log::error('Scheduled rank advancement failed.', [
                'duration_seconds' => $duration,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
            $this->error("Rank advancement failed: 1 error after {$duration} seconds. " . $exception->getMessage());

            throw $exception;
        }
    }

    private function advance(RankService $rankService, float $startedAt, ?CarbonImmutable $businessDate): int
    {
        $memberIds = $this->memberIds();
        if ($memberIds === []) {
            $this->info('Rank reconciliation completed: 0 members evaluated, 0 rank updates, 0 achievements, 0 unchanged, 0 errors, ' . round(microtime(true) - $startedAt, 3) . ' seconds.');
            return self::SUCCESS;
        }

        $members = Member::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'member_name', 'rank_id']);
        $memberIds = $members->pluck('member_id')->all();
        $ranks = Rank::query()->orderBy('sort_order')->get()->keyBy('id');
        $activeRanks = $ranks->where('is_active', true)->values();
        $calculations = $rankService->calculateForMembers($memberIds, null, $businessDate?->toDateString());
        $nextRankByCurrentRank = $this->nextRanks($activeRanks);
        $achievementKeys = RankAchievement::query()
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'rank_id'])
            ->mapWithKeys(fn (RankAchievement $achievement): array => [$this->achievementKey($achievement->member_id, $achievement->rank_id) => true]);
        $advancedOnBusinessDate = collect();
        if ($businessDate !== null) {
            $dateStart = $businessDate->startOfDay()->toDateTimeString();
            $dateEnd = $businessDate->addDay()->startOfDay()->toDateTimeString();
            $advancedOnBusinessDate = RankAchievement::query()
                ->whereIn('member_id', $memberIds)
                ->where('achieved_at', '>=', $dateStart)
                ->where('achieved_at', '<', $dateEnd)
                ->pluck('member_id')
                ->flip();
        }
        $rankUpdates = [];
        $newAchievements = [];
        $changed = 0;

        foreach ($members as $member) {
            $memberId = $member->member_id;
            if (isset($advancedOnBusinessDate[$memberId])) {
                continue;
            }

            $calculation = $calculations[$memberId];
            $fullTeamBusiness = (string) $calculation['full_team_business'];
            $qualifiedRank = $calculation['current_rank'];
            $currentRank = $member->rank_id !== null ? $ranks->get($member->rank_id) : null;
            $nextRank = $currentRank ? ($nextRankByCurrentRank[$currentRank->id] ?? null) : $activeRanks->first();
            $desiredRankId = $currentRank?->id;

            if ($currentRank === null) {
                if ($nextRank !== null && bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) >= 0) {
                    $desiredRankId = $nextRank->id;
                    $this->queueAchievement($newAchievements, $achievementKeys, $member, $nextRank, $fullTeamBusiness, $businessDate);
                } else {
                    $desiredRankId = null;
                }
            } elseif ($nextRank !== null && bccomp($fullTeamBusiness, (string) $nextRank->required_full_team_business, 4) >= 0) {
                $desiredRankId = $nextRank->id;
                $this->queueAchievement($newAchievements, $achievementKeys, $member, $nextRank, $fullTeamBusiness, $businessDate);
            } elseif ($qualifiedRank !== null && $qualifiedRank->id !== $currentRank->id) {
                $desiredRankId = $qualifiedRank->id;
            } elseif ($qualifiedRank === null) {
                $desiredRankId = null;
            } else {
                $desiredRankId = $qualifiedRank->id;
            }

            if ($desiredRankId !== $member->rank_id) {
                $rankUpdates[(string) ($member->rank_id ?? 'null')][(string) ($desiredRankId ?? 'null')][] = $memberId;
                $changed++;
            }
        }

        DB::transaction(function () use ($rankUpdates, $newAchievements): void {
            foreach ($rankUpdates as $currentRankId => $updatesByRankId) {
                foreach ($updatesByRankId as $rankId => $ids) {
                    $query = Member::query()->whereIn('member_id', $ids);
                    if ($currentRankId === 'null') {
                        $query->whereNull('rank_id');
                    } else {
                        $query->where('rank_id', (int) $currentRankId);
                    }

                    $query->update([
                        'rank_id' => $rankId === 'null' ? null : (int) $rankId,
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($newAchievements !== []) {
                foreach (array_chunk($newAchievements, self::ACHIEVEMENT_INSERT_BATCH_SIZE) as $achievementBatch) {
                    RankAchievement::query()->insertOrIgnore($achievementBatch);
                }
            }
        });

        $this->info(sprintf(
            'Rank reconciliation completed: %d members evaluated, %d rank updates, %d achievements, %d unchanged, 0 errors, %.3f seconds, %.1f MB peak memory.',
            count($members),
            $changed,
            count($newAchievements),
            count($members) - $changed,
            microtime(true) - $startedAt,
            memory_get_peak_usage(true) / 1048576
        ));
        Log::info('Scheduled rank advancement finished.', [
            'business_date' => $businessDate?->toDateString(),
            'processed' => count($members),
            'rank_updates' => $changed,
            'achievements' => count($newAchievements),
            'unchanged_members' => count($members) - $changed,
            'duration_seconds' => round(microtime(true) - $startedAt, 3),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        return self::SUCCESS;
    }

    private function queueAchievement(
        array &$newAchievements,
        Collection &$achievementKeys,
        Member $member,
        Rank $rank,
        string $fullTeamBusiness,
        ?CarbonImmutable $businessDate,
    ): void
    {
        $key = $this->achievementKey($member->member_id, $rank->id);
        if (! isset($achievementKeys[$key])) {
            $now = now('Asia/Kolkata');
            $newAchievements[] = [
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'rank_id' => $rank->id,
                'qualifying_business_amount' => $fullTeamBusiness,
                'achieved_at' => $businessDate?->endOfDay() ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $achievementKeys[$key] = true;
        }
    }

    private function achievementKey(string $memberId, int $rankId): string
    {
        return $memberId . ':' . $rankId;
    }

    private function nextRanks(Collection $activeRanks): array
    {
        $nextRanks = [];
        foreach ($activeRanks as $index => $rank) {
            $nextRanks[$rank->id] = $activeRanks->get($index + 1);
        }

        return $nextRanks;
    }

    private function memberIds(): array
    {
        $selected = trim((string) $this->option('member-id'));

        if ($selected !== '') {
            return [$selected];
        }

        return Member::query()->pluck('member_id')->all();
    }
}
