<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeAdministration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_control_is_admin_only_and_does_not_enable_generation(): void
    {
        $this->get(route('admin.finance.roi-schedule'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.finance.roi-schedule'))
            ->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.finance.roi-schedule'))
            ->assertStatus(405);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.finance.roi-schedule'))
            ->assertOk()
            ->assertSeeText('Income Control')
            ->assertSeeText('Automatic ROI generation is disabled')
            ->assertSeeText('No execution has been recorded.')
            ->assertSeeText('Package income generation is disabled. This page cannot turn it on.')
            ->assertSeeText('Automated test results are not stored in the database.')
            ->assertSeeText('Backup availability')
            ->assertDontSeeText('Enable generation');

        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageIncomeRun::query()->count());
        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_disabled_duplicate_and_failed_runs_are_recorded_without_a_wallet_credit(): void
    {
        Log::spy();
        $buyer = $this->member('STADM001', 'ST666666');
        $this->purchase($buyer, $this->package('DEMO-HILLS', true, true), 'approved', '2026-10-01 06:00:00', '99.0000');

        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(1);
        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(1);

        $this->assertSame(2, PackageIncomeRun::query()->where('status', PackageIncomeRun::REFUSED)->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('99.0000', PackagePurchase::query()->value('price_usd'));
        Log::shouldHaveReceived('warning')->twice();

        config(['deltasupport.package_income.enabled' => true]);
        $lock = Cache::lock('package-income-2026-10-11', 120);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(1);
        } finally {
            $lock->release();
        }
        $this->assertSame(1, PackageIncomeRun::query()->where('status', PackageIncomeRun::BLOCKED)->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && preg_match('/update\s+[`"]?members[`"]?/i', $query->sql)) {
                $armed = false;
                throw new \RuntimeException('forced wallet failure');
            }
        });
        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(1);
        $this->assertSame(1, PackageIncomeRun::query()->where('status', PackageIncomeRun::FAILED)->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $buyer->fresh()->working_wallet_amount);
    }

    public function test_chunked_processing_credits_each_purchase_once_when_enabled_in_the_test_database(): void
    {
        config([
            'deltasupport.package_income.enabled' => true,
            'deltasupport.package_income.chunk_size' => 1,
        ]);
        $sponsor = $this->member('STCHK001', 'ST666666');
        $buyer = $this->member('STCHK002', $sponsor->member_id);
        $hills = $this->package('DEMO-HILLS', true, true);
        $this->purchase($sponsor, $hills, 'approved', '2026-10-01 06:00:00', '1000.0000');
        $this->purchase($buyer, $hills, 'approved', '2026-10-01 06:00:00', '99.0000');

        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(0);
        $this->artisan('package-income:accrue --date=2026-10-11')->assertExitCode(0);

        $runs = PackageIncomeRun::query()->where('status', PackageIncomeRun::SUCCEEDED)->orderBy('id')->get();
        $this->assertCount(2, $runs);
        $this->assertSame(2, $runs[0]->credited_roi);
        $this->assertSame(1, $runs[0]->credited_commissions);
        $this->assertSame(0, $runs[1]->credited_roi);
        $this->assertSame(0, $runs[1]->credited_commissions);
        $this->assertSame(2, PackageRoiAccrual::query()->count());
        $this->assertSame(1, PackageLevelAccrual::query()->count());
        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('99.0000', PackagePurchase::query()->where('member_id', $buyer->member_id)->value('price_usd'));
    }

    public function test_readiness_reports_a_missing_sponsor_and_a_wallet_variance_without_changing_balances(): void
    {
        $member = $this->member('STVAR001', 'STABSENT');
        $member->forceFill(['roi_wallet_amount' => '5.0000'])->save();

        $report = app(PackageIncomeAdministration::class)->report();
        $labels = collect($report['checklist'])->keyBy('label');

        $this->assertSame('blocker', $labels['Sponsor records']['status']);
        $this->assertStringContainsString('STABSENT', $labels['Sponsor records']['detail']);
        $this->assertSame('blocker', $labels['Wallet credit match']['status']);
        $this->assertSame('5.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertNull($report['last_success']);
        $this->assertSame(0, $report['pending_roi']);
    }

    public function test_an_invalid_package_and_an_unpaid_member_add_no_pending_accrual(): void
    {
        $this->member('STNONE001', 'ST666666');
        $this->purchase($this->member('STBAD001', 'ST666666'), $this->package('NOT-A-PLAN', true, true), 'approved', '2026-10-01 06:00:00', '10.0000');

        $report = app(PackageIncomeAdministration::class)->report();

        $this->assertSame(0, $report['pending_roi']);
        $this->assertSame(0, $report['pending_commissions']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, bool $roi, bool $level): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $roi || $level ? Package::TYPE_TOUR_ROI : Package::TYPE_TOUR,
            'name' => $code,
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'roi_eligible' => $roi,
            'level_commission_eligible' => $level,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $reviewedAt, string $price): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.Str::upper(Str::random(4)),
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'denomination' => $package->denomination,
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id,
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === 'approved' ? $reviewedAt : null,
        ]);
    }
}
