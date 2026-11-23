<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagePlanVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'package_id',
        'version_number',
        'status',
        'has_daily_roi',
        'roi_daily',
        'roi_days',
        'wait_days',
        'roi_start_rule',
        'roi_end_rule',
        'commission_pool',
        'commission_days',
        'level_commission_eligible',
        'tour_eligible_after_days',
        'tour_nights',
        'tour_days',
        'tour_adults',
        'tour_children',
        'food_coupon_amount',
        'food_coupon_currency',
        'tour_description',
        'label',
        'change_note',
        'accrual_authority',
        'published_at',
        'created_by',
        'published_by',
    ];

    protected $casts = [
        'has_daily_roi' => 'boolean',
        'roi_daily' => 'decimal:4',
        'roi_days' => 'integer',
        'wait_days' => 'integer',
        'commission_pool' => 'decimal:4',
        'commission_days' => 'integer',
        'level_commission_eligible' => 'boolean',
        'tour_eligible_after_days' => 'integer',
        'tour_nights' => 'integer',
        'tour_days' => 'integer',
        'tour_adults' => 'integer',
        'tour_children' => 'integer',
        'food_coupon_amount' => 'decimal:4',
        'accrual_authority' => 'boolean',
        'published_at' => 'datetime',
        'version_number' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PackagePlanAudit::class);
    }

    public function totalRoi(): ?string
    {
        if (! $this->has_daily_roi || $this->roi_daily === null || $this->roi_days === null || $this->roi_days < 1) {
            return null;
        }

        return bcmul(bcadd((string) $this->roi_daily, '0', 4), (string) $this->roi_days, 4);
    }

    /**
     * @return array{roi_daily: string, roi_days: int, commission_pool: ?string, commission_days: ?int}|null
     */
    public function asIncomeDefinition(): ?array
    {
        if (! $this->has_daily_roi || $this->roi_daily === null || $this->roi_days === null || $this->roi_days < 1) {
            return null;
        }

        return [
            'roi_daily' => bcadd((string) $this->roi_daily, '0', 4),
            'roi_days' => (int) $this->roi_days,
            'commission_pool' => $this->commission_pool === null ? null : bcadd((string) $this->commission_pool, '0', 4),
            'commission_days' => $this->commission_days === null ? null : (int) $this->commission_days,
        ];
    }
}
