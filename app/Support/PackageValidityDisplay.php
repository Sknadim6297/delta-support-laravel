<?php

namespace App\Support;

/**
 * Display helpers for Package Validity Duration.
 * Separate from ROI days and tour nights/days. Does not enforce expiry.
 */
final class PackageValidityDisplay
{
    public const UNIT_DAYS = 'days';

    public const UNIT_MONTHS = 'months';

    public const UNIT_YEARS = 'years';

    public const UNITS = [
        self::UNIT_DAYS => 'Days',
        self::UNIT_MONTHS => 'Months',
        self::UNIT_YEARS => 'Years',
    ];

    /**
     * @param  array{validity_unlimited?: bool|null, validity_duration?: int|null, validity_unit?: string|null}|object|null  $source
     */
    public static function label(null|array|object $source): string
    {
        if ($source === null) {
            return 'Not set';
        }

        $unlimited = (bool) (is_array($source)
            ? ($source['validity_unlimited'] ?? false)
            : ($source->validity_unlimited ?? false));
        if ($unlimited) {
            return 'Unlimited';
        }

        $duration = is_array($source)
            ? ($source['validity_duration'] ?? null)
            : ($source->validity_duration ?? null);
        $unit = is_array($source)
            ? ($source['validity_unit'] ?? null)
            : ($source->validity_unit ?? null);

        if ($duration === null || $duration === '' || (int) $duration < 1 || ! is_string($unit) || ! isset(self::UNITS[$unit])) {
            return 'Not set';
        }

        $n = (int) $duration;
        $unitLabel = self::UNITS[$unit];
        if ($n === 1) {
            $unitLabel = match ($unit) {
                self::UNIT_DAYS => 'Day',
                self::UNIT_MONTHS => 'Month',
                self::UNIT_YEARS => 'Year',
                default => $unitLabel,
            };
        }

        return $n.' '.$unitLabel;
    }
}
