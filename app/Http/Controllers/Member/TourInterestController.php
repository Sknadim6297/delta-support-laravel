<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\TourInterestService;
use App\Support\MemberContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourInterestController extends Controller
{
    public function enable(Request $request, TourInterestService $tours): RedirectResponse
    {
        $member = $this->member($request);
        $tours->expressInterest($member, $request->ip());

        return redirect()
            ->route('member.dashboard')
            ->with('tour_interest_status', TourInterestService::CONFIRMATION_MESSAGE);
    }

    public function withdraw(Request $request, TourInterestService $tours): RedirectResponse
    {
        $member = $this->member($request);
        $tours->withdrawInterest($member);

        return redirect()
            ->route('member.dashboard')
            ->with('tour_interest_status', 'Your tour interest request was withdrawn.');
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', MemberContext::id($request))
            ->firstOrFail();
    }
}
