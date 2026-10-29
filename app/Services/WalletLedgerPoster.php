<?php

namespace App\Services;

use App\Models\Member;
use App\Models\WalletLedgerEntry;
use App\Support\WalletLedgerDirection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Posts one ROI, Working, or Activation movement and its balance change together.
 * Posting stays off until an explicit approval. This service is not called by
 * package accrual, withdrawals, or the income control page.
 */
class WalletLedgerPoster
{
    public function enabled(): bool
    {
        return (bool) config('deltasupport.wallet_ledger.enabled', false);
    }

    public function credit(string $memberId, string $wallet, string $amount, string $reference, string $idempotencyKey, string $reason): WalletLedgerEntry
    {
        return $this->post(WalletLedgerDirection::CREDIT, $memberId, $wallet, $amount, $reference, $idempotencyKey, $reason);
    }

    public function debit(string $memberId, string $wallet, string $amount, string $reference, string $idempotencyKey, string $reason): WalletLedgerEntry
    {
        return $this->post(WalletLedgerDirection::DEBIT, $memberId, $wallet, $amount, $reference, $idempotencyKey, $reason);
    }

    /**
     * Insert one immutable row inside the caller's transaction. This does not move a balance.
     * Package accrual, the same-member Working-to-Activation movement, and member transfers
     * call it after their own balance change. Member transfers use this path while
     * wallet_ledger.enabled stays false.
     */
    public function append(
        string $direction,
        string $memberId,
        string $wallet,
        string $amount,
        string $reference,
        string $idempotencyKey,
        string $reason,
        ?int $packagePurchaseId = null,
        ?string $openingBalance = null,
        ?string $closingBalance = null,
        ?int $withdrawalRequestId = null,
        ?string $actor = null,
        ?int $counterEntryId = null,
    ): WalletLedgerEntry {
        if (! in_array($direction, [WalletLedgerDirection::CREDIT, WalletLedgerDirection::DEBIT, WalletLedgerDirection::HOLD, WalletLedgerDirection::RELEASE, WalletLedgerDirection::REVERSAL, WalletLedgerDirection::ADJUSTMENT], true)) {
            throw new InvalidArgumentException('Unknown ledger direction.');
        }
        $this->column($wallet);
        $amount = $this->amount($amount);

        return WalletLedgerEntry::query()->create([
            'wallet' => $wallet,
            'direction' => $direction,
            'amount' => $amount,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'denomination' => 'USDT',
            'member_id' => $memberId,
            'reference' => trim($reference),
            'idempotency_key' => trim($idempotencyKey),
            'package_purchase_id' => $packagePurchaseId,
            'withdrawal_request_id' => $withdrawalRequestId,
            'actor' => 'system',
            'reason' => $reason,
        ]);
    }

    private function post(string $direction, string $memberId, string $wallet, string $amount, string $reference, string $idempotencyKey, string $reason): WalletLedgerEntry
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Wallet ledger posting is disabled. No balance was changed.');
        }

        $column = $this->column($wallet);
        $amount = $this->amount($amount);
        $reference = trim($reference);
        $idempotencyKey = trim($idempotencyKey);
        if ($reference === '' || $idempotencyKey === '') {
            throw new InvalidArgumentException('A ledger movement needs a reference and an idempotency key.');
        }

        return DB::transaction(function () use ($direction, $memberId, $wallet, $column, $amount, $reference, $idempotencyKey, $reason): WalletLedgerEntry {
            $member = Member::query()->where('member_id', $memberId)->lockForUpdate()->first();
            if (! $member) {
                throw new RuntimeException('Member '.$memberId.' has no record. No balance was changed.');
            }

            $existing = WalletLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $this->guardReplay($existing, $direction, $memberId, $wallet, $amount, $reference);

                return $existing;
            }

            $balance = bcadd((string) ($member->{$column} ?? '0'), '0', 4);
            if ($direction === WalletLedgerDirection::DEBIT && bccomp($balance, $amount, 4) < 0) {
                throw new RuntimeException('The debit would take the wallet below 0.0000. No row was stored.');
            }

            $closing = $direction === WalletLedgerDirection::CREDIT
                ? bcadd($balance, $amount, 4)
                : bcsub($balance, $amount, 4);
            $entry = WalletLedgerEntry::query()->create([
                'wallet' => $wallet,
                'direction' => $direction,
                'amount' => $amount,
                'opening_balance' => $balance,
                'closing_balance' => $closing,
                'denomination' => 'USDT',
                'member_id' => $member->member_id,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'actor' => 'system',
                'reason' => $reason,
            ]);

            $member->{$column} = $closing;
            $member->save();

            return $entry;
        });
    }

    private function column(string $wallet): string
    {
        return match ($wallet) {
            'roi' => 'roi_wallet_amount',
            'working' => 'working_wallet_amount',
            'activation' => 'activation_wallet_amount',
            default => throw new InvalidArgumentException('Unknown wallet.'),
        };
    }

    private function amount(string $amount): string
    {
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException('The amount must be a positive four-decimal USDT value.');
        }
        $normalized = bcadd($amount, '0', 4);
        if (bccomp($normalized, '0', 4) !== 1) {
            throw new InvalidArgumentException('The amount must be a positive four-decimal USDT value.');
        }

        return $normalized;
    }

    private function guardReplay(WalletLedgerEntry $existing, string $direction, string $memberId, string $wallet, string $amount, string $reference): void
    {
        $same = $existing->direction === $direction
            && $existing->member_id === $memberId
            && $existing->wallet === $wallet
            && $existing->reference === $reference
            && bccomp((string) $existing->amount, $amount, 4) === 0;
        if (! $same) {
            throw new RuntimeException('This idempotency key was already used for a different movement. No balance was changed.');
        }
    }
}
