<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\RewardAssignment;
use App\Models\RewardCampaign;
use App\Models\RewardQualification;
use App\Services\Rewards\RewardCampaignMedia;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RewardController extends Controller
{
    public function __construct(private readonly RewardCampaignMedia $media)
    {
    }

    public function index(Request $request, TeamBusinessCalculationService $business): View
    {
        $member = Member::query()
            ->where('member_id', \App\Support\MemberContext::id($request))
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

    public function show(Request $request, RewardCampaign $campaign, TeamBusinessCalculationService $business): View
    {
        abort_unless(in_array($campaign->status, [
            RewardCampaign::SCHEDULED,
            RewardCampaign::ACTIVE,
            RewardCampaign::PAUSED,
            RewardCampaign::COMPLETED,
        ], true), 404);

        $member = Member::query()
            ->where('member_id', \App\Support\MemberContext::id($request))
            ->firstOrFail();

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

        $qualification = RewardQualification::query()
            ->where('member_id', $member->member_id)
            ->where('campaign_id', $campaign->id)
            ->with(['assignment.delivery'])
            ->first();

        return view('member.rewards.show', [
            'member' => $member,
            'campaign' => $campaign,
            'achieved' => $achieved,
            'target' => $target,
            'remaining' => $remaining,
            'percent' => number_format($percent, 2, '.', ''),
            'qualification' => $qualification,
        ]);
    }

    public function image(Request $request, RewardCampaign $campaign): BinaryFileResponse
    {
        $this->assertMemberCanViewMedia($request, $campaign);

        $path = (string) $campaign->gift_image_path;
        abort_unless($this->media->isSafeImagePath($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function video(Request $request, RewardCampaign $campaign): BinaryFileResponse
    {
        $this->assertMemberCanViewMedia($request, $campaign);

        $path = (string) $campaign->video_path;
        abort_unless($this->media->isSafeVideoPath($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    private function assertMemberCanViewMedia(Request $request, RewardCampaign $campaign): void
    {
        abort_unless(in_array($campaign->status, [
            RewardCampaign::SCHEDULED,
            RewardCampaign::ACTIVE,
            RewardCampaign::PAUSED,
            RewardCampaign::COMPLETED,
        ], true), 404);

        $memberId = (string) \App\Support\MemberContext::id($request);
        abort_unless(filled($memberId), 404);
    }
}
