<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RewardQualification extends Model
{
    public const NOT_ELIGIBLE = 'not_eligible';

    public const IN_PROGRESS = 'in_progress';

    public const QUALIFIED = 'qualified';

    public const SELECTED = 'selected';

    public const SUPERSEDED = 'superseded';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::NOT_ELIGIBLE,
        self::IN_PROGRESS,
        self::QUALIFIED,
        self::SELECTED,
        self::SUPERSEDED,
        self::CANCELLED,
    ];

    protected $fillable = [
        'campaign_id',
        'member_id',
        'member_name',
        'sponsor_id',
        'achieved_amount',
        'target_amount',
        'status',
        'qualified_at',
    ];

    protected $casts = [
        'achieved_amount' => 'decimal:4',
        'target_amount' => 'decimal:4',
        'qualified_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RewardCampaign::class, 'campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(RewardAssignment::class, 'qualification_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::NOT_ELIGIBLE => 'Not eligible',
            self::IN_PROGRESS => 'In progress',
            self::QUALIFIED => 'Qualified',
            self::SELECTED => 'Selected',
            self::SUPERSEDED => 'Superseded',
            self::CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::QUALIFIED, self::SELECTED => 'success',
            self::IN_PROGRESS => 'warning',
            self::CANCELLED => 'danger',
            default => 'muted',
        };
    }
}
