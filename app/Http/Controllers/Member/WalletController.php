<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use App\Services\FinancialControls;
use App\Services\FinancialHistoryQuery;
use App\Services\PackageIncomeActivationGate;
use App\Services\ReferralLevelEligibility;
use App\Services\WithdrawalBalances;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function overview(
        Request $request,
        WithdrawalBalances $balances,
        FinancialControls $controls,
        PackageIncomeActivationGate $activation,
    ): View {
        $member = $this->member($request);
        $roiCount = RoiTransaction::query()->where('member_id', $member->member_id)->count()
            + PackageRoiAccrual::query()->where('member_id', $member->member_id)->count();
        $levelCount = LevelCommissionTransaction::query()->where('member_id', $member->member_id)->count()
            + PackageLevelAccrual::query()->where('member_id', $member->member_id)->count();
        $accruedRoi = PackageRoiAccrual::query()
            ->where('member_id', $member->member_id)
            ->where('status', 'accrued')
            ->count();
        $accruedLevel = PackageLevelAccrual::query()
            ->where('member_id', $member->member_id)
            ->where('status', 'accrued')
            ->count();
        $pendingCalculated = PackageRoiAccrual::query()
            ->where('member_id', $member->member_id)
            ->where('status', 'calculated')
            ->count()
            + PackageLevelAccrual::query()
                ->where('member_id', $member->member_id)
                ->where('status', 'calculated')
                ->count();

        return view('member.wallets.overview', [
            'member' => $member,
            'wallets' => [
                'roi' => $balances->snapshot($member, 'roi'),
                'working' => $balances->snapshot($member, 'working'),
                'activation' => $balances->snapshot($member, 'activation'),
            ],
            'roiCount' => $roiCount,
            'levelCount' => $levelCount,
            'incomeStatus' => $this->incomeStatusMessage(
                $member,
                $controls,
                $activation,
                $accruedRoi,
                $accruedLevel,
                $pendingCalculated
            ),
        ]);
    }

    private function incomeStatusMessage(
        Member $member,
        FinancialControls $controls,
        PackageIncomeActivationGate $activation,
        int $accruedRoi,
        int $accruedLevel,
        int $pendingCalculated,
    ): string {
        if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid')) {
            return 'Local test data. Production payout generation remains disabled.';
        }
        if ($controls->emergencyStopped()) {
            return 'Wallet balances are USDT. Automatic income posting is paused by the financial emergency stop. Stored accrued records still appear below.';
        }
        if (! $controls->automaticPostingAllowed()) {
            return 'Wallet balances are USDT. Automatic income posting is not armed. Stored accrued records still appear below.';
        }
        if ($pendingCalculated > 0) {
            return 'Wallet balances are USDT. Some income rows are calculated and waiting for wallet credit. Accrued ROI and level income already stored are shown below.';
        }
        if ($accruedRoi > 0 || $accruedLevel > 0) {
            $tourNote = $activation->tourPostingAuthorized()
                ? 'Standard and authorized Tour package income may post when eligible.'
                : 'Standard package income may post when eligible. Premium Tour posting remains unauthorized until written approval.';

            return 'Wallet balances are USDT. Accrued ROI and level income records are shown below. '.$tourNote;
        }

        if (! $activation->tourPostingAuthorized() && $activation->isActivated(PackageIncomeActivationGate::TOUR_CODE)) {
            return 'Wallet balances are USDT. No accrued income records yet. Standard package income may post when eligible. Premium Tour posting is not authorized yet (activation floor alone is insufficient).';
        }

        return 'Wallet balances are USDT. No accrued ROI or level income records are stored for this account yet.';
    }

    public function wallet(Request $request, string $wallet, WithdrawalBalances $balances): View
    {
        abort_unless(in_array($wallet, ['roi', 'working'], true), 404);
        $member = $this->member($request);
        $column = $wallet === 'roi' ? 'roi_wallet_amount' : 'working_wallet_amount';

        return view('member.wallets.show', [
            'member' => $member,
            'wallet' => $wallet,
            'balance' => $member->{$column},
            'figures' => $balances->snapshot($member, $wallet),
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
            ->where('member_id', \App\Support\MemberContext::id($request))
            ->firstOrFail();
    }
}
