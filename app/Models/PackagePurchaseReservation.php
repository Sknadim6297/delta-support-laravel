<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PackagePurchaseReservation extends Model
{
    public const ACTIVE = 'active';
    public const SETTLED = 'settled';
    public const RELEASED = 'released';

    protected $fillable = [
        'package_purchase_id',
        'member_id',
        'wallet',
        'reserved_amount',
        'status',
        'reservation_reference',
        'idempotency_key',
        'reserved_at',
        'settled_at',
        'released_at',
    ];

    protected $casts = [
        'reserved_amount' => 'decimal:4',
        'reserved_at' => 'datetime',
        'settled_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Package purchase reservations are append-only and cannot be deleted.');
        });
        static::updating(function (self $reservation): void {
            $dirty = $reservation->getDirty();
            $immutable = array_intersect(array_keys($dirty), [
                'package_purchase_id',
                'member_id',
                'wallet',
                'reserved_amount',
                'reservation_reference',
                'idempotency_key',
                'reserved_at',
            ]);
            if ($immutable !== []) {
                throw new RuntimeException('Package purchase reservation identity fields are immutable.');
            }

            $original = (string) $reservation->getOriginal('status');
            $status = (string) $reservation->status;
            $statusChanged = array_key_exists('status', $dirty);
            if ($statusChanged && in_array($original, [self::SETTLED, self::RELEASED], true)) {
                throw new RuntimeException('A settled or released package purchase reservation cannot be modified.');
            }
            if ($statusChanged && $original === self::ACTIVE
                && ! in_array($status, [self::SETTLED, self::RELEASED], true)) {
                throw new RuntimeException('An active package purchase reservation can only settle or release.');
            }
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
