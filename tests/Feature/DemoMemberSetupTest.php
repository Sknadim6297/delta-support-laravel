<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Services\DemoMemberSetup;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoMemberSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
        $this->package('DEMO-HILLS', '1000.0000');
        $this->package('DEMO-COAST', '100.0000');
        User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_confirmation_is_required_and_a_second_run_inserts_nothing(): void
    {
        $real = $this->realMember();

        $this->artisan('demo:members:setup')->assertSuccessful();
        $this->assertSame(0, Member::query()->where('email', 'like', '%@local-test.invalid')->count());

        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertSuccessful();
        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertSuccessful();

        $this->assertSame(20, Member::query()->whereIn('member_id', array_values(DemoMemberSetup::IDS))->count());
        $this->assertSame(6, PackagePurchase::query()->where('payment_reference', 'like', 'LOCAL-TEST-%')->count());
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('ST666666', Member::query()->where('member_id', 'ST100004')->value('sponsor_id'));
        $this->assertSame('ST100004', Member::query()->where('member_id', 'ST100005')->value('sponsor_id'));
        $this->assertSame('ST100013', Member::query()->where('member_id', 'ST100023')->value('sponsor_id'));
        $this->assertSame('0.0000', Member::query()->where('member_id', 'ST100004')->value('roi_wallet_amount'));
        $real->refresh();
        $this->assertSame('ST666666', $real->sponsor_id);
        $this->assertSame('12.5000', $real->roi_wallet_amount);
        $this->assertFalse(FinancialControl::current()->roi_wallet_crediting_enabled);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
    }

    public function test_member_email_phone_and_sponsor_conflicts_abort_without_writes(): void
    {
        Member::query()->create([
            'member_id' => 'ST100004',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Existing Person',
            'email' => 'existing@example.com',
            'mobile_no' => '9199990002',
            'status' => 'active',
        ]);

        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertExitCode(1);
        $this->assertSame(1, Member::query()->count());
        $this->assertSame('existing@example.com', Member::query()->where('member_id', 'ST100004')->value('email'));

        Member::query()->where('member_id', 'ST100004')->delete();
        Member::query()->create([
            'member_id' => 'ST200001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Phone Owner',
            'email' => 'phone@example.com',
            'mobile_no' => '9188000101',
            'status' => 'active',
        ]);
        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertExitCode(1);
        $this->assertSame(1, Member::query()->count());

        Member::query()->where('member_id', 'ST200001')->update([
            'mobile_no' => '9199990003',
            'sponsor_id' => 'ST100008',
        ]);
        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertExitCode(1);
        $this->assertSame('ST100008', Member::query()->where('member_id', 'ST200001')->value('sponsor_id'));
        $this->assertNull(Member::query()->where('member_id', 'ST100004')->value('id'));
    }

    public function test_demo_income_preview_is_admin_only_and_does_not_enter_real_reports(): void
    {
        $this->artisan('demo:members:setup', ['--confirm' => DemoMemberSetup::CONFIRM])->assertSuccessful();
        $admin = User::query()->where('is_admin', true)->firstOrFail();

        $this->get(route('admin.demo-income'))->assertRedirect(route('login'));

        $preview = $this->actingAs($admin)->get(route('admin.demo-income'));
        $preview->assertOk()
            ->assertSeeText('ST100004')
            ->assertSeeText('2.5000')
            ->assertSeeText('0.6000')
            ->assertSeeText('does not credit a wallet');
        $this->assertSame(0, PackageRoiAccrual::query()->count());

        $purchase = PackagePurchase::query()->where('member_id', 'ST100004')->firstOrFail();
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-DEMO-1',
            'idempotency_key' => (string) Str::uuid(),
            'package_purchase_id' => $purchase->id,
            'member_id' => 'ST100004',
            'member_name' => 'Nadim',
            'package_code' => 'DEMO-HILLS',
            'package_name' => 'Hill Station Retreat',
            'business_date' => '2026-10-29',
            'amount' => '2.5000',
            'status' => 'accrued',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Demonstration members')
            ->assertSeeText('ST100023')
            ->assertSeeText('USDT 0.0000')
            ->assertDontSeeText('1000.0000')
            ->assertDontSeeText('LOCAL-TEST-ST100004-DEMO-HILLS');
        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))
            ->assertOk()
            ->assertDontSeeText('PROI-DEMO-1');
        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'payments']))
            ->assertOk()
            ->assertDontSeeText('LOCAL-TEST-ST100004-DEMO-HILLS');

        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-10-29', true);
        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, $dry['commission_records']);

        config(['deltasupport.controls.withdrawal_requests_enabled' => true]);
        $demo = Member::query()->where('member_id', 'ST100004')->firstOrFail();
        $this->expectExceptionMessage('Demo members cannot request a withdrawal.');
        app(\App\Services\WithdrawalWorkflow::class)->submit($demo, [
            'wallet' => 'roi',
            'amount' => '1.0000',
            'network' => 'BEP20',
            'address' => '0xabc',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function realMember(): Member
    {
        return Member::query()->create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Real Member',
            'email' => 'real@example.com',
            'mobile_no' => '9199990001',
            'status' => 'active',
            'roi_wallet_amount' => '12.5000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $price): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $code === 'DEMO-HILLS' ? 'Hill Station Retreat' : 'Coastal Weekend',
            'price_usd' => $price,
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
