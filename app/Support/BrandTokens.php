<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Storage;

class BrandTokens
{
    public const DEFAULTS = [
        'primary' => '#2563eb',
        'primary_hover' => '#1d4ed8',
        'navy' => '#0f172a',
        'canvas' => '#f5f7fb',
        'border' => '#e2e8f0',
    ];

    public const LABELS = [
        'primary' => 'Primary',
        'primary_hover' => 'Primary hover',
        'navy' => 'Navy',
        'canvas' => 'Canvas',
        'border' => 'Border',
    ];

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        $colors = [];
        foreach (self::palette() as $key => $value) {
            $colors[self::LABELS[$key]] = $value;
        }

        return $colors;
    }

    /**
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $saved = [];

        try {
            $settings = CompanySetting::query()->first();
            if ($settings) {
                $saved = [
                    'primary' => $settings->primary_color,
                    'primary_hover' => $settings->primary_hover_color,
                    'navy' => $settings->navy_color,
                    'canvas' => $settings->canvas_color,
                    'border' => $settings->border_color,
                ];
            }
        } catch (\Throwable) {
            $saved = [];
        }

        $palette = [];
        foreach (self::DEFAULTS as $key => $default) {
            $palette[$key] = self::normalize(isset($saved[$key]) ? (string) $saved[$key] : null) ?? $default;
        }

        return $palette;
    }

    public static function normalize(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $match) === 1) {
            $short = $match[1];
            $value = '#'.$short[0].$short[0].$short[1].$short[1].$short[2].$short[2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    public static function focus(string $hex): string
    {
        $hex = ltrim(self::normalize($hex) ?? self::DEFAULTS['primary'], '#');

        return sprintf(
            'rgba(%d, %d, %d, 0.16)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public static function isManagedLogo(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^branding-logos/[A-Za-z0-9]+\.[A-Za-z0-9]+$#', $path) === 1;
    }

    public static function deleteManaged(?string $path): void
    {
        if (! self::isManagedLogo($path)) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function logoUrl(): string
    {
        try {
            $settings = CompanySetting::query()->first();
        } catch (\Throwable) {
            return asset('site/img/logo.png');
        }

        if (
            $settings
            && self::isManagedLogo($settings->logo_path)
            && Storage::disk('local')->exists($settings->logo_path)
        ) {
            return route('brand.logo');
        }

        return asset('site/img/logo.png');
    }
}
