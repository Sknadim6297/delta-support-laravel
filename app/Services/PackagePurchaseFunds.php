<?php

namespace App\Services;

use App\Models\Member;

/**
 * Combined spendable funds for wallet-funded package purchases.
 * Uses Working (level) + ROI only. Activation is excluded.
 * Debit order on settle: Working first, then ROI.
 */
class PackagePurchaseFunds
{
    /** @var list<string> */
    public const WALLETS = ['working', 'roi'];

    public function __construct(
        private readonly WorkingWalletFunds $working,
        private readonly WithdrawalBalances $withdrawals,
    ) {
    }

    public function available(Member $member, string $wallet): string
    {
        if ($wallet === 'working') {
            return $this->working->available($member);
        }

        if ($wallet === 'roi') {
            $available = $this->withdrawals->available($member, 'roi');

            return bccomp($available, '0', 4) === -1 ? '0.0000' : $available;
        }

        throw new \InvalidArgumentException('Unsupported package purchase wallet.');
    }

    public function totalAvailable(Member $member): string
    {
        $total = '0.0000';
        foreach (self::WALLETS as $wallet) {
            $total = bcadd($total, $this->available($member, $wallet), 4);
        }

        return $total;
    }

    public function stored(Member $member, string $wallet): string
    {
        return $this->withdrawals->stored($member, $wallet);
    }

    public function reserved(Member $member, string $wallet): string
    {
        if ($wallet === 'working') {
            return $this->working->reserved($member);
        }

        return $this->withdrawals->reserved($member, 'roi');
    }

    /**
     * Allocate Working first, then ROI. Total must equal $amount.
     *
     * @return array{working?: string, roi?: string}
     */
    public function allocate(Member $member, string $amount): array
    {
        $remaining = bcadd($amount, '0', 4);
        if (bccomp($remaining, '0', 4) !== 1) {
            throw new \InvalidArgumentException('Purchase amount must be positive.');
        }

        $allocation = [];
        foreach (self::WALLETS as $wallet) {
            if (bccomp($remaining, '0', 4) !== 1) {
                break;
            }
            $available = $this->available($member, $wallet);
            if (bccomp($available, '0', 4) !== 1) {
                continue;
            }
            $take = bccomp($available, $remaining, 4) === 1 ? $remaining : $available;
            if (bccomp($take, '0', 4) === 1) {
                $allocation[$wallet] = $take;
                $remaining = bcsub($remaining, $take, 4);
            }
        }

        if (bccomp($remaining, '0', 4) === 1) {
            throw new \RuntimeException('Insufficient combined Working + ROI wallet balance.');
        }

        return $allocation;
    }

    public function fundingWalletLabel(array $allocation): string
    {
        $wallets = array_keys(array_filter(
            $allocation,
            fn (string $amount): bool => bccomp($amount, '0', 4) === 1
        ));

        if ($wallets === ['working']) {
            return 'working';
        }
        if ($wallets === ['roi']) {
            return 'roi';
        }

        return 'combined';
    }
}
