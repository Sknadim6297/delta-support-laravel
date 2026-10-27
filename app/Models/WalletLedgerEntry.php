<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Eloquent blocks updates and deletes. Query Builder, DB::table, and direct SQL do not
 * fire these events, and the table has no database trigger. A hold row is not a wallet debit.
 */
class WalletLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
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
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Wallet ledger entries are append-only.');
        });
        static::deleting(function (): void {
            throw new RuntimeException('Wallet ledger entries are append-only.');
        });
    }
}
