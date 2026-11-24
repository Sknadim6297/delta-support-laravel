<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberPaymentBlock extends Model
{
    public const TYPE_ROI = 'roi';

    public const TYPE_LEVEL = 'level';

    protected $fillable = [
        'member_id',
        'income_type',
        'start_date',
        'end_date',
        'opened_by_event_id',
        'closed_by_event_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
