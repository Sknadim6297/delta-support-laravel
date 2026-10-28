<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public const MESSAGE = 'Use at least 12 characters with uppercase, lowercase, a number, and a symbol.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::accepts((string) $value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function accepts(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1
            && strcasecmp($password, 'admin@1234') !== 0;
    }

    public static function generate(): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $symbols = '!#$%*-_';
        $all = $lower.$upper.$digits.$symbols;
        $chars = [
            self::pick($lower),
            self::pick($upper),
            self::pick($digits),
            self::pick($symbols),
        ];

        for ($index = 0; $index < 16; $index++) {
            $chars[] = self::pick($all);
        }

        for ($index = count($chars) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$chars[$index], $chars[$swap]] = [$chars[$swap], $chars[$index]];
        }

        return implode('', $chars);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
