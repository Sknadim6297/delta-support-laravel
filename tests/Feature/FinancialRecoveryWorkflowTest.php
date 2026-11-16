<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyFinancialRecovery;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialRecoveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_recovery_preview_writes_nothing_and_marks_older_dates_ineligible(): void
    {
        $this->arm();
        $member = $this->member('STREC001', 'ST666666', 'preview@example.test');
        $this->purchase($member, $this->package('DEMO-COAST', 'Coastal'), '2026-10-01 02:00:00', 'PREV-1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $this->artisan('financial:recovery-preview', ['--days' => 5])
            ->assertSuccessful()
            ->expectsOutputToContain('Connected database')
            ->expectsOutputToContain('NOT eligible for automatic recovery')
            ->expectsOutputToContain('eligible via financial:recover (yesterday)')
            ->expectsOutputToContain('Stage A only');

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, DB::table('package_income_runs')->count());
    }

    public function test_recovery_apply_requires_confirm_and_does_not_post_on_preview(): void
    {
        $this->arm();
        $db = (string) DB::connection()->getDatabaseName();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $this->artisan('financial:recovery-apply', [
            '--database' => $db,
            '--preview' => true,
        ])->assertSuccessful()->expectsOutputToContain('Preview only');

        $this->artisan('financial:recovery-apply', [
            '--database' => $db,
            '--confirm' => 'wrong-token',
        ])->assertFailed()->expectsOutputToContain('Refused');

        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_recover_propagates_internal_daily_failure_exit_code(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $yesterdayLock = Cache::lock('package-income-2026-11-15', 120);
        $this->assertTrue($yesterdayLock->get());
        try {
            $this->artisan('financial:recover')
                ->assertFailed()
                ->expectsOutputToContain('Yesterday recovery failed');
        } finally {
            $yesterdayLock->release();
        }
    }

    public function test_older_than_yesterday_is_refused_by_daily_without_recover_flag(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The daily cycle processes only the current business date');
        app(PackageIncomeAccrual::class)->runControlled('2026-11-13', false, false);
    }

    public function test_already_succeeded_yesterday_is_not_credited_again_by_recover(): void
    {
        $this->arm();
        $member = $this->member('STREC002', 'ST666666', 'again@example.test');
        $this->purchase($member, $this->package('DEMO-COAST', 'Coastal'), '2026-10-01 02:00:00', 'AGAIN-1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        Artisan::call('financial:daily', [
            '--date' => '2026-11-15',
            '--recover-yesterday' => true,
        ]);
        Artisan::call('financial:daily', ['--date' => '2026-11-16']);
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-15')->count());
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-16')->count());
        $wallet = $member->fresh()->roi_wallet_amount;
        $runCount = DB::table('package_income_runs')->count();

        $this->artisan('financial:recover')
            ->assertSuccessful()
            ->expectsOutputToContain('already succeeded');
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-15')->count());
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-16')->count());
        $this->assertSame($wallet, $member->fresh()->roi_wallet_amount);
        $this->assertSame($runCount, DB::table('package_income_runs')->count());
    }

    public function test_recovery_apply_confirm_runs_recover_only(): void
    {
        $this->arm();
        $member = $this->member('STREC003', 'ST666666', 'apply@example.test');
        $this->purchase($member, $this->package('DEMO-COAST', 'Coastal'), '2026-10-01 02:00:00', 'APPLY-1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:recovery-apply', [
            '--database' => $db,
            '--confirm' => ApplyFinancialRecovery::CONFIRM,
        ])->assertSuccessful();

        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-15')->count());
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-16')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-13')->count());
        $this->assertSame('0.5000', $member->fresh()->roi_wallet_amount);
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
            'sponsor_name' => $sponsorId ? 'Sponsor' : null,
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
        return Package::query()->updateOrCreate(['code' => $code], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $name,
            'price_usd' => '100.0000',
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
