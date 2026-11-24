<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberPaymentControl extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'member_id',
        'master_enabled',
        'roi_preference',
        'level_preference',
        'status',
        'last_reason',
        'last_effective_from',
        'updated_by',
    ];

    protected $casts = [
        'master_enabled' => 'boolean',
        'roi_preference' => 'boolean',
        'level_preference' => 'boolean',
        'last_effective_from' => 'date',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MemberPaymentControlEvent::class, 'member_id', 'member_id');
    }

    public function effectiveRoiEnabled(): bool
    {
        return $this->master_enabled && $this->roi_preference;
    }

    public function effectiveLevelEnabled(): bool
    {
        return $this->master_enabled && $this->level_preference;
    }

    public function computeStatus(): string
    {
        $roi = $this->effectiveRoiEnabled();
        $level = $this->effectiveLevelEnabled();
        if ($roi && $level) {
            return self::STATUS_ACTIVE;
        }
        if (! $roi && ! $level) {
            return self::STATUS_STOPPED;
        }

        return self::STATUS_PARTIAL;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_STOPPED => 'Stopped',
            self::STATUS_PARTIAL => 'Partially Stopped',
            default => 'Active',
        };
    }
}
