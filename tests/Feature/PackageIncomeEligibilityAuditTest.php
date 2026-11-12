<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeEligibilityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_eligibility_explains_waiting_and_ready_purchases_without_crediting(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
        $package = $this->package('DEMO-COAST', true);
        $readyMember = $this->member('STELIG001', 'ready@example.test');
        $waitingMember = $this->member('STELIG002', 'wait@example.test');
        $demoMember = $this->member('STELIG003', 'demo@local-test.invalid');
        $unknown = $this->package('VERIFY-1', true);
        $ready = $this->purchase($readyMember, $package, '2026-10-17 02:00:00', 'READY-1');
        $this->purchase($waitingMember, $package, '2026-10-29 02:00:00', 'WAIT-1');
        $this->purchase($demoMember, $package, '2026-10-17 02:00:00', 'LOCAL-TEST-DEMO');
        $this->purchase($readyMember, $unknown, '2026-10-17 02:00:00', 'UNKNOWN-1');

        $report = app(\App\Services\PackageIncomeEligibilityAudit::class)->forDate('2026-10-29');
        $this->assertCount(1, $report['eligible']);
        $this->assertSame((string) $ready->id, $report['eligible'][0]['purchase_id']);
        $this->assertSame('0.2500', $report['eligible'][0]['amount']);
        $reasons = collect($report['ineligible'])->pluck('reason')->implode(' | ');
        $this->assertStringContainsString('Still inside the 10-day wait', $reasons);
        $this->assertStringContainsString('local-test / demonstration', $reasons);
        $this->assertStringContainsString('no income definition', $reasons);

        $this->artisan('financial:eligibility', ['--date' => '2026-10-29'])
            ->assertSuccessful()
            ->expectsOutputToContain('Eligible for this date')
            ->expectsOutputToContain((string) $ready->id);

        $this->assertSame('0.0000', $readyMember->fresh()->roi_wallet_amount);
    }

    public function test_mark_initialized_refuses_unapproved_or_mismatched_databases_and_never_arms(): void
    {
        $name = (string) \Illuminate\Support\Facades\DB::connection()->getDatabaseName();

        $this->artisan('financial:mark-initialized', [
            '--confirm' => 'mark-financial-initialization',
            '--database' => $name,
        ])->assertFailed()->expectsOutputToContain('FINANCIAL_APPROVED_DATABASE');

        config(['deltasupport.financial_approved_database' => $name]);
        $this->artisan('financial:mark-initialized', [
            '--confirm' => 'mark-financial-initialization',
            '--database' => 'Delta_support_db',
        ])->assertFailed()->expectsOutputToContain('must exactly match');

        config(['deltasupport.financial_approved_database' => 'delta_support']);
        $this->artisan('financial:mark-initialized', [
            '--confirm' => 'mark-financial-initialization',
            '--database' => 'delta_support',
        ])->assertFailed()->expectsOutputToContain('must exactly match');

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('production_initializations')->count());
        $this->assertFalse(\App\Models\FinancialControl::current()->financial_automation_armed);
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

    private function package(string $code, bool $roiEligible): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $code,
            'price_usd' => $code === 'DEMO-HILLS' ? '1000.0000' : '100.0000',
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
