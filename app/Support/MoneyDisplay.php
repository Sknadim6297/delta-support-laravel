<?php

namespace App\Support;

class MoneyDisplay
{
    public const USDT = 'USDT';

    public const USD = 'USD';

    public static function format(mixed $amount, ?string $denomination): string
    {
        $code = strtoupper(trim((string) $denomination));
        if (! in_array($code, [self::USDT, self::USD], true)) {
            $code = self::USD;
        }

        return $code.' '.self::amount($amount);
    }

    public static function usdt(mixed $amount): string
    {
        return self::USDT.' '.self::amount($amount);
    }

    public static function amount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.0000';
        }

        if (is_int($amount) || is_float($amount)) {
            return number_format($amount, 4, '.', '');
        }

        return bcadd((string) $amount, '0', 4);
    }
}
