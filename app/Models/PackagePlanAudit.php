<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePlanAudit extends Model
{
    protected $fillable = [
        'package_id',
        'package_plan_version_id',
        'user_id',
        'action',
        'before_json',
        'after_json',
        'ip_address',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PackagePlanVersion::class, 'package_plan_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
