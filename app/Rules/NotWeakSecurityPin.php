<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotWeakSecurityPin implements ValidationRule
{
    public const MESSAGE = 'Choose a PIN that is not easy to guess. Repeated and sequential digits are not allowed.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::accepts(is_scalar($value) ? (string) $value : '')) {
            $fail(self::MESSAGE);
        }
    }

    public static function accepts(string $pin): bool
    {
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            return false;
        }

        if (preg_match('/^(\d)\1{5}$/', $pin) === 1) {
            return false;
        }

        $ascending = true;
        $descending = true;
        for ($index = 1; $index < 6; $index++) {
            $previous = (int) $pin[$index - 1];
            $current = (int) $pin[$index];
            if ($current !== $previous + 1) {
                $ascending = false;
            }
            if ($current !== $previous - 1) {
                $descending = false;
            }
        }

        if ($ascending || $descending) {
            return false;
        }

        return ! in_array($pin, ['121212', '112233', '123123', '123321'], true);
    }
}
