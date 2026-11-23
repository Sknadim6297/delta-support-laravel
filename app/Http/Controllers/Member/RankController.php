<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Rank;
use App\Services\RankService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankController extends Controller
{
    public function show(Request $request, RankService $rankService): View
    {
        $member = $this->currentMember($request);
        $rankService->syncMemberRankAchievement($member);
        $rankData = $rankService->calculateForMember($member);
        $ranks = Rank::query()->orderBy('sort_order')->get();
        $roadmap = $ranks->map(function (Rank $rank) use ($rankData): array {
            $currentRankId = $rankData['current_rank']?->id;
            $nextRankId = $rankData['next_rank']?->id;

            return [
                'rank' => $rank,
                'state' => $rank->id === $currentRankId ? 'current' : ($rank->id === $nextRankId ? 'next' : ($currentRankId !== null && $rank->sort_order < $rankData['current_rank']->sort_order ? 'achieved' : 'locked')),
            ];
        });

        return view('member.rank.show', compact('member', 'rankData', 'roadmap'));
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', \App\Support\MemberContext::id($request))->firstOrFail();
    }
}
