<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelIncomeRuleAudit extends Model
{
    protected $fillable = [
        'level_income_rule_version_id',
        'action',
        'actor_id',
        'before_json',
        'after_json',
        'ip_address',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(LevelIncomeRuleVersion::class, 'level_income_rule_version_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
