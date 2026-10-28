<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardDelivery extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const PROCESSING = 'processing';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const COMMITTED = [
        self::PROCESSING,
        self::SHIPPED,
        self::DELIVERED,
        self::FAILED,
    ];

    public const STATUSES = [
        self::PENDING,
        self::APPROVED,
        self::PROCESSING,
        self::SHIPPED,
        self::DELIVERED,
        self::FAILED,
        self::CANCELLED,
    ];

    protected $fillable = [
        'assignment_id',
        'status',
        'method',
        'tracking_reference',
        'dispatched_at',
        'delivered_at',
        'recipient_confirmation',
        'internal_notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RewardAssignment::class, 'assignment_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RewardDeliveryEvent::class, 'delivery_id');
    }

    public function statusLabel(): string
    {
        return self::labelFor((string) $this->status);
    }

    public static function labelFor(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved for fulfillment',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped / Dispatched',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed delivery',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::DELIVERED => 'success',
            self::APPROVED, self::PROCESSING, self::SHIPPED => 'warning',
            self::FAILED, self::CANCELLED => 'danger',
            default => 'muted',
        };
    }
}
