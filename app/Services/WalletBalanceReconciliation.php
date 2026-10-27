<?php

namespace App\Services;

use App\Models\ActivationWalletTransaction;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RoiTransaction;

/**
 * Read-only comparison of member balance columns with their ledger rows.
 *
 * Future credits must insert one ledger row and increment the matching column
 * by the same four-decimal amount inside one transaction after lockForUpdate.
 * Future debits must insert one ledger row and decrement that column by the
 * same amount, and must refuse a result below 0.0000. This class never writes
 * a balance. ROI and Working debits have no ledger yet, so those wallets are
 * compared with their credit tables only. Activation debits already use
 * activation_wallet_transactions.type = debit. A working-wallet transfer into
 * the activation wallet is not a level-commission row, so that legacy movement
 * remains visible as a working-wallet variance until a working debit ledger exists.
 */
class WalletBalanceReconciliation
{
    /**
     * @return array<string, array<string, string>>
     */
    public function inspect(Member $member): array
    {
        $roiCredits = $this->money(RoiTransaction::query()->where('member_id', $member->member_id)->sum('income_amount'));
        $workingCredits = $this->money(LevelCommissionTransaction::query()->where('member_id', $member->member_id)->sum('income_amount'));
        $activationCredits = $this->money(ActivationWalletTransaction::query()
            ->where('member_id', $member->member_id)
            ->where('type', 'credit')
            ->sum('amount'));
        $activationDebits = $this->money(ActivationWalletTransaction::query()
            ->where('member_id', $member->member_id)
            ->where('type', 'debit')
            ->sum('amount'));

        return [
            'roi' => $this->line((string) $member->roi_wallet_amount, $roiCredits, '0.0000', false, 'ROI Wallet debit history is not stored. This credits-only comparison is not a completed reconciliation.'),
            'working' => $this->line((string) $member->working_wallet_amount, $workingCredits, '0.0000', false, 'Working Wallet debit history is not stored. Transfers out of this wallet are not in the commission ledger, so this is not a completed reconciliation.'),
            'activation' => $this->line((string) $member->activation_wallet_amount, $activationCredits, $activationDebits, true, 'Activation Wallet is compared with its own credit and debit rows. A difference is reported and is not corrected.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function notices(): array
    {
        $notices = [
            'ROI Wallet and Working Wallet cannot be fully reconciled because debit history is not stored.',
            'Wallet balances are shown as USDT. Historical package purchases and invoices keep their stored USD denomination. USD and USDT totals are not added together.',
        ];

        $duplicates = $this->duplicateReferenceCounts();
        foreach ($duplicates as $ledger => $count) {
            if ($count > 0) {
                $notices[] = $count.' duplicate '.$ledger.' reference'.($count === 1 ? '' : 's').' were found.';
            }
        }

        $negative = $this->negativeBalanceCount();
        if ($negative > 0) {
            $notices[] = $negative.' member balance'.($negative === 1 ? '' : 's').' are negative. They were not changed.';
        }

        return $notices;
    }

    /**
     * @return array{roi: int, level_income: int, activation: int}
     */
    public function duplicateReferenceCounts(): array
    {
        return [
            'roi' => $this->duplicateCount(RoiTransaction::query(), 'reference'),
            'level_income' => $this->duplicateCount(LevelCommissionTransaction::query(), 'reference'),
            'activation' => $this->duplicateCount(ActivationWalletTransaction::query(), 'reference'),
        ];
    }

    public function negativeBalanceCount(): int
    {
        return Member::query()
            ->where(function ($query): void {
                $query->where('roi_wallet_amount', '<', 0)
                    ->orWhere('working_wallet_amount', '<', 0)
                    ->orWhere('activation_wallet_amount', '<', 0);
            })
            ->count();
    }

    /**
     * @return array{balance: string, credits: string, debits: string, expected: string, variance: string, negative: bool, reconciled: bool, warning: string}
     */
    private function line(string $balance, string $credits, string $debits, bool $debitLedgerExists, string $warning): array
    {
        $balance = $this->money($balance);
        $expected = bcsub($credits, $debits, 4);
        $variance = bcsub($balance, $expected, 4);
        $negative = bccomp($balance, '0', 4) < 0;
        $matched = bccomp($variance, '0', 4) === 0 && ! $negative;
        if (! $debitLedgerExists) {
            $warning .= bccomp($variance, '0', 4) === 0
                ? ' The stored balance currently equals recorded credits.'
                : ' The stored balance differs from recorded credits.';
        } elseif (! $matched) {
            $warning .= ' The stored balance does not match recorded credits minus debits.';
        }

        return [
            'balance' => $balance,
            'credits' => $credits,
            'debits' => $debits,
            'expected' => $expected,
            'variance' => $variance,
            'negative' => $negative,
            'reconciled' => $debitLedgerExists && $matched,
            'warning' => $warning,
        ];
    }

    private function duplicateCount($query, string $column): int
    {
        return (int) (clone $query)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.0000';
        }

        return bcadd((string) $value, '0', 4);
    }
}
