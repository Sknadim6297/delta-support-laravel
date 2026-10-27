<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PackageIncomeTerm extends Model
{
    protected $fillable = [
        'package_purchase_id',
        'reference',
        'income_eligible',
        'monthly_rate_percentage',
        'monthly_amount',
        'duration_months',
        'commission_base',
        'first_accrual_rule',
        'business_timezone',
        'snapshotted_at',
    ];

    protected $casts = [
        'monthly_rate_percentage' => 'decimal:4',
        'monthly_amount' => 'decimal:4',
        'duration_months' => 'integer',
        'snapshotted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Package income terms are immutable.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Package income terms are immutable.');
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }
}
