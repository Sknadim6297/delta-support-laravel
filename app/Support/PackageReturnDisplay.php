<?php

namespace App\Support;

/**
 * Display helpers for Admin package ROI fields.
 * Uses BCMath. Does not invent rates — callers pass validated decimals.
 */
final class PackageReturnDisplay
{
    public const DAYS_PER_MONTH_DISPLAY = 30;

    public static function monthlyReturn(?string $dailyRoi): ?string
    {
        if ($dailyRoi === null || $dailyRoi === '') {
            return null;
        }

        return bcmul(bcadd($dailyRoi, '0', 4), (string) self::DAYS_PER_MONTH_DISPLAY, 4);
    }

    public static function totalReturn(?string $dailyRoi, ?int $roiDays): ?string
    {
        if ($dailyRoi === null || $dailyRoi === '' || $roiDays === null || $roiDays < 1) {
            return null;
        }

        return bcmul(bcadd($dailyRoi, '0', 4), (string) $roiDays, 4);
    }

    public static function totalMonthsDisplay(?int $roiDays): ?string
    {
        if ($roiDays === null || $roiDays < 1) {
            return null;
        }

        // 30-day display convention (not calendar months).
        $whole = intdiv($roiDays, self::DAYS_PER_MONTH_DISPLAY);
        $rem = $roiDays % self::DAYS_PER_MONTH_DISPLAY;
        if ($rem === 0) {
            return (string) $whole;
        }

        return rtrim(rtrim(bcdiv((string) $roiDays, (string) self::DAYS_PER_MONTH_DISPLAY, 2), '0'), '.');
    }
}
