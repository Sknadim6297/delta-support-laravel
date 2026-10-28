<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardQualification;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RewardController extends Controller
{
    public function index(Request $request, TeamBusinessCalculationService $business): View
    {
        $member = Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();

        $campaigns = RewardCampaign::query()
            ->whereIn('status', [
                RewardCampaign::SCHEDULED,
                RewardCampaign::ACTIVE,
                RewardCampaign::PAUSED,
                RewardCampaign::COMPLETED,
            ])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        $stored = RewardQualification::query()
            ->where('member_id', $member->member_id)
            ->with(['campaign', 'assignment.delivery'])
            ->get()
            ->keyBy('campaign_id');

        $rows = $campaigns->map(function (RewardCampaign $campaign) use ($business, $member, $stored): array {
            $achieved = $business->usdtForMembers(
                [$member->member_id],
                $campaign->starts_on->toDateString(),
                $campaign->ends_on->toDateString(),
            )[$member->member_id] ?? '0.0000';
            $target = bcadd((string) $campaign->target_amount, '0', 4);
            $remaining = bccomp($achieved, $target, 4) >= 0 ? '0.0000' : bcsub($target, $achieved, 4);
            $percent = bccomp($target, '0', 4) > 0
                ? min(100, (float) bcmul(bcdiv($achieved, $target, 8), '100', 2))
                : 0;

            return [
                'campaign' => $campaign,
                'achieved' => $achieved,
                'target' => $target,
                'remaining' => $remaining,
                'percent' => number_format($percent, 2, '.', ''),
                'qualification' => $stored->get($campaign->id),
            ];
        });

        $history = RewardAssignment::query()
            ->where('member_id', $member->member_id)
            ->with(['campaign', 'delivery'])
            ->orderByDesc('id')
            ->get();

        return view('member.rewards.index', [
            'member' => $member,
            'rows' => $rows,
            'history' => $history,
        ]);
    }

    public function image(Request $request, RewardCampaign $campaign): BinaryFileResponse
    {
        abort_unless(in_array($campaign->status, [
            RewardCampaign::SCHEDULED,
            RewardCampaign::ACTIVE,
            RewardCampaign::PAUSED,
            RewardCampaign::COMPLETED,
        ], true), 404);

        $memberId = (string) $request->session()->get('member_context_id');
        $ownsHistory = RewardAssignment::query()
            ->where('member_id', $memberId)
            ->where('campaign_id', $campaign->id)
            ->exists();
        abort_unless($ownsHistory || in_array($campaign->status, [
            RewardCampaign::SCHEDULED,
            RewardCampaign::ACTIVE,
            RewardCampaign::PAUSED,
            RewardCampaign::COMPLETED,
        ], true), 404);

        $path = (string) $campaign->gift_image_path;
        abort_unless(str_starts_with($path, 'rewards/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
