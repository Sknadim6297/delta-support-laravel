<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardEvaluationRun extends Model
{
    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    protected $fillable = [
        'business_date',
        'status',
        'members_reviewed',
        'selected_count',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'members_reviewed' => 'integer',
        'selected_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
