<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class QueryDate
{
    public static function day(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) !== 1) {
            return null;
        }

        if (! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value)->startOfDay();
    }

    public static function key(mixed $value): ?int
    {
        $day = self::day($value);

        return $day ? (int) $day->format('Ymd') : null;
    }
}
