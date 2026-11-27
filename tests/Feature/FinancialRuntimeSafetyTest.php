<?php

namespace Tests\Feature;

use App\Models\LevelIncomeRuleVersion;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Services\FinancialBusinessDateLock;
use App\Services\LevelIncomeRuleAdministration;
use App\Services\LevelIncomeRuleResolver;
use App\Services\LocalTestMarker;
use App\Services\PackageIncomeAccrual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialRuntimeSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_refuses_while_another_version_is_posting_authorized(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-12-01',
        ]);
        $published->forceFill(['grandfather_existing_unlock' => false])->save();
        $authorized = $service->authorizePosting($published->fresh(), $admin, true);
        $this->assertTrue($authorized->posting_authorized);

        $next = $service->cloneAsDraft($authorized, $admin);
        try {
            $service->publish($next, $admin, [
                'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
                'effective_from' => '2026-12-15',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('posting_authorized', $e->errors());
        }

        app(LevelIncomeRuleResolver::class)->clearCache();
        $this->assertSame($authorized->id, app(LevelIncomeRuleResolver::class)->authorizedVersion()?->id);
        $this->assertSame(LevelIncomeRuleVersion::STATUS_PUBLISHED, $authorized->fresh()->status);
    }

    public function test_resolver_sticky_cache_survives_clear_cache_only_when_reloaded_under_lock_sequence(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(LevelIncomeRuleAdministration::class);
        $draft = $service->cloneAsDraft(
            LevelIncomeRuleVersion::query()->where('version_code', 'proposed_activation_unlock_v1')->firstOrFail(),
            $admin
        );
        $published = $service->publish($draft, $admin, [
            'applicability' => LevelIncomeRuleVersion::APPLICABILITY_FUTURE_PURCHASES_ONLY,
            'effective_from' => '2026-12-01',
        ]);
        $published->forceFill(['grandfather_existing_unlock' => false])->save();
        $service->authorizePosting($published->fresh(), $admin, true);

        $resolver = app(LevelIncomeRuleResolver::class);
        $resolver->clearCache();
        $first = $resolver->authorizedVersion();
        $this->assertNotNull($first);

        // In-process sticky snapshot: clearCache is required to observe revoke.
        $service->revokePostingAuthorization($published->fresh(), $admin);
        $this->assertSame($first->id, $resolver->authorizedVersion()?->id);
        $resolver->clearCache();
        $this->assertNull($resolver->authorizedVersion());
    }

    public function test_refresh_owned_extends_lock_and_lost_ownership_aborts(): void
    {
        $dateLock = app(FinancialBusinessDateLock::class);
        $locks = $dateLock->acquireForExecution('2026-12-10');
        $this->assertIsArray($locks);
        $this->assertCount(2, $locks);
        try {
            $dateLock->refreshOwned($locks);
            $this->assertTrue(true);
        } finally {
            $dateLock->releaseAll($locks);
        }

        $orphan = $dateLock->make('2026-12-11');
        $this->assertTrue($orphan->get());
        $orphan->release();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ownership was lost');
        $dateLock->refreshOwned([$orphan]);
    }

    public function test_ownership_loss_after_plan_before_first_roi_credit_aborts_without_writes(): void
    {
        config(['deltasupport.package_income.enabled' => false]);

        $real = app(FinancialBusinessDateLock::class);
        $state = (object) ['refreshCalls' => 0];
        $proxy = new class($real, $state) extends FinancialBusinessDateLock
        {
            public function __construct(
                private readonly FinancialBusinessDateLock $inner,
                private readonly object $state
            ) {
            }

            public function acquireForExecution(string $businessDate): array
            {
                return $this->inner->acquireForExecution($businessDate);
            }

            public function refreshOwned(array $locks): void
            {
                $this->state->refreshCalls++;
                // Call 1: before plan. Call 2: after plan / before first ROI credit.
                if ($this->state->refreshCalls >= 2) {
                    throw new \RuntimeException('Financial lock ownership was lost (TTL expiry or steal). Aborting to avoid overlapping wallet writes.');
                }
                $this->inner->refreshOwned($locks);
            }

            public function releaseAll(array $locks): void
            {
                $this->inner->releaseAll($locks);
            }
        };
        $this->app->instance(FinancialBusinessDateLock::class, $proxy);
        $this->app->forgetInstance(PackageIncomeAccrual::class);

        $hills = Package::query()->create([
            'code' => 'DEMO-HILLS',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Hill Station Retreat',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $sponsor = Member::query()->create([
            'member_id' => 'STLCK001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Local Sponsor',
            'mobile_no' => '9000000001',
            'email' => 'sponsor-lock@local-test.invalid',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $buyer = Member::query()->create([
            'member_id' => 'STLCK002',
            'sponsor_id' => $sponsor->member_id,
            'sponsor_name' => $sponsor->member_name,
            'member_name' => 'Local Buyer',
            'mobile_no' => '9000000002',
            'email' => 'buyer-lock@local-test.invalid',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        PackagePurchase::query()->create([
            'reference' => 'PUR-STLCK001',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $sponsor->member_id,
            'package_id' => $hills->id,
            'package_code' => $hills->code,
            'package_name' => $hills->name,
            'package_type' => $hills->package_type,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => LocalTestMarker::PAYMENT_PREFIX.$sponsor->member_id.'-DEMO-HILLS',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => '2026-10-01 06:00:00',
            'reviewed_at' => '2026-10-01 06:00:00',
        ]);
        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-STLCK002',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $buyer->member_id,
            'package_id' => $hills->id,
            'package_code' => $hills->code,
            'package_name' => $hills->name,
            'package_type' => $hills->package_type,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => LocalTestMarker::PAYMENT_PREFIX.$buyer->member_id.'-DEMO-HILLS',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => '2026-10-01 06:00:00',
            'reviewed_at' => '2026-10-01 06:00:00',
        ]);

        try {
            app(PackageIncomeAccrual::class)->accruePurchases('2026-10-11', [$purchase->id]);
            $this->fail('Expected ownership-loss abort before ROI credit');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ownership was lost', $e->getMessage());
        }

        $this->assertSame(0, \App\Models\PackageRoiAccrual::query()->count());
        $this->assertSame(0, \App\Models\PackageLevelAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $sponsor->fresh()->working_wallet_amount);
        $this->assertGreaterThanOrEqual(2, $state->refreshCalls);
    }

    public function test_cache_value_flush_does_not_drop_database_financial_locks(): void
    {
        if (! Schema::hasTable('cache_locks')) {
            $this->markTestSkipped('cache_locks table missing');
        }

        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');

        $key = 'package-income-2026-12-20';
        $lock = Cache::lock($key, 60);
        $this->assertTrue($lock->get());

        try {
            $this->assertTrue(
                DB::table('cache_locks')->where('key', $key)->exists()
                || DB::table('cache_locks')->where('key', 'like', '%'.$key.'%')->exists()
            );

            // Value-store flush must not delete active lock rows.
            Cache::store('database')->flush();

            $stillLocked = DB::table('cache_locks')->where('key', $key)->exists()
                || DB::table('cache_locks')->where('key', 'like', '%'.$key.'%')->exists();
            $this->assertTrue($stillLocked, 'Financial lock row must survive cache value flush');

            $contender = Cache::lock($key, 60);
            $this->assertFalse($contender->get(), 'Second owner must not acquire while first holds DB lock');
        } finally {
            $lock->release();
            config(['cache.default' => 'array']);
            Cache::forgetDriver('database');
            Cache::forgetDriver('array');
        }
    }

    public function test_database_lock_steal_after_expiry_is_detectable_via_refresh_failure(): void
    {
        if (! Schema::hasTable('cache_locks')) {
            $this->markTestSkipped('cache_locks table missing');
        }

        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');

        $logicalKey = 'package-income-2026-12-21';
        $ownerA = Cache::lock($logicalKey, 1);
        $this->assertTrue($ownerA->get());

        try {
            // Lock rows may be prefix-scoped; expire whichever row was written.
            $updated = DB::table('cache_locks')
                ->where('key', $logicalKey)
                ->orWhere('key', 'like', '%'.$logicalKey)
                ->update(['expiration' => time() - 10]);
            $this->assertGreaterThan(0, $updated);

            $ownerB = Cache::lock($logicalKey, 30);
            $this->assertTrue($ownerB->get(), 'Expired lock must be acquirable by a new owner');

            // Original owner refresh must fail — callers must abort overlapping writes.
            $this->assertFalse($ownerA->refresh(30));

            $ownerB->release();
        } finally {
            try {
                $ownerA->release();
            } catch (\Throwable) {
            }
            config(['cache.default' => 'array']);
            Cache::forgetDriver('database');
            Cache::forgetDriver('array');
        }
    }

    public function test_resolver_clear_cache_does_not_touch_cache_locks_table(): void
    {
        if (! Schema::hasTable('cache_locks')) {
            $this->markTestSkipped('cache_locks table missing');
        }

        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');

        $key = FinancialBusinessDateLock::RULE_GUARD_KEY;
        $lock = Cache::lock($key, 60);
        $this->assertTrue($lock->get());

        try {
            app(LevelIncomeRuleResolver::class)->clearCache();
            app(LevelIncomeRuleResolver::class)->authorizedVersion();

            $this->assertTrue(
                DB::table('cache_locks')->where('key', $key)->exists()
                || DB::table('cache_locks')->where('key', 'like', '%'.$key.'%')->exists()
            );
        } finally {
            $lock->release();
            config(['cache.default' => 'array']);
            Cache::forgetDriver('database');
            Cache::forgetDriver('array');
        }
    }
}
