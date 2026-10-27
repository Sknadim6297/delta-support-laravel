<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'package_purchase_id',
        'purchase_reference',
        'member_id',
        'member_name',
        'package_name',
        'package_code',
        'package_details',
        'price_usd',
        'denomination',
        'currency_label',
        'network',
        'payment_reference',
        'company_name',
        'company_address',
        'company_email',
        'company_phone',
        'purchased_at',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'purchased_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class, 'package_purchase_id');
    }
}
