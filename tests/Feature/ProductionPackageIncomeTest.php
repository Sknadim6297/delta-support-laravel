<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
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

    public function test_production_roi_package_uses_authorized_coastal_tier_amounts(): void
    {
        $policy = app(PackageIncomePolicy::class);
        $definition = $policy->definition('DST-ROI-002');

        $this->assertNotNull($definition);
        $this->assertSame('0.2500', $definition['roi_daily']);
        $this->assertSame(800, $definition['roi_days']);
        $this->assertSame('0.2400', $definition['commission_pool']);
        $this->assertSame(400, $definition['commission_days']);
        $this->assertNull($policy->definition('DST-TOUR-003'));
        $this->assertSame(10, (int) config('deltasupport.package_income.wait_days'));
    }

    public function test_dst_roi_002_is_eligible_and_dst_tour_003_never_receives_daily_roi(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 10:00:00', 'Asia/Kolkata'));
        $this->arm();

        $roiPackage = $this->package('DST-ROI-002', 'Delta Support Digha Tour + Monthly Income', Package::TYPE_TOUR_ROI, '100.0000', true);
        $tourPackage = $this->package('DST-TOUR-003', 'Delta Support 3-Year Tour Package', Package::TYPE_TOUR, '1000.0000', false);
        $roiMember = $this->member('STDST0001', 'roi@example.test');
        $tourMember = $this->member('STDST0002', 'tour@example.test');
        $roiPurchase = $this->purchase($roiMember, $roiPackage, '2026-11-01 06:00:00', 'DST-ROI-A');
        $this->purchase($tourMember, $tourPackage, '2026-10-01 06:00:00', 'DST-TOUR-A');

        $report = app(PackageIncomeEligibilityAudit::class)->forDate('2026-11-12');
        $this->assertCount(1, $report['eligible']);
        $this->assertSame((string) $roiPurchase->id, $report['eligible'][0]['purchase_id']);
        $this->assertSame('0.2500', $report['eligible'][0]['amount']);
        $this->assertSame('2026-11-11', $report['eligible'][0]['first_accrual']);
        $reasons = collect($report['ineligible'])->pluck('reason')->implode(' | ');
        $this->assertStringContainsString('Package roi_eligible is false', $reasons);
        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-003'));

        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-12', true);
        $this->assertSame(1, $dry['roi_records']);
        $this->assertSame(0, $dry['commission_records']);

        $live = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');
        $repeat = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');
        $this->assertSame(1, $live['roi_records']);
        $this->assertSame(0, $repeat['roi_records']);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame('0.2500', PackageRoiAccrual::query()->value('amount'));
        $this->assertSame('DST-ROI-002', PackageRoiAccrual::query()->value('package_code'));
        $this->assertSame('0.2500', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tourMember->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->count());
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
