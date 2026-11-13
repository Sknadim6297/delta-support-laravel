<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\TourPackageIncomeActivation;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomeEligibilityAudit;
use App\Services\PackageIncomePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionPackageIncomeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_production_packages_use_authorized_income_schedules(): void
    {
        $policy = app(PackageIncomePolicy::class);
        $roi = $policy->definition('DST-ROI-002');
        $tour = $policy->definition('DST-TOUR-003');

        $this->assertNotNull($roi);
        $this->assertSame('0.2500', $roi['roi_daily']);
        $this->assertSame(800, $roi['roi_days']);
        $this->assertSame('0.2400', $roi['commission_pool']);
        $this->assertSame(400, $roi['commission_days']);

        $this->assertNotNull($tour);
        $this->assertSame('2.5000', $tour['roi_daily']);
        $this->assertSame(1000, $tour['roi_days']);
        $this->assertSame('2.4000', $tour['commission_pool']);
        $this->assertSame(400, $tour['commission_days']);
        $this->assertSame(10, (int) config('deltasupport.package_income.wait_days'));
    }

    public function test_dst_roi_002_and_dst_tour_003_credit_their_own_amounts(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 10:00:00', 'Asia/Kolkata'));
        $this->arm();
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => '2026-11-01',
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

        $roiPackage = $this->package('DST-ROI-002', 'Delta Support Digha Tour + Monthly Income', Package::TYPE_TOUR_ROI, '100.0000', true);
        $tourPackage = $this->package('DST-TOUR-003', 'Delta Support 3-Year Tour Package', Package::TYPE_PREMIUM, '1000.0000', true);
        $roiMember = $this->member('STDST0001', 'roi@example.test');
        $tourMember = $this->member('STDST0002', 'tour@example.test');
        $roiPurchase = $this->purchase($roiMember, $roiPackage, '2026-11-01 06:00:00', 'DST-ROI-A');
        $tourPurchase = $this->purchase($tourMember, $tourPackage, '2026-11-01 06:00:00', 'DST-TOUR-A');

        $report = app(PackageIncomeEligibilityAudit::class)->forDate('2026-11-12');
        $this->assertCount(2, $report['eligible']);
        $amounts = collect($report['eligible'])->pluck('amount', 'purchase_id');
        $this->assertSame('0.2500', $amounts[(string) $roiPurchase->id]);
        $this->assertSame('2.5000', $amounts[(string) $tourPurchase->id]);

        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-12', true);
        $this->assertSame(2, $dry['roi_records']);

        $live = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');
        $repeat = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');
        $this->assertSame(2, $live['roi_records']);
        $this->assertSame(0, $repeat['roi_records']);
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame('0.2500', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $tourMember->fresh()->roi_wallet_amount);
        $this->assertSame(2, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
    }

    public function test_eligibility_and_dry_run_agree_before_the_wait_ends(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 10:00:00', 'Asia/Kolkata'));
        $member = $this->member('STDST0003', 'wait@example.test');
        $package = $this->package('DST-ROI-002', 'Delta Support Digha Tour + Monthly Income', Package::TYPE_TOUR_ROI, '100.0000', true);
        $this->purchase($member, $package, '2026-11-01 06:00:00', 'DST-WAIT');

        $report = app(PackageIncomeEligibilityAudit::class)->forDate('2026-11-05');
        $this->assertCount(0, $report['eligible']);
        $this->assertStringContainsString('10-day wait', collect($report['ineligible'])->pluck('reason')->implode(' '));

        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-05', true);
        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function member(string $memberId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $name, string $type, string $price, bool $roiEligible): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => $name,
            'price_usd' => $price,
            'denomination' => 'USDT',
            'roi_eligible' => $roiEligible,
            'level_commission_eligible' => $roiEligible,
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
