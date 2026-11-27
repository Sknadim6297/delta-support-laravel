<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Shared exclusive lock for one Asia/Kolkata business-date financial execution.
 *
 * Key format matches legacy PackageIncomeAccrual locks: package-income-{YYYY-MM-DD}.
 * Used by financial plan+credit AND Level Income authorize/revoke/capture so rule
 * mutations cannot interleave with an active financial run for a conflicting date.
 *
 * Time-based schedule guards remain separate; this lock is the mutual-exclusion guarantee.
 */
class FinancialBusinessDateLock
{
    public function businessTimezone(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }

    public function ttlSeconds(): int
    {
        return max(120, (int) config('deltasupport.package_income.business_date_lock_ttl_seconds', 3600));
    }

    public function key(string $businessDate): string
    {
        return 'package-income-'.$businessDate;
    }

    public function make(string $businessDate): Lock
    {
        return Cache::lock($this->key($businessDate), $this->ttlSeconds());
    }

    /**
     * Non-blocking acquire for financial execution. Caller must release in finally.
     *
     * @throws RuntimeException
     */
    public function acquireForExecution(string $businessDate): Lock
    {
        $lock = $this->make($businessDate);
        if (! $lock->get()) {
            throw new RuntimeException('Package income for '.$businessDate.' is already running.');
        }

        return $lock;
    }

    /**
     * Acquire exclusive locks for rule mutation (authorize/revoke/capture).
     * Dates are sorted to keep a stable global order and avoid multi-date deadlocks.
     *
     * @param  list<string>  $businessDates
     * @return list<Lock>
     *
     * @throws ValidationException
     */
    public function acquireForRuleMutation(array $businessDates, string $action): array
    {
        $dates = array_values(array_unique(array_filter(array_map(
            static fn ($d): string => is_string($d) ? trim($d) : '',
            $businessDates
        ))));
        sort($dates);

        if ($dates === []) {
            return [];
        }

        $locks = [];
        try {
            foreach ($dates as $date) {
                $lock = $this->make($date);
                if (! $lock->get()) {
                    throw ValidationException::withMessages([
                        'financial_lock' => sprintf(
                            'Cannot %s while a financial run is active for business date %s (shared lock %s). Wait for that run to finish, then retry. Do not authorize after the effective date cycle has already succeeded.',
                            $action,
                            $date,
                            $this->key($date)
                        ),
                    ]);
                }
                $locks[] = $lock;
            }
        } catch (\Throwable $e) {
            $this->releaseAll($locks);
            throw $e;
        }

        return $locks;
    }

    /**
     * @param  list<Lock>  $locks
     */
    public function releaseAll(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Lock may already have expired or been released; never block unlock paths.
            }
        }
    }
}
