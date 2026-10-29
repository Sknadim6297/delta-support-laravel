<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberSecurityPinReset extends Model
{
    protected $hidden = [
        'otp_hash',
    ];

    protected $fillable = [
        'member_id',
        'otp_hash',
        'expires_at',
        'verified_at',
        'consumed_at',
        'invalidated_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
