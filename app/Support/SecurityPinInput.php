<?php

namespace App\Support;

use Illuminate\Http\Request;

class SecurityPinInput
{
    public static function merge(Request $request, string ...$names): void
    {
        $merged = [];

        foreach ($names as $name) {
            $digits = $request->input($name.'_digits');
            if (! is_array($digits)) {
                continue;
            }

            $joined = '';
            foreach ($digits as $digit) {
                if (! is_scalar($digit)) {
                    continue;
                }
                $joined .= preg_replace('/\D/', '', (string) $digit) ?? '';
            }

            if ($joined !== '') {
                $merged[$name] = $joined;
            }
        }

        if ($merged !== []) {
            $request->merge($merged);
        }
    }
}
