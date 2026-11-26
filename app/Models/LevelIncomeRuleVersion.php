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

    public const QUAL_REGISTERED_DIRECTS = 'registered_directs';

    public const QUAL_APPROVED_PACKAGE_DIRECTS = 'approved_package_directs';

    public const QUAL_INCOME_ELIGIBLE_DIRECTS = 'income_eligible_directs';

    public const QUAL_APPROVED_PACKAGE_PURCHASE_COUNT = 'approved_package_purchase_count';

    public const QUAL_COMBINED_JOINING_AND_PURCHASE = 'combined_joining_and_purchase';

    /**
     * @deprecated Use QUAL_COMBINED_JOINING_AND_PURCHASE (singular). Same stored value.
     */
    public const QUAL_COMBINED_JOINING_AND_PURCHASES = self::QUAL_COMBINED_JOINING_AND_PURCHASE;

    public const COMBINED_SCOPE_APPROVED_PACKAGE_DIRECTS = 'approved_package_directs';

    public const COMBINED_SCOPE_INCOME_ELIGIBLE_DIRECTS = 'income_eligible_directs';

    public const COMBINED_SCOPE_APPROVED_PACKAGE_PURCHASE_COUNT = 'approved_package_purchase_count';

    public const COMBINED_REPEAT_UNIQUE_DIRECTS = 'unique_directs';

    public const COMBINED_REPEAT_EACH_PURCHASE = 'each_approved_purchase';

    public const COMBINED_DOUBLE_ALLOW_SUM = 'allow_sum';

    public const COMBINED_DOUBLE_PREVENT_OVERLAP = 'prevent_overlap';

    public const COMBINED_DOUBLE_MAX_ONLY = 'max_only';

    protected $fillable = [
        'version_code',
        'label',
        'status',
        'posting_authorized',
        'applicability',
        'direct_qualification_basis',
        'tour_only_qualifies_for_unlock',
        'combined_joining_enabled',
        'combined_purchase_enabled',
        'combined_purchase_scope',
        'combined_repeat_purchase_mode',
        'combined_double_credit_policy',
        'combined_joining_unit_value',
        'combined_purchase_unit_value',
        'grandfather_existing_unlock',
        'grandfather_transition_date',
        'grandfather_captured_at',
        'grandfather_captured_by',
        'effective_from',
        'notes',
        'change_note',
        'created_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'posting_authorized' => 'boolean',
        'tour_only_qualifies_for_unlock' => 'boolean',
        'combined_joining_enabled' => 'boolean',
        'combined_purchase_enabled' => 'boolean',
        'combined_joining_unit_value' => 'integer',
        'combined_purchase_unit_value' => 'integer',
        'grandfather_existing_unlock' => 'boolean',
        'grandfather_transition_date' => 'date',
        'grandfather_captured_at' => 'datetime',
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

    public function grandfatherSnapshots(): HasMany
    {
        return $this->hasMany(LevelIncomeUnlockGrandfatherSnapshot::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LevelIncomeRuleAudit::class);
    }

    public function grandfatherCapturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grandfather_captured_by');
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
