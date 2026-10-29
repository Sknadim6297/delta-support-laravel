<?php

namespace App\Models;

use App\Support\MoneyDisplay;
use Illuminate\Database\Eloquent\Model;

class MemberTransferSetting extends Model
{
    protected $fillable = [
        'member_transfers_enabled',
        'minimum_amount',
        'maximum_amount',
        'daily_limit',
        'fee_amount',
        'denomination',
    ];

    protected $casts = [
        'member_transfers_enabled' => 'boolean',
        'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4',
        'daily_limit' => 'decimal:4',
        'fee_amount' => 'decimal:4',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'member_transfers_enabled' => false,
            'minimum_amount' => '1.0000',
            'maximum_amount' => '1000.0000',
            'daily_limit' => '5000.0000',
            'fee_amount' => '0.0000',
            'denomination' => MoneyDisplay::USDT,
        ]);
    }

    public function transfersEnabled(): bool
    {
        if (config('deltasupport.controls.member_transfers_enabled') === true) {
            return true;
        }

        return (bool) $this->member_transfers_enabled;
    }
}
