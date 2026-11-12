<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomeAccrual;
use App\Services\PostedFinancialCycleAudit;
use App\Services\WalletLedgerPoster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FinancialAutomationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_dry_run_after_a_successful_commission_day_reports_zero_pending_rows(): void
    {
        $this->arm();
        $this->seedCommissionTree();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));

        $first = app(PackageIncomeAccrual::class)->runControlled('2026-10-11');
        $this->assertSame(1, $first['roi_records']);
        $this->assertSame(4, $first['commission_records']);
        $this->assertSame(4, PackageLevelAccrual::query()->where('status', 'accrued')->count());

        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-10-11', true);
        $this->assertTrue($dry['dry_run']);
        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, $dry['commission_records']);

        $this->artisan('financial:daily', ['--dry-run' => true, '--date' => '2026-10-11'])
            ->assertSuccessful()
            ->expectsOutputToContain('ROI records: 0')
            ->expectsOutputToContain('Level commission records: 0');
    }

    public function test_rerunning_the_same_business_date_cannot_credit_commissions_twice(): void
    {
        $this->arm();
        $tree = $this->seedCommissionTree();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));

        $this->artisan('financial:daily')->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful();

        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame(4, PackageLevelAccrual::query()->count());
        $this->assertSame(4, WalletLedgerEntry::query()->where('wallet', 'working')->count());
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame('0.0600', $tree['sponsor']->fresh()->working_wallet_amount);
        $this->assertSame('0.2500', $tree['buyer']->fresh()->roi_wallet_amount);
        $reference = PackageLevelAccrual::query()->where('member_id', 'ST100001')->value('reference');
        $this->assertSame(
            WalletLedgerEntry::query()->where('reference', $reference)->value('idempotency_key'),
            $reference
        );
    }

    public function test_unique_constraints_block_duplicate_accrual_keys(): void
    {
        $this->arm();
        $tree = $this->seedCommissionTree();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();

        $roi = PackageRoiAccrual::query()->firstOrFail();
        $level = PackageLevelAccrual::query()->firstOrFail();

        try {
            DB::table('package_roi_accruals')->insert([
                'reference' => 'PROI-DUP',
                'idempotency_key' => 'dup-roi',
                'package_purchase_id' => $roi->package_purchase_id,
                'member_id' => $roi->member_id,
                'member_name' => $roi->member_name,
                'package_code' => $roi->package_code,
                'package_name' => $roi->package_name,
                'business_date' => $roi->business_date->toDateString(),
                'amount' => '0.2500',
                'status' => 'accrued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Duplicate ROI purchase/date should be refused.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }

        try {
            DB::table('package_level_accruals')->insert([
                'reference' => 'PLVL-DUP',
                'idempotency_key' => 'dup-level',
                'package_purchase_id' => $level->package_purchase_id,
                'member_id' => $level->member_id,
                'member_name' => $level->member_name,
                'from_member_id' => $level->from_member_id,
                'from_member_name' => $level->from_member_name,
                'level' => $level->level,
                'package_code' => $level->package_code,
                'package_name' => $level->package_name,
                'business_date' => $level->business_date->toDateString(),
                'pool_amount' => $level->pool_amount,
                'rate_percentage' => $level->rate_percentage,
                'amount' => $level->amount,
                'status' => 'accrued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Duplicate level purchase/member/date should be refused.');
        } catch (\Throwable $exception) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame(4, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0600', $tree['sponsor']->fresh()->working_wallet_amount);
    }

    public function test_partial_commission_failure_rolls_back_the_failed_row_and_retry_finishes_without_doubling(): void
    {
        $this->arm();
        $tree = $this->seedCommissionTree();
        $sponsor = $tree['sponsor'];
        $buyer = $tree['buyer'];

        $calls = 0;
        $real = app(WalletLedgerPoster::class);
        $poster = Mockery::mock(WalletLedgerPoster::class)->makePartial();
        $poster->shouldReceive('append')->andReturnUsing(function (...$args) use (&$calls, $real) {
            $calls++;
            // First append is ROI; second is the first commission. Fail that commission once.
            if ($calls === 2) {
                throw new RuntimeException('ledger failed on commission');
            }

            return $real->append(...$args);
        });
        $this->app->instance(WalletLedgerPoster::class, $poster);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        try {
            app(PackageIncomeAccrual::class)->runControlled('2026-10-11');
            $this->fail('Commission ledger failure should stop the run.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ledger failed on commission', $exception->getMessage());
        }

        $this->assertSame(1, PackageRoiAccrual::query()->where('status', 'accrued')->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame(0, WalletLedgerEntry::query()->where('wallet', 'working')->count());
        $this->assertSame('0.0000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.2500', $buyer->fresh()->roi_wallet_amount);

        $this->app->instance(WalletLedgerPoster::class, $real);
        $retry = app(PackageIncomeAccrual::class)->runControlled('2026-10-11');
        $this->assertSame(0, $retry['roi_records']);
        $this->assertSame(4, $retry['commission_records']);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame(4, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0600', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.2500', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame(4, WalletLedgerEntry::query()->where('wallet', 'working')->count());
    }

    public function test_recovery_after_failed_run_does_not_double_prior_success_on_retry(): void
    {
        $this->arm();
        $tree = $this->seedCommissionTree();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 01:00:00', 'Asia/Kolkata'));
        \App\Models\PackageIncomeRun::query()->create([
            'business_date' => '2026-10-10',
            'status' => 'failed',
            'credited_roi' => 0,
            'credited_commissions' => 0,
            'message' => 'The previous run failed.',
        ]);

        $this->artisan('financial:recover')->assertSuccessful();
        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '2026-10-10')->count());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();
        $wallet = $tree['sponsor']->fresh()->working_wallet_amount;
        $roiWallet = $tree['buyer']->fresh()->roi_wallet_amount;

        // Before today's schedule time so recover retries only the failed yesterday.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 00:05:00', 'Asia/Kolkata'));
        \App\Models\PackageIncomeRun::query()->whereDate('business_date', '2026-10-11')->update(['status' => 'failed']);
        $this->artisan('financial:recover')
            ->assertSuccessful()
            ->expectsOutputToContain('scheduled run has not started');

        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-10-11')->count());
        $this->assertSame(4, PackageLevelAccrual::query()->whereDate('business_date', '2026-10-11')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '2026-10-12')->count());
        $this->assertSame($wallet, $tree['sponsor']->fresh()->working_wallet_amount);
        $this->assertSame($roiWallet, $tree['buyer']->fresh()->roi_wallet_amount);
        $this->assertSame('succeeded', \App\Models\PackageIncomeRun::query()->whereDate('business_date', '2026-10-11')->latest('id')->value('status'));
    }

    public function test_read_only_posted_audit_reports_alignment_for_a_posted_day(): void
    {
        $this->arm();
        $this->seedCommissionTree();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();

        $report = app(PostedFinancialCycleAudit::class)->inspect(['2026-10-11']);
        $this->assertTrue($report['dates'][0]['ok']);
        $this->assertSame(1, $report['dates'][0]['roi']['accrued']);
        $this->assertSame(4, $report['dates'][0]['commissions']['accrued']);
        $this->assertTrue($report['unique_indexes']['roi']);
        $this->assertTrue($report['unique_indexes']['level']);
        $this->assertTrue($report['unique_indexes']['ledger_reference']);
        $this->assertTrue($report['unique_indexes']['ledger_idempotency']);
        $this->assertSame([], array_values(array_filter($report['rates'], fn (array $rate): bool => ! $rate['matches'])));

        // DST-ROI-002 config is present in app config; tour income stays absent.
        $this->assertNotNull($report['package_income']['DST-ROI-002']);
        $this->assertNull($report['package_income']['DST-TOUR-003']);

        $this->artisan('financial:audit-posted', [
            '--database' => (string) DB::connection()->getDatabaseName(),
            '--dates' => '2026-10-11',
        ])->assertSuccessful();
    }

    /**
     * @return array{buyer: Member, sponsor: Member}
     */
    private function seedCommissionTree(): array
    {
        $this->member('ST666666', 'ST666666', 'root@example.test');
        $locked = $this->member('ST100005', 'ST666666', 'locked@example.test');
        $fourth = $this->member('ST100004', 'ST100005', 'fourth@example.test');
        $third = $this->member('ST100003', 'ST100004', 'third@example.test');
        $second = $this->member('ST100002', 'ST100003', 'second@example.test');
        $sponsor = $this->member('ST100001', 'ST100002', 'sponsor@example.test');
        $buyer = $this->member('ST100000', 'ST100001', 'buyer@example.test');
        $package = $this->package('DEMO-COAST', 'Coastal Weekend');
        foreach ([$locked, $fourth, $third, $second, $sponsor] as $index => $upline) {
            $this->purchase($upline, $package, '2024-01-01 02:00:00', 'OLD-'.$index);
        }
        $this->purchase($buyer, $package, '2026-10-01 02:00:00', 'BUYER-1');

        return ['buyer' => $buyer, 'sponsor' => $sponsor];
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function member(string $memberId, ?string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId === null ? null : 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $name): Package
    {
        return Package::query()->firstOrCreate(['code' => $code], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $name,
            'price_usd' => $code === 'DEMO-HILLS' ? '1000.0000' : '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $reviewedAt, string $reference): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => $reference,
            'status' => 'approved',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
