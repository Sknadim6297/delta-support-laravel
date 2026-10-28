<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardDeliveryEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(RewardDelivery::class, 'delivery_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
