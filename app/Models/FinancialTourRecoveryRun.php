<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialTourRecoveryRun extends Model
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
        'business_date',
        'requested_dates',
        'package_code',
        'mode',
        'status',
        'dates_processed',
        'roi_records',
        'commission_records',
        'roi_amount',
        'commission_amount',
        'commission_pool_amount',
        'day_results',
        'message',
        'actor',
    ];

    protected $casts = [
        'business_date' => 'date',
        'requested_dates' => 'array',
        'day_results' => 'array',
        'roi_amount' => 'decimal:4',
        'commission_amount' => 'decimal:4',
        'commission_pool_amount' => 'decimal:4',
    ];
}
