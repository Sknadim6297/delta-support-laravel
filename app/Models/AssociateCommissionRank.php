<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociateCommissionRank extends Model
{
    protected $fillable = [
        'associate_commission_package_id',
        'rank',
        'daily_amount',
    ];

    protected $casts = [
        'rank' => 'integer',
        'daily_amount' => 'decimal:4',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(AssociateCommissionPackage::class, 'associate_commission_package_id');
    }
}
