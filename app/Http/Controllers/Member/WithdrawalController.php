<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Services\FinancialControls;
use App\Services\WithdrawalWorkflow;
use App\Support\SecurityPinInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

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
            'requestsEnabled' => app(FinancialControls::class)->enabled('withdrawal_requests_enabled'),
        ]);
    }

    public function store(Request $request, WithdrawalWorkflow $workflow): RedirectResponse
    {
        SecurityPinInput::merge($request, 'security_pin');

        try {
            $workflow->submit($this->member($request), [
                'wallet' => (string) $request->input('wallet'),
                'amount' => (string) $request->input('amount'),
                'network' => (string) $request->input('network'),
                'address' => (string) $request->input('address'),
                'idempotency_key' => (string) $request->input('idempotency_key'),
                'security_pin' => (string) $request->input('security_pin', ''),
            ]);
        } catch (RuntimeException $exception) {
            abort(403, $exception->getMessage());
        }

        return redirect()->route('member.withdrawals.history')->with('status', 'The withdrawal request was reserved. No external payout was sent.');
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
