<?php

namespace App\Services;

use App\Models\Member;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Support\Facades\Schema;

/**
 * Available Working Wallet funds are the stored balance minus withdrawal
 * reservations and any hold that is not already represented by those reservations.
 */
class WorkingWalletFunds
{
    /** @var list<string> */
    private const RESERVED = [
        WithdrawalStatus::PENDING_REVIEW,
        WithdrawalStatus::APPROVED,
        WithdrawalStatus::PROCESSING,
    ];

    public function balance(Member $member): string
    {
        return bcadd((string) ($member->working_wallet_amount ?? '0'), '0', 4);
    }

    public function reserved(Member $member): string
    {
        $requests = '0.0000';
        if (Schema::hasTable('withdrawal_requests')) {
            $requests = bcadd((string) WithdrawalRequest::query()
                ->where('member_id', $member->member_id)
                ->where('source_wallet', 'working')
                ->whereIn('status', self::RESERVED)
                ->sum('amount'), '0', 4);
        }

        $holds = '0.0000';
        if (Schema::hasTable('wallet_ledger_entries')) {
            $holds = bcadd((string) WalletLedgerEntry::query()
                ->where('member_id', $member->member_id)
                ->where('wallet', 'working')
                ->where('direction', WalletLedgerDirection::HOLD)
                ->whereNull('withdrawal_request_id')
                ->sum('amount'), '0', 4);
        }

        return bcadd($requests, $holds, 4);
    }

    public function available(Member $member): string
    {
        $available = bcsub($this->balance($member), $this->reserved($member), 4);

        return bccomp($available, '0', 4) === -1 ? '0.0000' : $available;
    }
}
