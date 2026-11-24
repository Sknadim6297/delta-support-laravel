<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPaymentControlEvent extends Model
{
    protected $fillable = [
        'member_id',
        'action',
        'reason',
        'effective_from',
        'before_json',
        'after_json',
        'actor_id',
        'ip_address',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
