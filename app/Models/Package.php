<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    public const TYPE_TOUR = 'tour';

    public const TYPE_TOUR_ROI = 'tour_roi';

    public const TYPE_PREMIUM = 'premium_tour_roi';

    public const TYPE_ROI = 'roi';

    public const TYPES = [
        self::TYPE_TOUR => 'Tour Only',
        self::TYPE_TOUR_ROI => 'ROI + Tour',
        self::TYPE_PREMIUM => 'Premium Tour + ROI',
        self::TYPE_ROI => 'ROI Only',
    ];

    public const INCOME_SOURCE_LEGACY = 'legacy_config';

    public const INCOME_SOURCE_DATABASE = 'database';

    protected $fillable = [
        'code',
        'package_type',
        'name',
        'description',
        'full_description',
        'price_usd',
        'denomination',
        'tour_title',
        'tour_details',
        'destinations',
        'duration',
        'benefits',
        'inclusions',
        'exclusions',
        'image_path',
        'roi_eligible',
        'auto_roi_enabled',
        'level_commission_eligible',
        'is_active',
        'purchase_available',
        'income_source',
        'active_plan_version_id',
        'sort_order',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'roi_eligible' => 'boolean',
        'auto_roi_enabled' => 'boolean',
        'level_commission_eligible' => 'boolean',
        'is_active' => 'boolean',
        'purchase_available' => 'boolean',
        'sort_order' => 'integer',
        'active_plan_version_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Package $package): void {
            // Admin createPackage always sets auto_roi_enabled explicitly (OFF).
            // Legacy/test callers that only set roi_eligible keep prior accrual intent.
            // Production SILVER/VIP/DIAMOND/SHARE are forced OFF by applySafeProductionDefaults().
            if (! array_key_exists('auto_roi_enabled', $package->getAttributes())) {
                $package->auto_roi_enabled = (bool) $package->roi_eligible;
            }
            if ($package->package_type === self::TYPE_TOUR) {
                $package->auto_roi_enabled = false;
            }
        });
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->package_type] ?? self::TYPES[self::TYPE_TOUR];
    }

    public function reservesFutureIncome(): bool
    {
        return in_array($this->package_type, [self::TYPE_TOUR_ROI, self::TYPE_PREMIUM, self::TYPE_ROI], true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    public function planVersions(): HasMany
    {
        return $this->hasMany(PackagePlanVersion::class);
    }

    public function planAudits(): HasMany
    {
        return $this->hasMany(PackagePlanAudit::class);
    }

    public function activePlanVersion(): BelongsTo
    {
        return $this->belongsTo(PackagePlanVersion::class, 'active_plan_version_id');
    }
}
