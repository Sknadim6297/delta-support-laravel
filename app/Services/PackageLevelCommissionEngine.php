<?php

namespace App\Services;

use RuntimeException;

/**
 * Boundary for a future package-purchase commission run.
 * Evaluation is read-only. Crediting stays closed until a commission base is approved.
 */
class PackageLevelCommissionEngine
{
    public function __construct(private readonly PackageIncomeReadiness $readiness)
    {
    }

    /**
     * @param  list<string>  $memberIds
     * @return array<string, mixed>
     */
    public function evaluate(array $memberIds): array
    {
        return $this->readiness->report($memberIds);
    }

    public function credit(): never
    {
        throw new RuntimeException('Package level commission is not enabled. No commission base is stored, and this engine does not credit wallets.');
    }
}
