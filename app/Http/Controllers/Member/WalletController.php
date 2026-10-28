<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use App\Services\FinancialHistoryQuery;
use App\Services\ReferralLevelEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function overview(Request $request): View
    {
        $member = $this->member($request);

        return view('member.wallets.overview', [
            'member' => $member,
            'roiCount' => RoiTransaction::query()->where('member_id', $member->member_id)->count()
                + PackageRoiAccrual::query()->where('member_id', $member->member_id)->count(),
            'levelCount' => LevelCommissionTransaction::query()->where('member_id', $member->member_id)->count()
                + PackageLevelAccrual::query()->where('member_id', $member->member_id)->count(),
        ]);
    }

    public function wallet(Request $request, string $wallet): View
    {
        abort_unless(in_array($wallet, ['roi', 'working'], true), 404);
        $member = $this->member($request);
        $column = $wallet === 'roi' ? 'roi_wallet_amount' : 'working_wallet_amount';

        return view('member.wallets.show', [
            'member' => $member,
            'wallet' => $wallet,
            'balance' => $member->{$column},
            'count' => $wallet === 'roi'
                ? RoiTransaction::query()->where('member_id', $member->member_id)->count()
                    + PackageRoiAccrual::query()->where('member_id', $member->member_id)->count()
                : LevelCommissionTransaction::query()->where('member_id', $member->member_id)->count()
                    + PackageLevelAccrual::query()->where('member_id', $member->member_id)->count(),
        ]);
    }

    public function roiHistory(Request $request, FinancialHistoryQuery $history): View
    {
        $member = $this->member($request);

        return view('member.finance.history', [
            'member' => $member,
            'kind' => 'roi',
            'rows' => $history->roi($member->member_id, (string) $request->query('q', '')),
        ]);
    }

    public function levelHistory(Request $request, FinancialHistoryQuery $history): View
    {
        $member = $this->member($request);

        return view('member.finance.history', [
            'member' => $member,
            'kind' => 'level',
            'rows' => $history->levelIncome($member->member_id, (string) $request->query('q', '')),
        ]);
    }

    public function eligibility(Request $request, ReferralLevelEligibility $eligibility): View
    {
        $member = $this->member($request);
        $summary = $eligibility->forMember($member->member_id);
        $directs = Member::query()
            ->where('sponsor_id', $member->member_id)
            ->where('member_id', '!=', $member->member_id)
            ->orderBy('member_id')
            ->paginate(10, ['member_id', 'member_name']);
        $qualifyingIds = $eligibility->qualifyingIdsAmong($directs->pluck('member_id')->all());

        return view('member.finance.eligibility', [
            'member' => $member,
            'directCount' => $eligibility->directCount($member->member_id),
            'qualifyingCount' => $summary['qualifying_direct_count'],
            'unlockedLevels' => $summary['unlocked_levels'],
            'status' => $eligibility->statusLabel($summary['unlocked_levels']),
            'referralUrl' => route('member.register', ['sponsor_id' => $member->member_id]),
            'directs' => $directs,
            'qualifyingIds' => $qualifyingIds,
        ]);
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }
}
