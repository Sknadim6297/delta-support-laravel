<?php

namespace App\Services;

use App\Models\Member;
use App\Support\WalletLedgerDirection;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\Schema;

class MemberFundSummary
{
    public function __construct(
        private readonly WithdrawalBalances $withdrawals,
        private readonly WorkingWalletFunds $workingFunds,
        private readonly WalletBalanceReconciliation $reconciliation,
    ) {
    }

    /**
     * @return array{
     *   total: array{stored:string,reserved:string,available:string,paid:string},
     *   wallets: array<string,array{stored:string,reserved:string,available:string,paid:string,credited:string,debits:string,expected:string,variance:string,reconciled:bool}>
     * }
     */
    public function forMember(Member $member): array
    {
        $reconciled = $this->reconciliation->inspect($member);
        $wallets = [];
        $totalStored = '0.0000';
        $totalReserved = '0.0000';
        $totalAvailable = '0.0000';
        $totalPaid = '0.0000';

        foreach (WithdrawalBalances::WALLETS as $wallet) {
            $snapshot = $this->withdrawals->snapshot($member, $wallet);
            $reserved = $snapshot['reserved'];

            $available = bcsub($snapshot['stored'], $reserved, 4);
            if (bccomp($available, '0', 4) === -1) {
                $available = '0.0000';
            }

            $wallets[$wallet] = [
                'stored' => $snapshot['stored'],
                'reserved' => $reserved,
                'available' => $available,
                'paid' => $snapshot['paid'],
                'credited' => $reconciled[$wallet]['credits'],
                'debits' => $reconciled[$wallet]['debits'],
                'expected' => $reconciled[$wallet]['expected'],
                'variance' => $reconciled[$wallet]['variance'],
                'reconciled' => $reconciled[$wallet]['reconciled'],
            ];

            $totalStored = bcadd($totalStored, $snapshot['stored'], 4);
            $totalReserved = bcadd($totalReserved, $reserved, 4);
            $totalAvailable = bcadd($totalAvailable, $available, 4);
            $totalPaid = bcadd($totalPaid, $snapshot['paid'], 4);
        }

        return [
            'total' => [
                'stored' => $totalStored,
                'reserved' => $totalReserved,
                'available' => $totalAvailable,
                'paid' => $totalPaid,
            ],
            'wallets' => $wallets,
        ];
    }

    private function genericWorkingHolds(Member $member): string
    {
        if (! Schema::hasTable('wallet_ledger_entries')) {
            return '0.0000';
        }

        return bcadd((string) WalletLedgerEntry::query()
            ->where('member_id', $member->member_id)
            ->where('wallet', 'working')
            ->where('direction', WalletLedgerDirection::HOLD)
            ->whereNull('withdrawal_request_id')
            ->sum('amount'), '0', 4);
    }
}
