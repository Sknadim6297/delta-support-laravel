<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssociateCommissionPackage extends Model
{
    protected $fillable = [
        'associate_commission_version_id',
        'tier_code',
        'label',
        'package_price',
        'mapped_package_code',
        'daily_total',
        'duration_total',
    ];

    protected $casts = [
        'package_price' => 'decimal:4',
        'daily_total' => 'decimal:4',
        'duration_total' => 'decimal:4',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AssociateCommissionVersion::class, 'associate_commission_version_id');
    }

    public function ranks(): HasMany
    {
        return $this->hasMany(AssociateCommissionRank::class)->orderBy('rank');
    }
}
