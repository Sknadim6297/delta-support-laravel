<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    public const TYPE_TOUR = 'tour';

    public const TYPE_TOUR_ROI = 'tour_roi';

    public const TYPE_PREMIUM = 'premium_tour_roi';

    public const TYPES = [
        self::TYPE_TOUR => 'Tour Package',
        self::TYPE_TOUR_ROI => 'Tour + ROI Package',
        self::TYPE_PREMIUM => 'Premium Tour + ROI Package',
    ];

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
        'level_commission_eligible',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'roi_eligible' => 'boolean',
        'level_commission_eligible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->package_type] ?? self::TYPES[self::TYPE_TOUR];
    }

    public function reservesFutureIncome(): bool
    {
        return in_array($this->package_type, [self::TYPE_TOUR_ROI, self::TYPE_PREMIUM], true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }
}
