<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPlanLevel extends Model
{
    public const PLAN_CODE = 'delta_support_v1';

    protected $fillable = [
        'plan_code',
        'level',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'percentage' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
