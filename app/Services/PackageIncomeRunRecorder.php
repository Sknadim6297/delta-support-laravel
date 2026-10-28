<?php

namespace App\Services;

use App\Models\PackageIncomeRun;
use Illuminate\Support\Facades\Log;

class PackageIncomeRunRecorder
{
    public function record(?string $businessDate, string $status, int $roi, int $commissions, string $message): PackageIncomeRun
    {
        $run = PackageIncomeRun::query()->create([
            'business_date' => $this->date($businessDate),
            'status' => $status,
            'credited_roi' => $roi,
            'credited_commissions' => $commissions,
            'message' => $message,
        ]);

        $line = 'Package income '.$status.($run->business_date ? ' for '.$run->business_date->toDateString() : '').': '.$message;
        if (in_array($status, [PackageIncomeRun::FAILED, PackageIncomeRun::BLOCKED], true)) {
            Log::error($line);
        } elseif ($status === PackageIncomeRun::REFUSED) {
            Log::warning($line);
        } else {
            Log::info($line);
        }

        return $run;
    }

    private function date(?string $businessDate): ?string
    {
        if ($businessDate === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) {
            return null;
        }

        return $businessDate;
    }
}
