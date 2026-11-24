<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelIncomeUnlockGrandfatherSnapshot extends Model
{
    protected $fillable = [
        'level_income_rule_version_id',
        'member_id',
        'legacy_unlocked_levels',
        'income_eligible_direct_count',
        'registered_direct_count',
        'personally_activated_legacy',
        'transition_date',
        'captured_at',
        'captured_by',
    ];

    protected $casts = [
        'legacy_unlocked_levels' => 'integer',
        'income_eligible_direct_count' => 'integer',
        'registered_direct_count' => 'integer',
        'personally_activated_legacy' => 'boolean',
        'transition_date' => 'date',
        'captured_at' => 'datetime',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(LevelIncomeRuleVersion::class, 'level_income_rule_version_id');
    }

    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
