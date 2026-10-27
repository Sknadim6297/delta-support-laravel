<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'price_usd',
        'tour_title',
        'tour_details',
        'duration',
        'benefits',
        'image_path',
        'roi_eligible',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'roi_eligible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }
}
