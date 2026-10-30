<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\DailyFinancialScheduleRegistrar;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealIncomeReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_demonstration_rows_stay_out_of_the_real_daily_count_and_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $demo = $this->member('ST100004', 'ST666666', 'nadim@local-test.invalid');
        $real = $this->member('ST200001', 'ST666666', 'real@example.com');
        $package = $this->package();
        $this->purchase($demo, $package, '2026-10-17 02:00:00', 'LOCAL-TEST-ST100004-DEMO-HILLS');
        $waiting = $this->purchase($real, $package, '2026-10-29 02:00:00', 'CHAIN-REAL-1');

        $accrual = app(PackageIncomeAccrual::class);
        $this->assertCount(1, $accrual->previewDemonstration('2026-10-29')['roi']);
        $this->assertCount(0, $accrual->previewReal('2026-10-29')['roi']);
        $dry = $accrual->runControlled('2026-10-29', true);
        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, $dry['commission_records']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertFalse(FinancialControl::current()->roi_wallet_crediting_enabled);
        $this->assertFalse(FinancialControl::current()->daily_financial_cycle_enabled);

        $schedule = new Schedule;
        app(DailyFinancialScheduleRegistrar::class)->register($schedule);
        $commands = implode(' ', array_map(fn ($event) => (string) $event->command, $schedule->events()));
        $this->assertStringContainsString('financial:daily', $commands);

        $this->actingAs($admin)->get(route('admin.finance.real-income'))
            ->assertOk()
            ->assertSeeText('ST200001')
            ->assertSeeText('The first income date is 2026-11-08.')
            ->assertDontSeeText('LOCAL-TEST-ST100004-DEMO-HILLS');
        $this->actingAs($admin)->get(route('admin.finance.roi-schedule'))
            ->assertOk()
            ->assertSeeText('Automatic ROI generation is disabled')
            ->assertSeeText('financial:daily')
            ->assertSeeText('Not registered');
        $this->actingAs($admin)->get(route('admin.demo-income'))
            ->assertOk()
            ->assertSeeText('ST100004')
            ->assertSeeText('2.5000');

        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-DEMO-HIDE',
            'idempotency_key' => (string) Str::uuid(),
            'package_purchase_id' => PackagePurchase::query()->where('member_id', 'ST100004')->value('id'),
            'member_id' => 'ST100004',
            'member_name' => 'Nadim',
            'package_code' => 'DEMO-HILLS',
            'package_name' => 'Hill Station Retreat',
            'business_date' => '2026-10-29',
            'amount' => '2.5000',
            'status' => 'accrued',
        ]);
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-REAL-SHOW',
            'idempotency_key' => (string) Str::uuid(),
            'package_purchase_id' => $waiting->id,
            'member_id' => 'ST200001',
            'member_name' => 'Real Member',
            'package_code' => 'DEMO-HILLS',
            'package_name' => 'Hill Station Retreat',
            'business_date' => '2026-10-29',
            'amount' => '2.5000',
            'status' => 'accrued',
        ]);
        $this->actingAs($admin)->get(route('admin.finance.roi-history'))
            ->assertOk()
            ->assertSeeText('PROI-REAL-SHOW')
            ->assertDontSeeText('PROI-DEMO-HIDE');
    }

    public function test_a_real_purchase_inside_the_window_is_counted_without_a_wallet_credit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $buyer = $this->member('ST200002', 'ST666666', 'buyer@example.com');
        $this->purchase($buyer, $this->package(), '2026-10-17 02:00:00', 'CHAIN-REAL-2');

        $real = app(PackageIncomeAccrual::class)->previewReal('2026-10-29');
        $this->assertCount(1, $real['roi']);
        $this->assertSame('2.5000', $real['roi'][0]['amount']);
        $this->assertSame('0.0000', $buyer->fresh()->roi_wallet_amount);

        $schedule = new Schedule;
        app(DailyFinancialScheduleRegistrar::class)->register($schedule);
        $commands = implode(' ', array_map(fn ($event) => (string) $event->command, $schedule->events()));
        $this->assertStringContainsString('financial:daily', $commands);

        $this->actingAs($admin)->get(route('admin.finance.real-income'))
            ->assertOk()
            ->assertSeeText('Eligible for ROI')
            ->assertSeeText('ST200002');
        $this->actingAs($admin)->get(route('admin.finance.roi-schedule'))
            ->assertOk()
            ->assertSeeText('Registered');
        $this->assertFalse(FinancialControl::query()->value('roi_wallet_crediting_enabled'));
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    private function member(string $memberId, string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'email' => $email,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(): Package
    {
        return Package::query()->create([
            'code' => 'DEMO-HILLS',
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
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
