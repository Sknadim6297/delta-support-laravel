<?php

namespace App\Support;

/**
 * Column blueprint for wallet_ledger_entries. This class does not insert a row or move a balance.
 *
 * The append-only ledger stores one immutable row per movement: wallet (roi, working, activation),
 * direction (credit, debit, transfer, hold, release, reversal, settlement, adjustment),
 * amount decimal(20,4), denomination (USDT for new rows), member_id, reference, idempotency_key,
 * package_purchase_id nullable, withdrawal_request_id nullable, counter_entry_id nullable,
 * actor, reason, and created_at. A hold does not decrease the gross wallet column.
 * Updates and deletes stay forbidden. No opening balance is written by this class.
 * A credit and its balance change must commit together after lockForUpdate, and a debit
 * must refuse a result below 0.0000. ROI and Working Wallet debits are not stored yet,
 * so those wallets stay unreconciled until that ledger exists.
 */
class FutureWalletLedger
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            'wallet',
            'direction',
            'amount',
            'denomination',
            'member_id',
            'reference',
            'idempotency_key',
            'package_purchase_id',
            'withdrawal_request_id',
            'counter_entry_id',
            'actor',
            'reason',
            'created_at',
        ];
    }
}
