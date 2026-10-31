<?php

namespace App\Models;

use App\Support\MoneyDisplay;
use App\Support\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'reference',
        'idempotency_key',
        'member_id',
        'source_wallet',
        'amount',
        'charge_amount',
        'net_amount',
        'denomination',
        'network',
        'receiving_address',
        'status',
        'payout_network',
        'payout_txid',
        'paid_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'charge_amount' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if (strcasecmp((string) $request->denomination, MoneyDisplay::USDT) !== 0) {
                throw new RuntimeException('Withdrawal requests must be denominated in USDT.');
            }
            if (! in_array((string) $request->status, WithdrawalStatus::VALUES, true)) {
                throw new RuntimeException('Withdrawal status is not recognized.');
            }
            $txid = trim((string) ($request->payout_txid ?? ''));
            if ($txid !== '' && trim((string) ($request->payout_network ?? '')) === '') {
                throw new RuntimeException('A payout transaction ID requires a payout network.');
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(WithdrawalStatusEvent::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return WithdrawalStatus::canTransition((string) $this->status, $status);
    }
}
