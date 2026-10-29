<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialControl extends Model
{
    protected $fillable = [
        'roi_calculation_enabled',
        'roi_wallet_crediting_enabled',
        'level_calculation_enabled',
        'level_wallet_crediting_enabled',
        'rewards_evaluation_enabled',
        'withdrawal_requests_enabled',
        'withdrawal_approval_enabled',
        'external_payouts_enabled',
        'daily_financial_cycle_enabled',
    ];

    protected $casts = [
        'roi_calculation_enabled' => 'boolean',
        'roi_wallet_crediting_enabled' => 'boolean',
        'level_calculation_enabled' => 'boolean',
        'level_wallet_crediting_enabled' => 'boolean',
        'rewards_evaluation_enabled' => 'boolean',
        'withdrawal_requests_enabled' => 'boolean',
        'withdrawal_approval_enabled' => 'boolean',
        'external_payouts_enabled' => 'boolean',
        'daily_financial_cycle_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'roi_calculation_enabled' => false,
            'roi_wallet_crediting_enabled' => false,
            'level_calculation_enabled' => false,
            'level_wallet_crediting_enabled' => false,
            'rewards_evaluation_enabled' => false,
            'withdrawal_requests_enabled' => false,
            'withdrawal_approval_enabled' => false,
            'external_payouts_enabled' => false,
            'daily_financial_cycle_enabled' => false,
        ]);
    }
};
