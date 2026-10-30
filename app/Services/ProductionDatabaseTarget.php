<?php

namespace App\Services;

use RuntimeException;

class ProductionDatabaseTarget
{
    /**
     * Databases that already hold development or isolated test data.
     * A launch command must never migrate, seed, or wipe these.
     *
     * @var list<string>
     */
    public const PROTECTED = [
        'delta_support',
        'delta_support_income_test',
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
    ];

    public function isProtected(string $name): bool
    {
        return in_array(strtolower(trim($name)), self::PROTECTED, true);
    }

    public function assertFreshName(string $name): string
    {
        $name = trim($name);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            throw new RuntimeException('Refused. The database name is not a plain identifier. Nothing was changed.');
        }
        if ($this->isProtected($name)) {
            throw new RuntimeException('Refused. '.$name.' is protected. Nothing was changed.');
        }

        return $name;
    }
}
