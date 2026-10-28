<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RewardAssignment extends Model
{
    public const SELECTED = 'selected';

    public const SUPERSEDED = 'superseded';

    public const CONFLICT = 'conflict';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'qualification_id',
        'campaign_id',
        'member_id',
        'gift_name',
        'status',
        'selected_at',
        'superseded_at',
        'superseded_by_campaign_id',
        'conflict_campaign_id',
        'conflict_note',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(RewardQualification::class, 'qualification_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RewardCampaign::class, 'campaign_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(RewardDelivery::class, 'assignment_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::SELECTED => 'Selected',
            self::SUPERSEDED => 'Superseded',
            self::CONFLICT => 'Needs review',
            self::CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::SELECTED => 'success',
            self::CONFLICT => 'warning',
            self::CANCELLED => 'danger',
            default => 'muted',
        };
    }
}
