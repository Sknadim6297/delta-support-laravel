<?php

namespace App\Services\Rewards;

use App\Models\AdminActivityLog;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardDelivery;
use App\Models\RewardQualification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardCampaignLifecycle
{
    public function __construct(
        private readonly RewardEligibilityService $eligibility,
        private readonly RewardFulfillmentService $fulfillment,
    ) {}

    public function publish(RewardCampaign $campaign, User $user): RewardCampaign
    {
        if (! in_array($campaign->status, [RewardCampaign::DRAFT, RewardCampaign::SCHEDULED, RewardCampaign::PAUSED], true)) {
            throw ValidationException::withMessages([
                'status' => 'This campaign cannot be published from its current status.',
            ]);
        }

        $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $start = $campaign->starts_on->toDateString();
        $end = $campaign->ends_on->toDateString();
        $status = $start > $today
            ? RewardCampaign::SCHEDULED
            : ($end < $today ? RewardCampaign::COMPLETED : RewardCampaign::ACTIVE);

        $campaign->forceFill([
            'status' => $status,
            'published_at' => $campaign->published_at ?? now(),
        ])->save();
        $this->log($campaign, $user, 'publish');

        if (in_array($status, [RewardCampaign::ACTIVE, RewardCampaign::COMPLETED], true)) {
            $this->eligibility->evaluate($campaign->id);
        }

        return $campaign->refresh();
    }

    public function pause(RewardCampaign $campaign, User $user): RewardCampaign
    {
        if (! in_array($campaign->status, [RewardCampaign::ACTIVE, RewardCampaign::SCHEDULED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only an active or scheduled campaign can be paused.',
            ]);
        }

        $campaign->forceFill(['status' => RewardCampaign::PAUSED])->save();
        $this->log($campaign, $user, 'pause');

        return $campaign;
    }

    public function close(RewardCampaign $campaign, User $user): RewardCampaign
    {
        if (! in_array($campaign->status, [RewardCampaign::ACTIVE, RewardCampaign::PAUSED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only an active or paused campaign can be closed.',
            ]);
        }

        $campaign->forceFill(['status' => RewardCampaign::COMPLETED])->save();
        $this->log($campaign, $user, 'close');
        $this->eligibility->evaluate($campaign->id);

        return $campaign->refresh();
    }

    public function cancel(RewardCampaign $campaign, User $user): RewardCampaign
    {
        if (! in_array($campaign->status, [RewardCampaign::DRAFT, RewardCampaign::SCHEDULED, RewardCampaign::ACTIVE, RewardCampaign::PAUSED], true)) {
            throw ValidationException::withMessages([
                'status' => 'This campaign cannot be cancelled.',
            ]);
        }

        DB::transaction(function () use ($campaign, $user): void {
            $campaign->forceFill(['status' => RewardCampaign::CANCELLED])->save();
            $assignments = RewardAssignment::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', RewardAssignment::SELECTED)
                ->lockForUpdate()
                ->with('delivery')
                ->get();

            $kept = [];
            foreach ($assignments as $assignment) {
                $delivery = $assignment->delivery;
                if ($delivery && in_array($delivery->status, RewardDelivery::COMMITTED, true)) {
                    $assignment->forceFill([
                        'conflict_note' => 'The campaign was cancelled after this gift was committed. Review it manually.',
                    ])->save();
                    $kept[] = $assignment->qualification_id;
                    continue;
                }

                if ($delivery && in_array($delivery->status, [RewardDelivery::PENDING, RewardDelivery::APPROVED], true)) {
                    $this->fulfillment->transition($delivery, [
                        'status' => RewardDelivery::CANCELLED,
                        'internal_notes' => 'Campaign cancelled.',
                    ], $user);
                }

                $assignment->forceFill(['status' => RewardAssignment::CANCELLED])->save();
                $assignment->qualification?->forceFill(['status' => RewardQualification::CANCELLED])->save();
            }

            RewardQualification::query()
                ->where('campaign_id', $campaign->id)
                ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
                ->update(['status' => RewardQualification::CANCELLED]);
        });

        $this->log($campaign, $user, 'cancel');

        return $campaign->refresh();
    }

    private function log(RewardCampaign $campaign, User $user, string $action): void
    {
        AdminActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'reward_campaign_'.$action,
            'subject' => (string) $campaign->id,
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'title' => $campaign->title,
                'status' => $campaign->status,
            ],
            'row_count' => 1,
        ]);
    }
}
