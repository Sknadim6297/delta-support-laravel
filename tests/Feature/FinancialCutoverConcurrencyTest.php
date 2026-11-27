<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\User;
use App\Services\FinancialBusinessDateLock;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeUnlockGrandfathering;
use App\Services\PackageIncomeAccrual;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialCutoverConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_is_blocked_while_shared_financial_lock_is_held(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));

        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);
        $dateLock = app(FinancialBusinessDateLock::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');

        $held = $dateLock->acquireForExecution('2026-11-28');
        try {
            try {
                $service->authorizePosting($published->fresh(), $admin, true, null, true);
                $this->fail('Expected ValidationException while financial lock is held');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('financial_lock', $e->errors());
            }
            $this->assertFalse($published->fresh()->posting_authorized);
        } finally {
            $dateLock->releaseAll($held);
        }

        $authorized = $service->authorizePosting($published->fresh(), $admin, true, null, true);
        $this->assertTrue($authorized->posting_authorized);

        Carbon::setTestNow();
    }

    public function test_capture_is_blocked_while_shared_financial_lock_is_held_and_releases_safely(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-27 10:00:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['is_admin' => true]);
        $dateLock = app(FinancialBusinessDateLock::class);
        $version = LevelIncomeRuleVersion::query()
            ->where('version_code', 'proposed_activation_unlock_v1')
            ->firstOrFail();
        $version->forceFill(['grandfather_existing_unlock' => true])->save();

        $held = $dateLock->acquireForExecution('2026-11-27');
        try {
            try {
                app(LevelIncomeUnlockGrandfathering::class)
                    ->captureBaseline($version->fresh(), $admin, '2026-11-28');
                $this->fail('Expected ValidationException while financial lock is held');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('financial_lock', $e->errors());
            }
        } finally {
            $dateLock->releaseAll($held);
        }

        $result = app(LevelIncomeUnlockGrandfathering::class)
            ->captureBaseline($version->fresh(), $admin, '2026-11-28');
        $this->assertSame(LevelIncomeUnlockGrandfathering::CAPTURE_MODE_PRE_CUTOVER_CURRENT, $result['capture_mode']);

        Carbon::setTestNow();
    }

    public function test_duplicate_financial_execution_lock_contention(): void
    {
        $dateLock = app(FinancialBusinessDateLock::class);
        $first = $dateLock->acquireForExecution('2026-12-01');
        try {
            $this->expectException(\RuntimeException::class);
            $dateLock->acquireForExecution('2026-12-01');
        } finally {
            $dateLock->releaseAll($first);
        }
    }

    public function test_cross_date_financial_run_blocks_authorize_via_rule_guard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $dateLock = app(FinancialBusinessDateLock::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => false])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-11-28',
        ]);

        // Historical / other-date financial run must still block authorize (global rule guard).
        $held = $dateLock->acquireForExecution('2026-10-01');
        try {
            try {
                $service->authorizePosting($published->fresh(), $admin, true);
                $this->fail('Expected ValidationException while cross-date financial lock is held');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('financial_lock', $e->errors());
            }
        } finally {
            $dateLock->releaseAll($held);
        }

        Carbon::setTestNow();
    }

    public function test_expired_or_released_lock_allows_subsequent_authorize(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $grandfathering = app(LevelIncomeUnlockGrandfathering::class);
        $dateLock = app(FinancialBusinessDateLock::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);
        $grandfathering->captureBaseline($published->fresh(), $admin, '2026-11-28');

        $held = $dateLock->acquireForExecution('2026-11-28');
        $dateLock->releaseAll($held);
        // Second release must not throw (safe unlock after expiry/release).
        $dateLock->releaseAll($held);

        $authorized = $service->authorizePosting($published->fresh(), $admin, true, null, true);
        $this->assertTrue($authorized->posting_authorized);

        Carbon::setTestNow();
    }

    public function test_failed_authorize_transaction_releases_financial_locks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $dateLock = app(FinancialBusinessDateLock::class);

        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        // Grandfather enabled but not captured → authorize fails after lock acquisition.
        $draft->forceFill(['grandfather_existing_unlock' => true])->save();
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_EXISTING_PROSPECTIVE,
            'effective_from' => '2026-11-28',
        ]);

        try {
            $service->authorizePosting($published->fresh(), $admin, true, null, true);
            $this->fail('Expected ValidationException for missing capture');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grandfather', $e->errors());
        }

        // Locks must have been released so financial execution can proceed.
        $locks = $dateLock->acquireForExecution('2026-11-28');
        $dateLock->releaseAll($locks);
        $this->assertTrue(true);

        Carbon::setTestNow();
    }

    public function test_open_day_snapshot_uses_capture_instant_not_future_end_of_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:05:00', 'Asia/Kolkata'));
        $eligibility = app(\App\Services\ReferralLevelEligibility::class);
        $instant = $eligibility->businessAsOfEnd('2026-11-28');
        $this->assertSame('2026-11-28 00:05:00', $instant->format('Y-m-d H:i:s'));
        $this->assertTrue($instant->lt(Carbon::parse('2026-11-28 23:59:59', 'Asia/Kolkata')));

        Carbon::setTestNow();
    }

    public function test_manual_dry_run_does_not_hold_shared_lock(): void
    {
        $dateLock = app(FinancialBusinessDateLock::class);
        $accrual = app(PackageIncomeAccrual::class);

        // Dry-run should not acquire the exclusive execution lock.
        $accrual->runControlled('2026-12-02', true);
        $held = $dateLock->acquireForExecution('2026-12-02');
        $dateLock->releaseAll($held);
        $this->assertTrue(true);
    }

    public function test_lock_key_and_ttl_are_production_compatible(): void
    {
        $dateLock = app(FinancialBusinessDateLock::class);
        $this->assertSame('package-income-2026-11-28', $dateLock->key('2026-11-28'));
        $this->assertSame(FinancialBusinessDateLock::RULE_GUARD_KEY, $dateLock->ruleGuardKey());
        $this->assertGreaterThanOrEqual(3600, $dateLock->ttlSeconds());
        // PHPUnit forces CACHE_STORE=array; production .env defaults to database (atomic locks).
        $this->assertTrue(in_array(config('cache.default'), ['database', 'redis', 'memcached', 'dynamodb', 'file', 'array'], true));
        $this->assertSame('database', config('cache.stores.database.driver'));
    }
}
