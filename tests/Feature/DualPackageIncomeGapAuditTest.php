<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Services\FinancialRecoveryPreview;
use App\Services\InProcessScheduleTick;
use App\Services\PackageIncomeAccrual;
use App\Services\TourPackageIncomeActivation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DualPackageIncomeGapAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        config(['deltasupport.package_income.tour_posting_authorized' => false]);
        parent::tearDown();
    }

    public function test_preview_separates_100_and_1000_expected_amounts_while_tour_auth_blocks_gates(): void
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

        $roiMember = $this->member('STGAP100', 'ST666666', 'roi100@example.test');
        $tourMember = $this->member('STGAP1000', 'ST666666', 'tour1000@example.test');
        $this->purchase($roiMember, $this->roiPackage(), '2026-11-01 06:00:00', 'GAP-100');
        $this->purchase($tourMember, $this->tourPackage(), '2026-11-01 06:00:00', 'GAP-1000');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        // First accrual: 2026-11-11 for both (approval + 10 days). Floor for tour = 2026-11-14.

        $report = app(FinancialRecoveryPreview::class)->build(3, true);
        $this->assertFalse($report['writes']);
        $this->assertFalse($report['tour_posting_authorized']);
        $this->assertNotEmpty($report['generation_blockers']);
        $this->assertTrue(collect($report['generation_blockers'])->contains(
            fn (string $b): bool => str_contains($b, 'Tour posting is not authorized')
        ));

        $day = collect($report['dates'])->firstWhere('business_date', '2026-11-15');
        $this->assertNotNull($day);
        $this->assertSame('1', $day['expected_normal_roi']);
        $this->assertSame('0.2500', $day['expected_normal_roi_amount']);
        $this->assertSame('1', $day['expected_tour_roi']);
        $this->assertSame('2.5000', $day['expected_tour_roi_amount']);
        // Current gates allow only $100 while Tour auth is false.
        $this->assertSame('1', $day['actual_gate_roi']);
        $this->assertSame('0.2500', $day['actual_gate_roi_amount']);

        $tourLine = collect($report['roi_lines'])->first(
            fn (array $line): bool => $line['business_date'] === '2026-11-15' && $line['package_code'] === 'DST-TOUR-003'
        );
        $this->assertNotNull($tourLine);
        $this->assertSame('no', $tourLine['allowed_under_current_gates']);
        $this->assertSame('missing', $tourLine['record_status']);
        $this->assertStringContainsString('not authorized', $tourLine['block_reason']);

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tourMember->fresh()->roi_wallet_amount);
    }

    public function test_both_packages_post_once_when_authorized_and_armed_and_are_idempotent(): void
    {
        $this->arm();
        config(['deltasupport.package_income.tour_posting_authorized' => true]);
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

        $roiMember = $this->member('STOK100', 'ST666666', 'ok100@example.test');
        $tourMember = $this->member('STOK1000', 'ST666666', 'ok1000@example.test');
        $this->purchase($roiMember, $this->roiPackage(), '2026-11-01 06:00:00', 'OK-100');
        $this->purchase($tourMember, $this->tourPackage(), '2026-11-01 06:00:00', 'OK-1000');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 00:30:00', 'Asia/Kolkata'));
        $first = app(PackageIncomeAccrual::class)->runControlled('2026-11-15');
        $second = app(PackageIncomeAccrual::class)->runControlled('2026-11-15');

        $this->assertSame(2, $first['roi_records']);
        $this->assertSame(0, $second['roi_records']);
        $this->assertSame('0.2500', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $tourMember->fresh()->roi_wallet_amount);
        $this->assertSame(2, PackageRoiAccrual::query()->where('status', 'accrued')->count());
    }

    public function test_verify_scheduler_simulation_never_executes_financial_jobs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 00:10:00', 'Asia/Kolkata'));
        $results = app(InProcessScheduleTick::class)->run(InProcessScheduleTick::MODE_VERIFY);
        foreach ($results as $row) {
            $this->assertFalse($row['ran']);
            if ($row['due']) {
                $this->assertSame('verify-only: execution disabled', $row['skipped']);
            }
        }

        $report = app(FinancialRecoveryPreview::class)->build(1, false);
        $this->assertNotEmpty($report['future_schedule_simulation']);
        $this->assertTrue(collect($report['future_schedule_simulation'])->every(
            fn (array $row): bool => $row['would_execute'] === false
        ));
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    public function test_artisan_detailed_preview_reports_package_totals_without_writes(): void
    {
        $this->arm();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 12:00:00', 'Asia/Kolkata'));
        $exit = \Illuminate\Support\Facades\Artisan::call('financial:recovery-preview', [
            '--days' => 2,
            '--detailed' => true,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DST-ROI-002', $output);
        $this->assertStringContainsString('DST-TOUR-003', $output);
        $this->assertStringContainsString('Expected DST-ROI-002', $output);
        $this->assertStringContainsString('Future schedule simulation', $output);
        $this->assertStringContainsString('Stage A only', $output);
        $this->assertSame(0, DB::table('package_income_runs')->count());
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
