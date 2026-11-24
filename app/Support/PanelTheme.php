<?php

namespace App\Support;

/**
 * Admin/Member panel shell theme.
 * Panel V2 is the only supported shell after T4. Lux assets and switching were removed;
 * rollback is via git tag t4-pre-legacy-removal.
 */
final class PanelTheme
{
    public const LUX = 'lux';

    public const V2 = 'v2';

    public static function current(): string
    {
        return self::V2;
    }

    public static function isV2(): bool
    {
        return true;
    }

    public static function isLux(): bool
    {
        return false;
    }
}
