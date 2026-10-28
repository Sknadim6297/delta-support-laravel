<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Services\FinancialScheduleRegistrar;
use App\Services\LocalTestMarker;
use App\Services\PackageIncomeAccrual;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeLocalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_local_command_refuses_a_non_local_environment_and_stays_unscheduled(): void
    {
        config(['deltasupport.package_income.enabled' => false]);

        $this->artisan('package-income:local', [
            'action' => 'run',
            '--date' => '2026-11-07',
        ])->assertExitCode(1);

        $this->artisan('package-income:local', [
            'action' => 'run',
            '--date' => '2026-11-07',
            '--confirm' => 'local-delta-support',
        ])->assertExitCode(1);

        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_local_test_accrual_credits_only_marked_purchases_and_a_repeat_credits_nothing(): void
    {
        config(['deltasupport.package_income.enabled' => false]);
        $hills = $this->package('DEMO-HILLS');
        $sponsor = $this->member('STLCL001', 'ST666666', 'rahul@local-test.invalid');
        $buyer = $this->member('STLCL002', $sponsor->member_id, 'ram@local-test.invalid');
        $outsider = $this->member('STREAL01', 'ST666666', 'real@example.test');
        $this->purchase($sponsor, $hills, true);
        $marked = $this->purchase($buyer, $hills, true);
        $unmarked = $this->purchase($outsider, $hills, false);

        try {
            app(PackageIncomeAccrual::class)->accruePurchases('2026-10-11', [$marked->id, $unmarked->id]);
            $this->fail('An unmarked purchase was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not local test data', $exception->getMessage());
        }
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);

        $first = app(PackageIncomeAccrual::class)->accruePurchases('2026-10-11', [$marked->id]);
        $again = app(PackageIncomeAccrual::class)->accruePurchases('2026-10-11', [$marked->id]);

        $this->assertSame(1, $first['credited_roi']);
        $this->assertSame(1, $first['credited_commissions']);
        $this->assertSame(0, $again['credited_roi']);
        $this->assertSame(0, $again['credited_commissions']);
        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $outsider->fresh()->roi_wallet_amount);
        $this->assertFalse(config('deltasupport.package_income.enabled'));
    }

    public function test_a_real_sponsor_blocks_the_local_test_credit(): void
    {
        config(['deltasupport.package_income.enabled' => false]);
        $hills = $this->package('DEMO-HILLS');
        $sponsor = $this->member('STREAL02', 'ST666666', 'sponsor@example.test');
        $buyer = $this->member('STLCL003', $sponsor->member_id, 'sam@local-test.invalid');
        $this->purchase($sponsor, $hills, true);
        $marked = $this->purchase($buyer, $hills, true);

        try {
            app(PackageIncomeAccrual::class)->accruePurchases('2026-10-11', [$marked->id]);
            $this->fail('A real sponsor was paid.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not a local test member', $exception->getMessage());
        }

        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame(0, \App\Models\PackageRoiAccrual::query()->count());
    }

    private function member(string $memberId, string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Hill Station Retreat',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, bool $local): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => $local
                ? LocalTestMarker::PAYMENT_PREFIX.$member->member_id.'-DEMO-HILLS'
                : 'TX'.$member->member_id,
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => '2026-10-01 06:00:00',
            'reviewed_at' => '2026-10-01 06:00:00',
        ]);
    }
}
