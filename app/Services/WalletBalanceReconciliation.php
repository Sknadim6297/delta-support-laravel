<?php

namespace App\Services;

use App\Models\ActivationWalletTransaction;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use App\Models\WalletLedgerEntry;
use App\Support\WalletLedgerDirection;

/**
 * Read-only comparison of member balance columns with their ledger rows.
 *
 * Future credits must insert one ledger row and increment the matching column
 * by the same four-decimal amount inside one transaction after lockForUpdate.
 * Future debits must insert one ledger row and decrement that column by the
 * same amount, and must refuse a result below 0.0000. This class never writes
 * a balance. ROI and Working debits are the debit and reversal rows in
 * wallet_ledger_entries. Credits stay in the legacy and package accrual tables
 * so a ledger credit written beside an accrual is not counted twice.
 * No historical debit is created by this class. Activation debits already use
 * activation_wallet_transactions.type = debit.
 */
class WalletBalanceReconciliation
{
    /**
     * @return array<string, array<string, string>>
     */
    public function inspect(Member $member): array
    {
        $roiCredits = bcadd(
            $this->money(RoiTransaction::query()->where('member_id', $member->member_id)->sum('income_amount')),
            $this->money(PackageRoiAccrual::query()->where('member_id', $member->member_id)->sum('amount')),
            4
        );
        $workingCredits = bcadd(
            $this->money(LevelCommissionTransaction::query()->where('member_id', $member->member_id)->sum('income_amount')),
            $this->money(PackageLevelAccrual::query()->where('member_id', $member->member_id)->sum('amount')),
            4
        );
        $activationCredits = $this->money(ActivationWalletTransaction::query()
            ->where('member_id', $member->member_id)
            ->where('type', 'credit')
            ->sum('amount'));
        $activationDebits = $this->money(ActivationWalletTransaction::query()
            ->where('member_id', $member->member_id)
            ->where('type', 'debit')
            ->sum('amount'));

        return [
            'roi' => $this->line((string) $member->roi_wallet_amount, $roiCredits, $this->ledgerDebits($member->member_id, 'roi'), true, 'ROI Wallet is compared with legacy credits, package accruals, and stored ledger debits. No historical debit was created.'),
            'working' => $this->line((string) $member->working_wallet_amount, $workingCredits, $this->ledgerDebits($member->member_id, 'working'), true, 'Working Wallet is compared with legacy credits, package accruals, and stored ledger debits. No historical debit was created.'),
            'activation' => $this->line((string) $member->activation_wallet_amount, $activationCredits, $activationDebits, true, 'Activation Wallet is compared with its own credit and debit rows. A difference is reported and is not corrected.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function notices(): array
    {
        $notices = [
            'Wallet balances are shown as USDT. Historical package purchases and invoices keep their stored USD denomination. USD and USDT totals are not added together.',
            'No historical wallet debit was created. Stored ROI and Working debits are read from wallet_ledger_entries.',
        ];
        if ($this->hasWalletVariance()) {
            $notices[] = 'ROI Wallet and Working Wallet cannot be fully reconciled because a stored balance differs from recorded credits minus stored debits.';
        }

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

    private function ledgerDebits(string $memberId, string $wallet): string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('wallet_ledger_entries')) {
            return '0.0000';
        }

        return $this->money(WalletLedgerEntry::query()
            ->where('member_id', $memberId)
            ->where('wallet', $wallet)
            ->whereIn('direction', [WalletLedgerDirection::DEBIT, WalletLedgerDirection::REVERSAL])
            ->sum('amount'));
    }

    private function hasWalletVariance(): bool
    {
        $variance = false;
        Member::query()->orderBy('id')->chunkById(100, function ($members) use (&$variance): void {
            foreach ($members as $member) {
                $report = $this->inspect($member);
                if (bccomp($report['roi']['variance'], '0', 4) !== 0 || bccomp($report['working']['variance'], '0', 4) !== 0 || $report['roi']['negative'] || $report['working']['negative']) {
                    $variance = true;
                }
            }
        });

        return $variance;
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
