<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageIncomeActivation extends Model
{
    protected $fillable = [
        'package_code',
        'activation_business_date',
        'activated_at',
        'activated_by',
        'confirm_token',
        'terms_snapshot',
    ];

    protected $casts = [
        'activation_business_date' => 'date',
        'activated_at' => 'datetime',
        'terms_snapshot' => 'array',
    ];
}
