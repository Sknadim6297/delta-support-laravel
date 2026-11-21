<?php

namespace App\Models;

use App\Support\MoneyDisplay;
use Illuminate\Database\Eloquent\Model;

class MemberTransferSetting extends Model
{
    public const CHARGE_TYPES = ['flat', 'percent'];

    protected $fillable = [
        'member_transfers_enabled',
        'minimum_amount',
        'maximum_amount',
        'daily_limit',
        'fee_amount',
        'fee_type',
        'fee_value',
        'denomination',
    ];

    protected $casts = [
        'member_transfers_enabled' => 'boolean',
        'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4',
        'daily_limit' => 'decimal:4',
        'fee_amount' => 'decimal:4',
        'fee_value' => 'decimal:4',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'member_transfers_enabled' => false,
            'minimum_amount' => '1.0000',
            'maximum_amount' => '1000.0000',
            'daily_limit' => '5000.0000',
            'fee_amount' => '0.0000',
            'fee_type' => 'flat',
            'fee_value' => '0.0000',
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

    public function calculateFee(string $amount): string
    {
        $amount = bcadd(trim($amount), '0', 4);
        if ($this->fee_type === 'percent') {
            $value = bcadd((string) ($this->fee_value ?? '0'), '0', 4);

            return bcdiv(bcmul($amount, $value, 4), '100', 4);
        }

        return bcadd((string) ($this->fee_amount ?? '0'), '0', 4);
    }

    public function effectiveFeeLabel(): string
    {
        if ($this->fee_type === 'percent') {
            return bcadd((string) ($this->fee_value ?? '0'), '0', 4).'%';
        }

        return bcadd((string) ($this->fee_amount ?? '0'), '0', 4).' USDT';
    }
}
