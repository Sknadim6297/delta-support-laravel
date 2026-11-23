<?php

namespace App\Services;

use App\Models\Member;
use App\Models\WithdrawalRequest;
use App\Models\WalletLedgerEntry;
use App\Models\PackagePurchaseReservation;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Support\Facades\Schema;

/**
 * Stored balance is the wallet column. Reserved balance is the gross amount
 * of open withdrawal requests. Available is stored minus reserved. Paid is
 * the gross amount already settled and removed from the stored balance.
 */
class WithdrawalBalances
{
    /** @var list<string> */
    public const RESERVED = [
        WithdrawalStatus::PENDING_REVIEW,
        WithdrawalStatus::APPROVED,
        WithdrawalStatus::PROCESSING,
    ];

    /** @var list<string> */
    public const WALLETS = ['roi', 'working', 'activation'];

    public function stored(Member $member, string $wallet): string
    {
        $column = $this->column($wallet);

        return bcadd((string) ($member->{$column} ?? '0'), '0', 4);
    }

    public function reserved(Member $member, string $wallet): string
    {
        $this->column($wallet);
        if (! Schema::hasTable('withdrawal_requests')) {
            return '0.0000';
        }

        $reserved = bcadd((string) WithdrawalRequest::query()
            ->where('member_id', $member->member_id)
            ->where('source_wallet', $wallet)
            ->whereIn('status', self::RESERVED)
            ->sum('amount'), '0', 4);

        if ($wallet === 'working') {
            if (Schema::hasTable('wallet_ledger_entries')) {
                $reserved = bcadd($reserved, (string) WalletLedgerEntry::query()
                    ->where('member_id', $member->member_id)
                    ->where('wallet', 'working')
                    ->where('direction', WalletLedgerDirection::HOLD)
                    ->whereNull('withdrawal_request_id')
                    ->sum('amount'), 4);
            }
            if (Schema::hasTable('package_purchase_reservations')) {
                $reserved = bcadd($reserved, (string) PackagePurchaseReservation::query()
                    ->where('member_id', $member->member_id)
                    ->where('wallet', 'working')
                    ->where('status', PackagePurchaseReservation::ACTIVE)
                    ->sum('reserved_amount'), 4);
            }
        }

        return bcadd($reserved, '0', 4);
    }

    public function available(Member $member, string $wallet): string
    {
        return bcsub($this->stored($member, $wallet), $this->reserved($member, $wallet), 4);
    }

    /**
     * Sum of available balances across withdrawable wallets (floored at 0 per wallet).
     *
     * @param  list<string>|null  $wallets
     */
    public function totalAvailable(Member $member, ?array $wallets = null): string
    {
        $total = '0.0000';
        foreach ($this->normalizeWallets($wallets) as $wallet) {
            $available = $this->available($member, $wallet);
            if (bccomp($available, '0', 4) === -1) {
                $available = '0.0000';
            }
            $total = bcadd($total, $available, 4);
        }

        return $total;
    }

    public function paid(Member $member, string $wallet): string
    {
        $this->column($wallet);
        if (! Schema::hasTable('withdrawal_requests')) {
            return '0.0000';
        }

        return bcadd((string) WithdrawalRequest::query()
            ->where('member_id', $member->member_id)
            ->where('source_wallet', $wallet)
            ->where('status', WithdrawalStatus::COMPLETED)
            ->sum('amount'), '0', 4);
    }

    /**
     * @return array{stored: string, reserved: string, available: string, paid: string}
     */
    public function snapshot(Member $member, string $wallet): array
    {
        $stored = $this->stored($member, $wallet);
        $reserved = $this->reserved($member, $wallet);
        $available = bcsub($stored, $reserved, 4);

        return [
            'stored' => $stored,
            'reserved' => $reserved,
            'available' => bccomp($available, '0', 4) === -1 ? '0.0000' : $available,
            'paid' => $this->paid($member, $wallet),
        ];
    }

    /**
     * @return array{stored: string, reserved: string, available: string, paid: string}
     */
    public function book(): array
    {
        $stored = '0.0000';
        if (Schema::hasTable('members')) {
            $totals = Member::query()
                ->selectRaw('COALESCE(SUM(roi_wallet_amount), 0) + COALESCE(SUM(working_wallet_amount), 0) + COALESCE(SUM(activation_wallet_amount), 0) as stored_total')
                ->first();
            $stored = bcadd((string) ($totals->stored_total ?? '0'), '0', 4);
        }

        $reserved = '0.0000';
        $paid = '0.0000';
        if (Schema::hasTable('withdrawal_requests')) {
            $reserved = bcadd((string) WithdrawalRequest::query()->whereIn('status', self::RESERVED)->sum('amount'), '0', 4);
            $paid = bcadd((string) WithdrawalRequest::query()->where('status', WithdrawalStatus::COMPLETED)->sum('amount'), '0', 4);
        }
        $available = bcsub($stored, $reserved, 4);

        return [
            'stored' => $stored,
            'reserved' => $reserved,
            'available' => bccomp($available, '0', 4) === -1 ? '0.0000' : $available,
            'paid' => $paid,
        ];
    }

    public function column(string $wallet): string
    {
        return match ($wallet) {
            'roi' => 'roi_wallet_amount',
            'working' => 'working_wallet_amount',
            'activation' => 'activation_wallet_amount',
            default => throw new \InvalidArgumentException('Unknown wallet.'),
        };
    }

    /**
     * @param  list<string>|null  $wallets
     * @return list<string>
     */
    public function normalizeWallets(?array $wallets): array
    {
        if ($wallets === null || $wallets === []) {
            return self::WALLETS;
        }

        return array_values(array_intersect(self::WALLETS, $wallets));
    }
};
