<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Shared exclusive locks for financial execution and Level Income rule mutations.
 *
 * Per-date key: package-income-{YYYY-MM-DD} (legacy-compatible).
 * Global rule guard: level-income-posting-rules — serializes authorize/revoke/capture
 * with ANY non-dry financial plan+credit so cross-date recovery cannot observe a
 * mid-run authorization flip.
 *
 * Acquisition order (deadlock-safe): global rule guard first, then sorted date locks.
 * Time-based schedule guards remain separate; these locks are the mutual-exclusion guarantee.
 */
class FinancialBusinessDateLock
{
    public const RULE_GUARD_KEY = 'level-income-posting-rules';

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

    public function ruleGuardKey(): string
    {
        return self::RULE_GUARD_KEY;
    }

    public function make(string $businessDate): Lock
    {
        return Cache::lock($this->key($businessDate), $this->ttlSeconds());
    }

    public function makeRuleGuard(): Lock
    {
        return Cache::lock($this->ruleGuardKey(), $this->ttlSeconds());
    }

    /**
     * Non-blocking acquire for financial execution: rule guard + business-date lock.
     * Caller must releaseAll() in finally and refreshOwned() during long runs.
     *
     * @return list<Lock>
     *
     * @throws RuntimeException
     */
    public function acquireForExecution(string $businessDate): array
    {
        $locks = [];
        try {
            $guard = $this->makeRuleGuard();
            if (! $guard->get()) {
                throw new RuntimeException(
                    'Package income for '.$businessDate.' cannot start: Level Income rule mutation or another financial run holds '.$this->ruleGuardKey().'.'
                );
            }
            $locks[] = $guard;

            $dateLock = $this->make($businessDate);
            if (! $dateLock->get()) {
                throw new RuntimeException('Package income for '.$businessDate.' is already running.');
            }
            $locks[] = $dateLock;
        } catch (\Throwable $e) {
            $this->releaseAll($locks);
            throw $e;
        }

        return $locks;
    }

    /**
     * Acquire exclusive locks for rule mutation (authorize/revoke/capture).
     * Global rule guard first, then sorted business dates.
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

        $locks = [];
        try {
            $guard = $this->makeRuleGuard();
            if (! $guard->get()) {
                throw ValidationException::withMessages([
                    'financial_lock' => sprintf(
                        'Cannot %s while a financial run or another rule mutation is active (shared lock %s). Wait for that work to finish, then retry. Do not authorize after the effective date cycle has already succeeded.',
                        $action,
                        $this->ruleGuardKey()
                    ),
                ]);
            }
            $locks[] = $guard;

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
     * Extend ownership so TTL expiry cannot silently allow overlapping writers.
     *
     * @param  list<Lock>  $locks
     *
     * @throws RuntimeException
     */
    public function refreshOwned(array $locks): void
    {
        foreach ($locks as $lock) {
            try {
                $ok = $lock->refresh($this->ttlSeconds());
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    'Financial lock refresh is unsupported or failed ('.$e->getMessage().'). Aborting to avoid overlapping writes.',
                    0,
                    $e
                );
            }
            if ($ok !== true) {
                throw new RuntimeException(
                    'Financial lock ownership was lost (TTL expiry or steal). Aborting to avoid overlapping wallet writes.'
                );
            }
        }
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
