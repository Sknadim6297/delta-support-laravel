<?php

namespace App\Support;

/**
 * Server-controlled Admin/Member panel shell theme.
 * Values: lux (legacy rollback) | v2 (new panel design system).
 * Not user-toggleable; only env/config.
 */
final class PanelTheme
{
    public const LUX = 'lux';

    public const V2 = 'v2';

    public static function current(): string
    {
        $value = strtolower((string) config('deltasupport.panel_theme', self::LUX));

        return $value === self::V2 ? self::V2 : self::LUX;
    }

    public static function isV2(): bool
    {
        return self::current() === self::V2;
    }

    public static function isLux(): bool
    {
        return ! self::isV2();
    }
}
