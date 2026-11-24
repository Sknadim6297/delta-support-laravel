<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourInterestRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONTACTED,
        self::STATUS_CONFIRMED,
    ];

    public const CLOSED_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'member_id',
        'status',
        'active_marker',
        'submitted_at',
        'contacted_at',
        'withdrawn_at',
        'closed_at',
        'assigned_admin_id',
        'admin_notes',
        'source',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'contacted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TourInterestRequestEvent::class)->orderByDesc('id');
    }

    public function isActive(): bool
    {
        return $this->active_marker === '1' && in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONTACTED => 'Contacted',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Pending',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_CONTACTED => 'warning',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'muted',
            default => 'brand',
        };
    }
}
