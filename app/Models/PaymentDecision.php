<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDecision extends Model
{
    protected $fillable = [
        'package_purchase_id',
        'user_id',
        'action',
        'reason',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }
}
