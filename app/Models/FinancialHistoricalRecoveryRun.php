<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialHistoricalRecoveryRun extends Model
{
    public const MODE_PREVIEW = 'preview';

    public const MODE_APPLY = 'apply';

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'database_name',
        'from_date',
        'to_date',
        'package_codes',
        'mode',
        'status',
        'days_processed',
        'roi_records',
        'commission_records',
        'roi_amount',
        'commission_amount',
        'day_results',
        'message',
        'actor',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'package_codes' => 'array',
        'day_results' => 'array',
        'roi_amount' => 'decimal:4',
        'commission_amount' => 'decimal:4',
    ];
}
