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

    /**
     * Compact header-style balance with 2 decimals: $134.37
     * Ledger/ROI storage stays 4dp; this is display-only.
     */
    public static function dollar(mixed $amount): string
    {
        $normalized = self::amount($amount, 4);

        return '$'.number_format((float) $normalized, 2, '.', '');
    }

    public static function amount(mixed $amount, int $decimals = 4): string
    {
        $decimals = max(0, min(8, $decimals));

        if ($amount === null || $amount === '') {
            return bcadd('0', '0', $decimals);
        }

        if (is_int($amount) || is_float($amount)) {
            return number_format($amount, $decimals, '.', '');
        }

        return bcadd((string) $amount, '0', $decimals);
    }
}
