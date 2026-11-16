<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\FinancialTourRecoveryRun;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\HistoricalFinancialRecovery;
use App\Services\PackageIncomeAccrual;
use App\Services\TourFinancialRecovery;
use App\Services\TourPackageIncomeActivation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourFinancialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        parent::tearDown();
    }

    public function test_preview_lists_nov_16_liability_for_st100005_and_st100017(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');

        $a = $this->member('ST100005', 'ST666666', 'st100005@example.test');
        $b = $this->member('ST100017', 'ST666666', 'st100017@example.test');
        $this->purchase($a, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-FDODSV');
        $this->purchase($b, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-VTXODU');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 18:00:00', 'Asia/Kolkata'));

        $db = (string) DB::connection()->getDatabaseName();
        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--dates' => '2026-11-14,2026-11-15,2026-11-16',
        ])->assertSuccessful();

        $report = app(TourFinancialRecovery::class)->preview(['2026-11-14', '2026-11-15', '2026-11-16']);
        $this->assertTrue($report['dry_run']);
        $this->assertFalse($report['writes']);
        $this->assertFalse($report['standard_packages_touched']);
        $this->assertSame('15.0000', $report['totals']['roi_amount']); // 3 days × 5.0000
        $this->assertSame('14.4000', $report['totals']['commission_pool_amount']); // 3 × 4.8000
        $this->assertSame('0.0000', $report['totals']['commission_amount']); // admin-root-only chain
        $this->assertSame('15.0000', $report['totals']['financial_liability']);

        $nov16 = collect($report['days'])->firstWhere('business_date', '2026-11-16');
        $this->assertNotNull($nov16);
        $this->assertCount(2, $nov16['purchases']);
        $this->assertSame('5.0000', $nov16['roi_amount']);
        $this->assertSame('4.8000', $nov16['commission_pool_amount']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $a->fresh()->roi_wallet_amount);
    }

    public function test_refuses_dates_before_activation_floor(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $db = (string) DB::connection()->getDatabaseName();
        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--dates' => '2026-11-13',
        ])->assertFailed();

        $this->expectException(\RuntimeException::class);
        app(TourFinancialRecovery::class)->preview(['2026-11-13']);
    }

    public function test_apply_requires_confirm_tokens_and_single_date(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');
        $member = $this->member('STTOURREQ', 'ST666666', 'tourreq@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'REQ-1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
        ])->assertFailed();

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--dates' => '2026-11-15,2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_unauthorized_tour_blocks_apply_and_standard_historical_still_refuses_tour(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        $this->activateFloor('2026-11-14');
        $member = $this->member('STTOURAUTH', 'ST666666', 'tourauth@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'AUTH-1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->artisan('financial:recover-historical', [
            '--database' => $db,
            '--from' => '2026-11-16',
            '--to' => '2026-11-16',
            '--packages' => 'DST-TOUR-003',
            '--confirm' => HistoricalFinancialRecovery::CONFIRM,
            '--backup-confirmed' => HistoricalFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => HistoricalFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_apply_credits_tour_roi_once_and_is_idempotent(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');

        $a = $this->member('ST100005', 'ST666666', 'a@example.test');
        $b = $this->member('ST100017', 'ST666666', 'b@example.test');
        $standard = $this->member('STSTAND', 'ST666666', 'std@example.test');
        $this->purchase($a, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-FDODSV');
        $this->purchase($b, $this->tourPackage(), '2026-11-02 06:00:00', '20261102-VTXODU');
        $this->purchase($standard, $this->roiPackage(), '2026-11-01 06:00:00', 'STD-OK');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertSuccessful();

        $this->assertSame(2, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->where('status', 'accrued')->count());
        $this->assertSame(2, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame('2.5000', $a->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $b->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $standard->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_code', 'DST-ROI-002')->count());

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertSuccessful();

        $this->assertSame(2, PackageRoiAccrual::query()->where('status', 'accrued')->count());
        $this->assertSame(2, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame('2.5000', $a->fresh()->roi_wallet_amount);
        $this->assertSame(2, FinancialTourRecoveryRun::query()->where('mode', 'apply')->count());
        $this->assertSame(2, FinancialTourRecoveryRun::query()->where('mode', 'apply')->where('status', 'succeeded')->count());
    }

    public function test_partial_day_then_retry_posts_remaining_without_duplicates(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');

        $source = $this->member('ST100005', 'ST666666', 'source@example.test');
        $sponsor = $this->member('STSPONSOR1', 'ST666666', 'sponsor@example.test');
        $source->sponsor_id = $sponsor->member_id;
        $source->sponsor_name = $sponsor->member_name;
        $source->save();

        // Sponsor qualifies for level income with a Standard package.
        $this->purchase($sponsor, $this->roiPackage(), '2026-10-01 06:00:00', 'SP-QUAL');
        $purchase = $this->purchase($source, $this->tourPackage(), '2026-11-02 06:00:00', 'SRC-TOUR');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        // Pre-create calculated ROI only; commission still missing.
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-'.$purchase->id.'-20261116',
            'idempotency_key' => $purchase->id.'|2026-11-16',
            'package_purchase_id' => $purchase->id,
            'member_id' => $source->member_id,
            'member_name' => $source->member_name,
            'package_code' => 'DST-TOUR-003',
            'package_name' => 'Tour',
            'business_date' => '2026-11-16',
            'amount' => '2.5000',
            'status' => 'calculated',
        ]);

        $result = app(PackageIncomeAccrual::class)->runTourRecovery('2026-11-16', false);
        $this->assertSame(1, $result['roi_records']);
        $this->assertGreaterThanOrEqual(1, $result['commission_records']);
        $this->assertSame('2.5000', $source->fresh()->roi_wallet_amount);
        $this->assertSame('accrued', PackageRoiAccrual::query()->value('status'));
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertGreaterThanOrEqual(1, PackageLevelAccrual::query()->where('status', 'accrued')->count());

        $second = app(PackageIncomeAccrual::class)->runTourRecovery('2026-11-16', false);
        $this->assertSame(0, $second['roi_records']);
        $this->assertSame(0, $second['commission_records']);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->where('member_id', $source->member_id)->count());
    }

    public function test_emergency_stop_blocks_apply_but_allows_preview(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');
        $row = FinancialControl::current();
        $row->financial_emergency_stop = true;
        $row->save();

        $member = $this->member('STTOURSTOP', 'ST666666', 'tourstop@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'STOP-T');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $preview = app(TourFinancialRecovery::class)->preview(['2026-11-16']);
        $this->assertTrue($preview['dry_run']);
        $this->assertSame(1, $preview['totals']['roi_records']);
        $this->assertSame('2.5000', $preview['totals']['roi_amount']);

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_concurrent_lock_blocks_tour_recovery_day(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');
        $member = $this->member('STTOURLOCK', 'ST666666', 'tourlock@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'LOCK-T');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $lock = Cache::lock('package-income-2026-11-16', 120);
        $this->assertTrue($lock->get());
        try {
            $day = app(TourFinancialRecovery::class)->apply('2026-11-16');
            $this->assertSame('failed', $day['days'][0]['status']);
            $this->assertStringContainsString('already running', (string) $day['days'][0]['error']);
        } finally {
            $lock->release();
        }
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_unarmed_automation_blocks_apply_while_preview_still_reports_liability(): void
    {
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');
        $row = FinancialControl::current();
        $row->financial_automation_armed = false;
        $row->financial_emergency_stop = false;
        $row->save();

        $member = $this->member('STTOURUNARM', 'ST666666', 'unarm@example.test');
        $this->purchase($member, $this->tourPackage(), '2026-11-02 06:00:00', 'UNARM-T');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $db = (string) DB::connection()->getDatabaseName();

        $preview = app(TourFinancialRecovery::class)->preview(['2026-11-16']);
        $this->assertTrue($preview['dry_run']);
        $this->assertSame('2.5000', $preview['totals']['roi_amount']);
        $this->assertFalse($preview['automation_armed']);

        $this->artisan('financial:recover-tour', [
            '--database' => $db,
            '--date' => '2026-11-16',
            '--confirm' => TourFinancialRecovery::CONFIRM,
            '--backup-confirmed' => TourFinancialRecovery::BACKUP_CONFIRM,
            '--rules-confirmed' => TourFinancialRecovery::RULES_CONFIRM,
        ])->assertFailed();

        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->where('status', 'accrued')->count());
    }

    public function test_eligible_upline_receives_commission_from_tour_pool(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
        $this->activateFloor('2026-11-14');

        $source = $this->member('ST100017', 'ST666666', 'src2@example.test');
        $sponsor = $this->member('STSPONSOR2', 'ST666666', 'sp2@example.test');
        $source->sponsor_id = $sponsor->member_id;
        $source->sponsor_name = $sponsor->member_name;
        $source->save();
        $this->purchase($sponsor, $this->roiPackage(), '2026-10-01 06:00:00', 'SP2-QUAL');
        $this->purchase($source, $this->tourPackage(), '2026-11-02 06:00:00', 'SRC2-TOUR');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));

        $preview = app(TourFinancialRecovery::class)->preview(['2026-11-16']);
        $this->assertSame('2.5000', $preview['totals']['roi_amount']);
        $this->assertSame('2.4000', $preview['totals']['commission_pool_amount']);
        $this->assertTrue(bccomp($preview['totals']['commission_amount'], '0', 4) === 1);

        $result = app(TourFinancialRecovery::class)->apply('2026-11-16');
        $this->assertSame('succeeded', $result['status']);
        $this->assertSame('2.5000', $source->fresh()->roi_wallet_amount);
        $this->assertTrue(bccomp((string) $sponsor->fresh()->working_wallet_amount, '0', 4) === 1);
        $this->assertSame(
            $preview['totals']['commission_amount'],
            $sponsor->fresh()->working_wallet_amount
        );
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function activateFloor(string $date): void
    {
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => $date,
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
