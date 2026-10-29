<?php

namespace App\Services\Rewards;

use App\Models\Member;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardDelivery;
use App\Models\RewardQualification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RewardEligibilityService
{
    public function __construct(
        private readonly TeamBusinessCalculationService $business,
        private readonly RewardFulfillmentService $fulfillment,
    ) {}

    /**
     * @return array{skipped: bool, members: int, selected: int}
     */
    public function evaluate(?int $campaignId = null): array
    {
        // Released when the run finishes. A crashed process keeps the lock for 840 seconds.
        $lock = Cache::lock('rewards-evaluate', 840);
        if (! $lock->get()) {
            return ['skipped' => true, 'members' => 0, 'selected' => 0];
        }

        try {
            $members = 0;
            Member::query()
                ->orderBy('id')
                ->select(['id', 'member_id', 'member_name', 'sponsor_id'])
                ->chunkById(100, function (Collection $chunk) use (&$members, $campaignId): void {
                    $this->evaluateMembers($chunk, $campaignId);
                    $members += $chunk->count();
                });

            return [
                'skipped' => false,
                'members' => $members,
                'selected' => RewardAssignment::query()->where('status', RewardAssignment::SELECTED)->count(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    public function evaluateMembers(Collection $members, ?int $campaignId = null): void
    {
        $campaigns = RewardCampaign::query()
            ->when($campaignId !== null, fn ($query) => $query->whereKey($campaignId))
            ->whereIn('status', [RewardCampaign::ACTIVE, RewardCampaign::COMPLETED])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();

        if ($campaigns->isEmpty()) {
            foreach ($members as $member) {
                $this->selectLatest($member->member_id);
            }

            return;
        }

        $ids = $members->pluck('member_id')->all();
        foreach ($campaigns as $campaign) {
            $amounts = $this->business->usdtForMembers(
                $ids,
                $campaign->starts_on->toDateString(),
                $campaign->ends_on->toDateString(),
            );
            foreach ($members as $member) {
                $this->syncQualification($campaign, $member, $amounts[$member->member_id] ?? '0.0000');
            }
        }

        foreach ($members as $member) {
            $this->selectLatest($member->member_id);
        }
    }

    private function syncQualification(RewardCampaign $campaign, Member $member, string $achieved): void
    {
        $existing = RewardQualification::query()
            ->where('campaign_id', $campaign->id)
            ->where('member_id', $member->member_id)
            ->with('assignment.delivery')
            ->first();

        if ($existing?->assignment && $this->committed($existing->assignment)) {
            return;
        }

        $target = bcadd((string) $campaign->target_amount, '0', 4);
        $met = bccomp($achieved, $target, 4) >= 0;
        $status = $met
            ? RewardQualification::QUALIFIED
            : (bccomp($achieved, '0', 4) > 0 ? RewardQualification::IN_PROGRESS : RewardQualification::NOT_ELIGIBLE);

        if ($existing && $existing->status === RewardQualification::SELECTED && $met) {
            $status = RewardQualification::SELECTED;
        }

        if ($existing && $existing->status === RewardQualification::SUPERSEDED && $met) {
            $status = RewardQualification::SUPERSEDED;
        }

        $payload = [
            'member_name' => $member->member_name,
            'sponsor_id' => $member->sponsor_id,
            'achieved_amount' => $achieved,
            'target_amount' => $target,
            'status' => $status,
        ];

        if ($met && ($existing === null || $existing->qualified_at === null)) {
            $payload['qualified_at'] = now();
        }

        if ($existing) {
            $existing->fill($payload)->save();
        } else {
            $existing = RewardQualification::query()->create($payload + [
                'campaign_id' => $campaign->id,
                'member_id' => $member->member_id,
            ]);
        }

        $assignment = $existing->assignment;
        if (! $met && $assignment && $assignment->status === RewardAssignment::SELECTED && ! $this->committed($assignment)) {
            if ($assignment->delivery && in_array($assignment->delivery->status, [RewardDelivery::PENDING, RewardDelivery::APPROVED], true)) {
                $this->fulfillment->transition($assignment->delivery, [
                    'status' => RewardDelivery::CANCELLED,
                    'internal_notes' => 'Team business is below the campaign target.',
                ], null, true);
            }
            $assignment->forceFill(['status' => RewardAssignment::CANCELLED])->save();
            $existing->forceFill(['status' => $status])->save();
        }
    }

    private function selectLatest(string $memberId): void
    {
        DB::transaction(function () use ($memberId): void {
            $qualifications = RewardQualification::query()
                ->where('member_id', $memberId)
                ->whereIn('status', [
                    RewardQualification::QUALIFIED,
                    RewardQualification::SELECTED,
                    RewardQualification::SUPERSEDED,
                ])
                ->lockForUpdate()
                ->with(['campaign', 'assignment.delivery'])
                ->get()
                ->filter(function (RewardQualification $qualification): bool {
                    return in_array($qualification->campaign?->status, [RewardCampaign::ACTIVE, RewardCampaign::COMPLETED], true)
                        && bccomp((string) $qualification->achieved_amount, (string) $qualification->target_amount, 4) >= 0;
                })
                ->sort(function (RewardQualification $left, RewardQualification $right): int {
                    $dates = strcmp(
                        $left->campaign->starts_on->toDateString(),
                        $right->campaign->starts_on->toDateString(),
                    );

                    return $dates !== 0 ? $dates : $left->campaign_id <=> $right->campaign_id;
                })
                ->values();

            if ($qualifications->isEmpty()) {
                return;
            }

            $winner = $qualifications->last();
            $current = RewardAssignment::query()
                ->where('member_id', $memberId)
                ->where('status', RewardAssignment::SELECTED)
                ->lockForUpdate()
                ->with(['delivery', 'campaign'])
                ->first();

            if ($current && ($this->committed($current) || $current->campaign?->status === RewardCampaign::PAUSED)) {
                if ((int) $current->campaign_id !== (int) $winner->campaign_id) {
                    $current->forceFill([
                        'conflict_campaign_id' => $winner->campaign_id,
                        'conflict_note' => 'A later qualifying campaign cannot replace a gift that is already committed.',
                    ])->save();
                }

                return;
            }

            if ($current && (int) $current->campaign_id === (int) $winner->campaign_id) {
                $winner->forceFill(['status' => RewardQualification::SELECTED])->save();
                $this->markOthersSuperseded($winner);

                return;
            }

            if (! $this->stockAvailable($winner)) {
                $winner->forceFill(['status' => RewardQualification::QUALIFIED])->save();

                return;
            }

            if ($current) {
                $this->supersede($current, $winner);
            }

            $this->assign($winner);
        });
    }

    private function stockAvailable(RewardQualification $qualification): bool
    {
        $stock = $qualification->campaign?->stock_quantity;
        if ($stock === null) {
            return true;
        }

        $used = RewardAssignment::query()
            ->where('campaign_id', $qualification->campaign_id)
            ->where('status', RewardAssignment::SELECTED)
            ->where('member_id', '!=', $qualification->member_id)
            ->lockForUpdate()
            ->count();

        return $used < (int) $stock;
    }

    private function supersede(RewardAssignment $assignment, RewardQualification $winner): void
    {
        if ($this->committed($assignment)) {
            return;
        }

        $assignment->forceFill([
            'status' => RewardAssignment::SUPERSEDED,
            'superseded_at' => now(),
            'superseded_by_campaign_id' => $winner->campaign_id,
        ])->save();

        $delivery = $assignment->delivery;
        if ($delivery && in_array($delivery->status, [RewardDelivery::PENDING, RewardDelivery::APPROVED], true)) {
            $this->fulfillment->transition($delivery, [
                'status' => RewardDelivery::CANCELLED,
                'internal_notes' => 'Superseded by a later qualifying campaign.',
            ], null, true);
        }

        $assignment->qualification?->forceFill(['status' => RewardQualification::SUPERSEDED])->save();
    }

    private function assign(RewardQualification $qualification): void
    {
        $campaign = $qualification->campaign;
        $assignment = RewardAssignment::query()->updateOrCreate(
            [
                'member_id' => $qualification->member_id,
                'campaign_id' => $qualification->campaign_id,
            ],
            [
                'qualification_id' => $qualification->id,
                'gift_name' => $campaign->gift_name,
                'status' => RewardAssignment::SELECTED,
                'selected_at' => now(),
                'superseded_at' => null,
                'superseded_by_campaign_id' => null,
                'conflict_campaign_id' => null,
                'conflict_note' => null,
            ],
        );

        $delivery = RewardDelivery::query()->firstOrCreate(
            ['assignment_id' => $assignment->id],
            ['status' => RewardDelivery::PENDING],
        );
        if ($delivery->status === RewardDelivery::CANCELLED) {
            $this->fulfillment->reopenCancelled($delivery);
        }

        $qualification->forceFill(['status' => RewardQualification::SELECTED])->save();
        $this->markOthersSuperseded($qualification);
    }

    private function markOthersSuperseded(RewardQualification $qualification): void
    {
        RewardQualification::query()
            ->where('member_id', $qualification->member_id)
            ->where('id', '!=', $qualification->id)
            ->whereIn('status', [RewardQualification::QUALIFIED, RewardQualification::SELECTED])
            ->update(['status' => RewardQualification::SUPERSEDED]);
    }

    private function committed(RewardAssignment $assignment): bool
    {
        return in_array($assignment->delivery?->status, RewardDelivery::COMMITTED, true);
    }
}
