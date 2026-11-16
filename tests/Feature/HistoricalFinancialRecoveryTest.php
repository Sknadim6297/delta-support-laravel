<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\FinancialHistoricalRecoveryRun;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\HistoricalFinancialRecovery;
use App\Services\PackageIncomeAccrual;
use App\Services\TourPackageIncomeActivation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoricalFinancialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        parent::tearDown();
    }

    public function test_preview_defaults_and_excludes_tour_while_reporting_standard_totals(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => '2026-11-14',
            'activated_at' => now(),
            'activated_by' => 'test',
            'confirm_token' => TourPackageIncomeActivation::CONFIRM,
            'terms_snapshot' => [
                'roi_daily' => '2.5000',
                'roi_days' => 1000,
                'commission_pool' => '2.4000',
                'commission_days' => 400,
            ],
        ]);

        $standard = $this->member('STHIST100', 'ST666666', 'std@example.test');
        $tour = $this->member('STHIST1000', 'ST666666', 'tour@example.test');
        $this->purchase($standard, $this->roiPackage(), '2026-11-01 06:00:00', 'H-100');
        $this->purchase($tour, $this->tourPackage(), '2026-11-01 06:00:00', 'H-1000');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $db = (string) DB::connection()->getDatabaseName();
        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-13',
            '--to' => '2026-11-15',
            '--packages' => 'DST-ROI-002',
        ])->assertSuccessful()->expectsOutputToContain('Preview only');

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $standard->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tour->fresh()->roi_wallet_amount);
        $this->assertSame(1, FinancialHistoricalRecoveryRun::query()->where('mode', 'preview')->count());
    }

    public function test_apply_requires_confirm_backup_and_rules_and_refuses_tour_package(): void
    {
        $this->arm();
        $db = (string) DB::connection()->getDatabaseName();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-13',
            '--to' => '2026-11-15',
            '--packages' => 'DST-TOUR-003',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
            '--backup-confirmed' => HistoricalFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => HistoricalFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed()->expectsOutputToContain('Tour');

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-13',
            '--to' => '2026-11-15',
            '--packages' => 'DST-ROI-002',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
        ])->assertFailed()->expectsOutputToContain('database-backup-confirmed');
    }

    public function test_emergency_stop_blocks_apply_but_allows_preview(): void
    {
        $this->arm();
        $row = FinancialControl::current();
        $row->financial_emergency_stop = true;
        $row->save();

        $member = $this->member('STHISTSTOP', 'ST666666', 'stop@example.test');
        $this->purchase($member, $this->roiPackage(), '2026-11-01 06:00:00', 'STOP-H');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $preview = app(HistoricalFinancialRecovery::class)->preview('2026-11-15', '2026-11-15', ['DST-ROI-002']);
        $this->assertTrue($preview['dry_run']);
        $this->assertGreaterThanOrEqual(0, $preview['totals']['roi_records']);

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-15',
            '--to' => '2026-11-15',
            '--packages' => 'DST-ROI-002',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
            '--backup-confirmed' => HistoricalFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => HistoricalFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->assertSame(0, PackageRoiAccrual::query()->where('status', 'accrued')->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_apply_credits_standard_roi_once_with_ledger_and_skips_duplicates(): void
    {
        $this->arm();
        $member = $this->member('STHISTOK', 'ST666666', 'ok@example.test');
        $this->purchase($member, $this->roiPackage(), '2026-11-01 06:00:00', 'OK-H');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-13',
            '--to' => '2026-11-15',
            '--packages' => 'DST-ROI-002',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
            '--backup-confirmed' => HistoricalFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => HistoricalFinancialRecovery::RULES_CONFIRM,
        ])->assertSuccessful();

        $this->assertSame(3, PackageRoiAccrual::query()->where('package_code', 'DST-ROI-002')->where('status', 'accrued')->count());
        $this->assertSame(3, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame('0.7500', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->count());

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-13',
            '--to' => '2026-11-15',
            '--packages' => 'DST-ROI-002',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
            '--backup-confirmed' => HistoricalFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => HistoricalFinancialRecovery::RULES_CONFIRM,
        ])->assertSuccessful();

        $this->assertSame(3, PackageRoiAccrual::query()->where('status', 'accrued')->count());
        $this->assertSame(3, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame('0.7500', $member->fresh()->roi_wallet_amount);
    }

    public function test_future_dates_and_daily_path_remain_restricted(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $this->expectException(\RuntimeException::class);
        app(PackageIncomeAccrual::class)->runControlled('2026-11-13', false, false);
    }

    public function test_concurrent_lock_blocks_historical_day(): void
    {
        $this->arm();
        $member = $this->member('STHISTLOCK', 'ST666666', 'lock@example.test');
        $this->purchase($member, $this->roiPackage(), '2026-11-01 06:00:00', 'LOCK-H');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $lock = Cache::lock('package-income-2026-11-15', 120);
        $this->assertTrue($lock->get());
        try {
            $day = app(HistoricalFinancialRecovery::class)->apply('2026-11-15', '2026-11-15', ['DST-ROI-002']);
            $this->assertSame('failed', $day['days'][0]['status']);
            $this->assertStringContainsString('already running', (string) $day['days'][0]['error']);
        } finally {
            $lock->release();
        }
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_calculated_only_accrual_can_be_posted_on_authorized_retry(): void
    {
        $this->arm();
        $member = $this->member('STHISTCALC', 'ST666666', 'calc@example.test');
        $purchase = $this->purchase($member, $this->roiPackage(), '2026-11-01 06:00:00', 'CALC-H');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-'.$purchase->id.'-20261115',
            'idempotency_key' => $purchase->id.'|2026-11-15',
            'package_purchase_id' => $purchase->id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'package_code' => 'DST-ROI-002',
            'package_name' => 'Coastal',
            'business_date' => '2026-11-15',
            'amount' => '0.2500',
            'status' => 'calculated',
        ]);

        $result = app(PackageIncomeAccrual::class)->runStandardHistorical('2026-11-15', false, ['DST-ROI-002']);
        $this->assertSame(1, $result['roi_records']);
        $this->assertSame('accrued', PackageRoiAccrual::query()->value('status'));
        $this->assertSame('0.2500', $member->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->count());
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

    private function roiPackage(): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-ROI-002'], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    private function tourPackage(): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-TOUR-003'], [
            'package_type' => Package::TYPE_PREMIUM,
            'name' => 'Delta Support 3-Year Tour Package',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 10,
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
