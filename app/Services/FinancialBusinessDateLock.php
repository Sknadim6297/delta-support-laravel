<?php

namespace App\Services;

use Illuminate\Cache\DatabaseLock;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Shared exclusive locks for financial execution and Level Income rule mutations.
 *
 * Per-date key: package-income-{YYYY-MM-DD} (legacy-compatible).
 * Global rule guard: level-income-posting-rules — serializes authorize/revoke/capture
 * with ANY non-dry financial plan+credit so cross-date recovery cannot observe a
 * mid-run authorization flip.
 *
 * Acquisition order (deadlock-safe): global rule guard first, then sorted date locks.
 *
 * MariaDB: DatabaseLock::refresh() returns false when UPDATE changes 0 rows (same-second
 * identical expiration). refreshOwned() verifies primary-write ownership + near-full TTL
 * before accepting that false, then retries refresh(ttl+1) and re-verifies if needed.
 */
class FinancialBusinessDateLock
{
    public const RULE_GUARD_KEY = 'level-income-posting-rules';

    /** Allowed clock skew (seconds) when requiring a near-full remaining lease. */
    private const LEASE_SKEW_SECONDS = 2;

    public function businessTimezone(): string
    {
        return (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
    }

    public function ttlSeconds(): int
    {
        return max(120, (int) config('deltasupport.package_income.business_date_lock_ttl_seconds', 3600));
    }

    /**
     * Remaining lease must be close to the configured full TTL to accept a same-second no-op.
     * Example: TTL 3600 → require remaining >= 3598 (60s remaining is not enough).
     */
    public function minimumRemainingLeaseSeconds(): int
    {
        return max(1, $this->ttlSeconds() - self::LEASE_SKEW_SECONDS);
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
        } catch (Throwable $e) {
            $this->releaseAll($locks);
            throw $e;
        }

        return $locks;
    }

    /**
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
        } catch (Throwable $e) {
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
        $ttl = $this->ttlSeconds();

        foreach ($locks as $lock) {
            $refreshed = $this->callRefresh($lock, $ttl, 'initial');
            if ($refreshed === true) {
                continue;
            }

            // false: MariaDB same-second no-op or real loss — verify before continuing.
            $this->assertDatabaseLockOwnedWithNearFullLease($lock);

            $retried = $this->callRefresh($lock, $ttl + 1, 'retry');
            if ($retried === true) {
                continue;
            }

            // retry also false — re-verify ownership / expiry / near-full lease.
            $this->assertDatabaseLockOwnedWithNearFullLease($lock);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function callRefresh(Lock $lock, int $seconds, string $phase): bool
    {
        try {
            return $lock->refresh($seconds) === true;
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf(
                    'Financial lock %s refresh failed (%s). Aborting to avoid overlapping writes.',
                    $phase,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Fail closed unless this DatabaseLock still owns an unexpired primary row
     * with remaining lease close to the configured full TTL.
     *
     * @throws RuntimeException
     */
    public function assertDatabaseLockOwnedWithNearFullLease(Lock $lock): void
    {
        if (! $lock instanceof DatabaseLock) {
            throw new RuntimeException(
                'Financial lock ownership was lost (non-database refresh returned false). Aborting to avoid overlapping wallet writes.'
            );
        }

        $row = $this->readDatabaseLockRow($lock);
        if ($row === null) {
            throw new RuntimeException(
                'Financial lock ownership was lost (missing lock row). Aborting to avoid overlapping wallet writes.'
            );
        }

        if ($row->owner !== (string) $lock->owner()) {
            throw new RuntimeException(
                'Financial lock ownership was lost (stolen lock). Aborting to avoid overlapping wallet writes.'
            );
        }

        $now = time();
        if ($row->expiration <= $now) {
            throw new RuntimeException(
                'Financial lock ownership was lost (expired lock). Aborting to avoid overlapping wallet writes.'
            );
        }

        $remaining = $row->expiration - $now;
        if ($remaining < $this->minimumRemainingLeaseSeconds()) {
            throw new RuntimeException(
                sprintf(
                    'Financial lock ownership was lost (insufficient remaining lease %ds; need >= %ds for TTL %ds). Aborting to avoid overlapping wallet writes.',
                    $remaining,
                    $this->minimumRemainingLeaseSeconds(),
                    $this->ttlSeconds()
                )
            );
        }
    }

    /**
     * Read lock row from the DatabaseLock's connection/table/key via the write PDO.
     *
     * @return object{key: string, owner: string, expiration: int, table: string, connection: string}|null
     *
     * @throws RuntimeException
     */
    public function readDatabaseLockRow(DatabaseLock $lock): ?object
    {
        $meta = $this->databaseLockMeta($lock);

        try {
            $row = $meta['connection']
                ->table($meta['table'])
                ->useWritePdo()
                ->where('key', $meta['key'])
                ->first();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Financial lock ownership verification failed ('.$e->getMessage().'). Aborting to avoid overlapping writes.',
                0,
                $e
            );
        }

        if ($row === null) {
            return null;
        }

        return (object) [
            'key' => (string) $row->key,
            'owner' => (string) $row->owner,
            'expiration' => (int) $row->expiration,
            'table' => $meta['table'],
            'connection' => (string) $meta['connection']->getName(),
        ];
    }

    /**
     * @return array{key: string, table: string, owner: string, connection: \Illuminate\Database\Connection}
     */
    public function databaseLockMeta(DatabaseLock $lock): array
    {
        // DatabaseLock does not expose name/table/connection publicly; read the instance fields.
        $reflection = new \ReflectionClass(DatabaseLock::class);

        $nameProp = $reflection->getParentClass()->getProperty('name');
        $nameProp->setAccessible(true);

        $tableProp = $reflection->getProperty('table');
        $tableProp->setAccessible(true);

        $connectionProp = $reflection->getProperty('connection');
        $connectionProp->setAccessible(true);

        return [
            'key' => (string) $nameProp->getValue($lock),
            'table' => (string) $tableProp->getValue($lock),
            'owner' => (string) $lock->owner(),
            'connection' => $connectionProp->getValue($lock),
        ];
    }

    /**
     * @param  list<Lock>  $locks
     */
    public function releaseAll(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            try {
                $lock->release();
            } catch (Throwable) {
                // Lock may already have expired or been released; never block unlock paths.
            }
        }
    }
}
