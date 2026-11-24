<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardCampaign extends Model
{
    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::DRAFT,
        self::SCHEDULED,
        self::ACTIVE,
        self::PAUSED,
        self::COMPLETED,
        self::CANCELLED,
    ];

    protected $fillable = [
        'title',
        'description',
        'gift_name',
        'gift_description',
        'target_amount',
        'denomination',
        'starts_on',
        'ends_on',
        'status',
        'promote_on_login',
        'stock_quantity',
        'terms',
        'admin_notes',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:4',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'stock_quantity' => 'integer',
        'promote_on_login' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Active campaign eligible for the member login promotional popup.
     */
    public function scopePromotableForLogin($query)
    {
        $today = now(config('deltasupport.report_timezone', 'Asia/Kolkata'))->toDateString();

        return $query->where('status', self::ACTIVE)
            ->where('promote_on_login', true)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    public function hasBanner(): bool
    {
        return filled($this->gift_image_path);
    }

    public function hasVideo(): bool
    {
        return filled($this->video_path);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(RewardQualification::class, 'campaign_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RewardAssignment::class, 'campaign_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::ACTIVE, self::COMPLETED => 'success',
            self::SCHEDULED, self::PAUSED => 'warning',
            self::CANCELLED => 'danger',
            default => 'muted',
        };
    }

    public function rulesLocked(): bool
    {
        return $this->assignments()
            ->where('status', RewardAssignment::SELECTED)
            ->whereHas('delivery', function ($query): void {
                $query->whereIn('status', RewardDelivery::COMMITTED);
            })
            ->exists();
    }
}
