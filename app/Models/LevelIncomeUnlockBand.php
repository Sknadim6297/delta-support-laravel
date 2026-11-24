<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelIncomeUnlockBand extends Model
{
    protected $fillable = [
        'level_income_rule_version_id',
        'sort_order',
        'start_level',
        'end_level',
        'min_qualifying_directs',
        'requires_personal_activation',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'start_level' => 'integer',
        'end_level' => 'integer',
        'min_qualifying_directs' => 'integer',
        'requires_personal_activation' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(LevelIncomeRuleVersion::class, 'level_income_rule_version_id');
    }
}
