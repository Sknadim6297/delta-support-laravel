<?php

namespace App\Models;

use App\Support\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class WithdrawalStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'withdrawal_request_id',
        'from_status',
        'to_status',
        'actor',
        'reason',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            if (! in_array((string) $event->to_status, WithdrawalStatus::VALUES, true)) {
                throw new RuntimeException('Withdrawal status is not recognized.');
            }
            if ($event->from_status !== null && ! in_array((string) $event->from_status, WithdrawalStatus::VALUES, true)) {
                throw new RuntimeException('Withdrawal status is not recognized.');
            }
        });
        static::updating(function (): void {
            throw new RuntimeException('Withdrawal status events are append-only.');
        });
        static::deleting(function (): void {
            throw new RuntimeException('Withdrawal status events are append-only.');
        });
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }
}
