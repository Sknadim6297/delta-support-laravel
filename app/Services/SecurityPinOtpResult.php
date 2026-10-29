<?php

namespace App\Services;

class SecurityPinOtpResult
{
    public function __construct(
        public bool $delivered,
        public ?string $blocker = null,
    ) {
    }
}
