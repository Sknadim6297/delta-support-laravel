<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageLevelAccrual extends Model
{
    protected $fillable = [
        'reference',
        'idempotency_key',
        'package_purchase_id',
        'member_id',
        'member_name',
        'from_member_id',
        'from_member_name',
        'level',
        'package_code',
        'package_name',
        'business_date',
        'pool_amount',
        'rate_percentage',
        'amount',
        'status',
    ];

    protected $casts = [
        'level' => 'integer',
        'business_date' => 'date',
        'pool_amount' => 'decimal:4',
        'rate_percentage' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }
}
