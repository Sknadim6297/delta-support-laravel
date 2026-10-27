<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function requestForm(Request $request): View
    {
        $member = $this->member($request);
        $settings = WithdrawalSetting::current();

        return view('member.withdrawals.request', [
            'member' => $member,
            'settings' => $settings,
            'wallets' => $settings->configuredWallets(),
            'networks' => $settings->configuredNetworks(),
        ]);
    }

    public function history(Request $request): View
    {
        $member = $this->member($request);

        return view('member.withdrawals.history', [
            'member' => $member,
            'withdrawals' => WithdrawalRequest::query()
                ->where('member_id', $member->member_id)
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, string $withdrawalRequest): View
    {
        $member = $this->member($request);
        $record = WithdrawalRequest::query()
            ->where('member_id', $member->member_id)
            ->whereKey($withdrawalRequest)
            ->firstOrFail();
        $record->load(['statusEvents' => fn ($query) => $query->orderBy('id')]);

        return view('member.withdrawals.show', [
            'member' => $member,
            'withdrawal' => $record,
        ]);
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }
}
