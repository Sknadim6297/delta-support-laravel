<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelIncomeCommissionRate extends Model
{
    protected $fillable = [
        'level_income_rule_version_id',
        'level',
        'percentage',
    ];

    protected $casts = [
        'level' => 'integer',
        'percentage' => 'decimal:4',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(LevelIncomeRuleVersion::class, 'level_income_rule_version_id');
    }
}
