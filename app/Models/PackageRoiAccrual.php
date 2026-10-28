<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageRoiAccrual extends Model
{
    protected $fillable = [
        'reference',
        'idempotency_key',
        'package_purchase_id',
        'member_id',
        'member_name',
        'package_code',
        'package_name',
        'business_date',
        'amount',
        'status',
    ];

    protected $casts = [
        'business_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }
}
