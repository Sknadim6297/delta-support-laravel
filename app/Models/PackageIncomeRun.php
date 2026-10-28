<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageIncomeRun extends Model
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const BLOCKED = 'blocked';

    public const REFUSED = 'refused';

    protected $fillable = [
        'business_date',
        'status',
        'credited_roi',
        'credited_commissions',
        'message',
    ];

    protected $casts = [
        'business_date' => 'date',
        'credited_roi' => 'integer',
        'credited_commissions' => 'integer',
    ];
}
