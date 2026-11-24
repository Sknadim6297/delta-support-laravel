<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourInterestRequestEvent extends Model
{
    protected $fillable = [
        'tour_interest_request_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'actor_type',
        'actor_member_id',
        'actor_admin_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TourInterestRequest::class, 'tour_interest_request_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_admin_id');
    }
}
