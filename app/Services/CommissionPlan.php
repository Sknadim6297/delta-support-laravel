<?php

namespace App\Services;

use App\Models\CommissionPlanLevel;
use Illuminate\Support\Collection;

class CommissionPlan
{
    /**
     * Approved chart for a future engine. Stored inactive and not read by ROI or commission generation.
     *
     * @var array<int, string>
     */
    public const RATE_BY_LEVEL = [
        1 => '25.0000',
        2 => '12.5000',
        3 => '7.5000',
        4 => '5.0000',
        5 => '2.5000',
        6 => '2.5000',
        7 => '2.5000',
        8 => '2.5000',
        9 => '2.5000',
        10 => '2.5000',
        11 => '2.5000',
        12 => '2.5000',
        13 => '2.5000',
        14 => '2.5000',
        15 => '2.5000',
        16 => '2.5000',
        17 => '2.5000',
        18 => '5.0000',
        19 => '5.0000',
        20 => '7.5000',
    ];

    public function levels(): Collection
    {
        return CommissionPlanLevel::query()
            ->where('plan_code', CommissionPlanLevel::PLAN_CODE)
            ->orderBy('level')
            ->get();
    }

    public function isActive(): bool
    {
        return CommissionPlanLevel::query()
            ->where('plan_code', CommissionPlanLevel::PLAN_CODE)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function integrityErrors(): array
    {
        $levels = $this->levels();
        $errors = [];
        $storedLevels = $levels->pluck('level')->map(fn ($level): int => (int) $level)->all();

        if (count($storedLevels) !== 20 || count(array_unique($storedLevels)) !== 20) {
            $errors[] = 'The Delta Support commission plan must contain exactly 20 unique levels.';
        }

        foreach (self::RATE_BY_LEVEL as $level => $percentage) {
            $row = $levels->firstWhere('level', $level);
            if ($row === null || (string) $row->percentage !== $percentage) {
                $errors[] = "Level {$level} must be stored as {$percentage}.";
            }
        }

        if ($levels->contains(fn (CommissionPlanLevel $level): bool => $level->is_active)) {
            $errors[] = 'The Delta Support commission plan must stay inactive.';
        }

        return $errors;
    }
}
