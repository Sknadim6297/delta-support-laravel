<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LevelIncomeRuleVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    public const APPLICABILITY_UNSPECIFIED = 'unspecified';

    public const APPLICABILITY_FUTURE_PURCHASES_ONLY = 'future_purchases_only';

    public const APPLICABILITY_EXISTING_PROSPECTIVE = 'existing_prospective';

    protected $fillable = [
        'version_code',
        'label',
        'status',
        'posting_authorized',
        'applicability',
        'effective_from',
        'notes',
        'change_note',
        'created_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'posting_authorized' => 'boolean',
        'effective_from' => 'date',
        'published_at' => 'datetime',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(LevelIncomeUnlockBand::class)->orderBy('sort_order')->orderBy('start_level');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(LevelIncomeCommissionRate::class)->orderBy('level');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LevelIncomeRuleAudit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
