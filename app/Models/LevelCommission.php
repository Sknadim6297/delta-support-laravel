<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Retained historical 32-level rate table. It is not read by the Delta Support 20-level plan.
 */
class LevelCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'percentage' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
