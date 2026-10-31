<?php

namespace App\Services;

use App\Models\Member;
use App\Models\WithdrawalRequest;
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

        return bcadd((string) WithdrawalRequest::query()
            ->where('member_id', $member->member_id)
            ->where('source_wallet', $wallet)
            ->whereIn('status', self::RESERVED)
            ->sum('amount'), '0', 4);
    }

    public function available(Member $member, string $wallet): string
    {
        return bcsub($this->stored($member, $wallet), $this->reserved($member, $wallet), 4);
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
};
