<?php

namespace Tests\Feature;

use App\Services\FinancialBusinessDateLock;
use Illuminate\Cache\DatabaseLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MariaDbLockRefreshHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('cache_locks')) {
            $this->markTestSkipped('cache_locks table missing');
        }

        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');
        Cache::forgetDriver('array');
    }

    protected function tearDown(): void
    {
        config(['cache.default' => 'array']);
        Cache::forgetDriver('database');
        Cache::forgetDriver('array');
        parent::tearDown();
    }

    public function test_repeated_same_second_refresh_succeeds_for_owned_locks(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);
        $this->assertSame(118, $service->minimumRemainingLeaseSeconds());

        $locks = $service->acquireForExecution('2026-12-22');
        $this->assertCount(2, $locks);

        try {
            $service->refreshOwned($locks);
            $service->refreshOwned($locks);
            $service->refreshOwned($locks);

            foreach ($locks as $lock) {
                /** @var DatabaseLock $lock */
                $row = $service->readDatabaseLockRow($lock);
                $this->assertNotNull($row);
                $this->assertSame($lock->owner(), $row->owner);
                $this->assertGreaterThanOrEqual($service->minimumRemainingLeaseSeconds(), $row->expiration - time());
            }
        } finally {
            $service->releaseAll($locks);
        }
    }

    public function test_ownership_loss_between_initial_verification_and_retry_is_rejected(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);

        $real = Cache::lock('package-income-2026-12-30', 120);
        $this->assertTrue($real->get());
        $this->assertInstanceOf(DatabaseLock::class, $real);

        $meta = $service->databaseLockMeta($real);
        $calls = 0;
        $proxy = new class($meta['connection'], $meta['table'], $meta['key'], 120, $meta['owner'], $calls) extends DatabaseLock
        {
            public function __construct(
                $connection,
                $table,
                $name,
                $seconds,
                $owner,
                private int &$calls
            ) {
                parent::__construct($connection, $table, $name, $seconds, $owner);
            }

            public function refresh($seconds = null)
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return false; // force same-second path
                }
                // Steal after initial verification, before retry completes usefully.
                $this->connection->table($this->table)
                    ->where('key', $this->name)
                    ->update(['owner' => 'stolen-between-verify-and-retry', 'expiration' => time() + 120]);

                return false; // retry also false
            }
        };

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('stolen lock');
            $service->refreshOwned([$proxy]);
        } finally {
            try {
                $real->forceRelease();
            } catch (\Throwable) {
            }
        }
    }

    public function test_retry_returning_false_requires_reverification(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);

        $real = Cache::lock('package-income-2026-12-31', 120);
        $this->assertTrue($real->get());
        $this->assertInstanceOf(DatabaseLock::class, $real);
        $meta = $service->databaseLockMeta($real);

        $calls = 0;
        $proxy = new class($meta['connection'], $meta['table'], $meta['key'], 120, $meta['owner'], $calls) extends DatabaseLock
        {
            public function __construct(
                $connection,
                $table,
                $name,
                $seconds,
                $owner,
                private int &$calls
            ) {
                parent::__construct($connection, $table, $name, $seconds, $owner);
            }

            public function refresh($seconds = null)
            {
                $this->calls++;

                return false; // initial + retry both false; row still owned with near-full lease
            }
        };

        try {
            $service->refreshOwned([$proxy]);
            $this->assertSame(2, $calls, 'initial false + retry false must both run');
            $row = $service->readDatabaseLockRow($proxy);
            $this->assertNotNull($row);
            $this->assertSame($meta['owner'], $row->owner);
        } finally {
            try {
                $real->forceRelease();
            } catch (\Throwable) {
            }
        }
    }

    public function test_retry_throwing_database_exception_is_not_swallowed(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);

        $real = Cache::lock('package-income-2027-01-01', 120);
        $this->assertTrue($real->get());
        $this->assertInstanceOf(DatabaseLock::class, $real);
        $meta = $service->databaseLockMeta($real);

        $calls = 0;
        $proxy = new class($meta['connection'], $meta['table'], $meta['key'], 120, $meta['owner'], $calls) extends DatabaseLock
        {
            public function __construct(
                $connection,
                $table,
                $name,
                $seconds,
                $owner,
                private int &$calls
            ) {
                parent::__construct($connection, $table, $name, $seconds, $owner);
            }

            public function refresh($seconds = null)
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return false;
                }

                throw new \RuntimeException('simulated database refresh failure');
            }
        };

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('retry refresh failed');
            $service->refreshOwned([$proxy]);
        } finally {
            try {
                $real->forceRelease();
            } catch (\Throwable) {
            }
        }
    }

    public function test_insufficient_remaining_lease_is_rejected(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 3600]);
        $service = app(FinancialBusinessDateLock::class);
        $this->assertSame(3598, $service->minimumRemainingLeaseSeconds());

        $lock = Cache::lock('package-income-2026-12-26', 3600);
        $this->assertTrue($lock->get());
        $this->assertInstanceOf(DatabaseLock::class, $lock);
        $meta = $service->databaseLockMeta($lock);

        try {
            // 60s remaining is nowhere near a 3600s TTL — must fail closed on no-op path.
            $meta['connection']->table($meta['table'])
                ->where('key', $meta['key'])
                ->update(['expiration' => time() + 60]);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('insufficient remaining lease');
            $service->assertDatabaseLockOwnedWithNearFullLease($lock);
        } finally {
            try {
                $lock->forceRelease();
            } catch (\Throwable) {
            }
        }
    }

    public function test_custom_lock_table_and_cache_prefix_are_honored(): void
    {
        Schema::create('custom_cache_locks', function ($table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        config([
            'cache.default' => 'database',
            'cache.prefix' => 'delta-hotfix-',
            'cache.stores.database.prefix' => 'delta-hotfix-',
            'cache.stores.database.lock_table' => 'custom_cache_locks',
            'deltasupport.package_income.business_date_lock_ttl_seconds' => 120,
        ]);
        Cache::forgetDriver('database');

        $service = app(FinancialBusinessDateLock::class);
        $lock = Cache::lock('package-income-2027-01-02', 120);
        $this->assertTrue($lock->get());
        $this->assertInstanceOf(DatabaseLock::class, $lock);

        try {
            $meta = $service->databaseLockMeta($lock);
            $this->assertSame('custom_cache_locks', $meta['table']);
            $this->assertSame('delta-hotfix-package-income-2027-01-02', $meta['key']);
            $this->assertSame($meta['owner'], $lock->owner());

            $row = $service->readDatabaseLockRow($lock);
            $this->assertNotNull($row);
            $this->assertSame('custom_cache_locks', $row->table);
            $this->assertSame($meta['key'], $row->key);

            $service->refreshOwned([$lock]);
        } finally {
            try {
                $lock->forceRelease();
            } catch (\Throwable) {
            }
            Schema::dropIfExists('custom_cache_locks');
            config([
                'cache.prefix' => env('CACHE_PREFIX', \Illuminate\Support\Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),
                'cache.stores.database.lock_table' => null,
                'cache.stores.database.prefix' => null,
            ]);
            Cache::forgetDriver('database');
        }
    }

    public function test_global_and_business_date_locks_are_both_verified(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);

        $locks = $service->acquireForExecution('2026-12-27');
        try {
            $this->assertCount(2, $locks);
            $guardMeta = $service->databaseLockMeta($locks[0]);
            $dateMeta = $service->databaseLockMeta($locks[1]);

            $this->assertStringContainsString(FinancialBusinessDateLock::RULE_GUARD_KEY, $guardMeta['key']);
            $this->assertStringContainsString('package-income-2026-12-27', $dateMeta['key']);
            $this->assertSame($guardMeta['table'], $dateMeta['table']);

            $service->refreshOwned($locks);

            $this->assertNotNull($service->readDatabaseLockRow($locks[0]));
            $this->assertNotNull($service->readDatabaseLockRow($locks[1]));
        } finally {
            $service->releaseAll($locks);
        }
    }

    public function test_expired_stolen_and_missing_locks_fail_closed(): void
    {
        config(['deltasupport.package_income.business_date_lock_ttl_seconds' => 120]);
        $service = app(FinancialBusinessDateLock::class);

        $expired = Cache::lock('package-income-exp', 120);
        $this->assertTrue($expired->get());
        $meta = $service->databaseLockMeta($expired);
        $meta['connection']->table($meta['table'])->where('key', $meta['key'])->update(['expiration' => time() - 1]);
        try {
            $service->assertDatabaseLockOwnedWithNearFullLease($expired);
            $this->fail('Expected expired lock rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('expired lock', $e->getMessage());
        } finally {
            try {
                $expired->forceRelease();
            } catch (\Throwable) {
            }
        }

        $stolen = Cache::lock('package-income-stolen', 120);
        $this->assertTrue($stolen->get());
        $meta = $service->databaseLockMeta($stolen);
        $meta['connection']->table($meta['table'])->where('key', $meta['key'])->update([
            'owner' => 'other',
            'expiration' => time() + 120,
        ]);
        try {
            $service->assertDatabaseLockOwnedWithNearFullLease($stolen);
            $this->fail('Expected stolen lock rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('stolen lock', $e->getMessage());
        } finally {
            try {
                $stolen->forceRelease();
            } catch (\Throwable) {
            }
        }

        $missing = Cache::lock('package-income-missing', 120);
        $this->assertTrue($missing->get());
        $meta = $service->databaseLockMeta($missing);
        $meta['connection']->table($meta['table'])->where('key', $meta['key'])->delete();
        try {
            $service->assertDatabaseLockOwnedWithNearFullLease($missing);
            $this->fail('Expected missing lock rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('missing lock row', $e->getMessage());
        }
    }

    public function test_array_driver_false_refresh_still_fails_closed(): void
    {
        config(['cache.default' => 'array']);
        Cache::forgetDriver('array');

        $service = app(FinancialBusinessDateLock::class);
        $lock = Cache::lock('package-income-2026-12-29', 60);
        $this->assertTrue($lock->get());
        $lock->release();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-database refresh returned false');
        $service->refreshOwned([$lock]);
    }
}
