<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssociateCommissionVersion extends Model
{
    public const VERSION_V1 = 'associate_v1';

    protected $fillable = [
        'version_code',
        'label',
        'commission_days',
        'is_active',
        'snapshot_on_approval',
        'effective_from',
        'notes',
    ];

    protected $casts = [
        'commission_days' => 'integer',
        'is_active' => 'boolean',
        'snapshot_on_approval' => 'boolean',
        'effective_from' => 'date',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(AssociateCommissionPackage::class);
    }
}
