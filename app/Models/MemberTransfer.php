<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class MemberTransfer extends Model
{
    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    public const WALLET = 'working';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::COMPLETED,
        self::REVERSED,
    ];

    /**
     * @var list<string>
     */
    private const IMMUTABLE = [
        'transfer_reference',
        'sender_member_id',
        'receiver_member_id',
        'source_wallet',
        'destination_wallet',
        'amount',
        'fee_amount',
        'currency',
        'idempotency_key',
        'business_date',
        'completed_at',
    ];

    protected $fillable = [
        'transfer_reference',
        'sender_member_id',
        'receiver_member_id',
        'source_wallet',
        'destination_wallet',
        'amount',
        'fee_amount',
        'currency',
        'status',
        'idempotency_key',
        'business_date',
        'completed_at',
        'reversed_at',
        'reversal_actor',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fee_amount' => 'decimal:4',
        'business_date' => 'date',
        'completed_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $transfer): void {
            $locked = array_intersect(array_keys($transfer->getDirty()), self::IMMUTABLE);
            if ($locked !== []) {
                throw new RuntimeException('Completed transfer amounts cannot be edited.');
            }
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sender_member_id', 'member_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'receiver_member_id', 'member_id');
    }

    public function involves(string $memberId): bool
    {
        return $this->sender_member_id === $memberId || $this->receiver_member_id === $memberId;
    }
}
