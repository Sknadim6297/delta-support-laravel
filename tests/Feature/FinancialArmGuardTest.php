<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\User;
use App\Services\FinancialActivationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialArmGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_do_not_arm_posting_or_write_the_initialization_marker(): void
    {
        $this->assertFalse(FinancialControl::current()->financial_automation_armed);
        $this->assertFalse(FinancialControl::current()->financial_emergency_stop);
        $this->assertSame(0, DB::table('production_initializations')->count());
        $this->assertSame('', trim((string) config('deltasupport.financial_approved_database')));
    }

    public function test_an_unauthorized_or_uncertain_database_cannot_be_armed(): void
    {
        $this->artisan('financial:arm', ['--confirm' => 'arm-financial-automation'])
            ->assertFailed()
            ->expectsOutputToContain('was not named');

        $this->artisan('financial:arm', [
            '--confirm' => 'arm-financial-automation',
            '--database' => 'delta_support',
        ])->assertFailed()->expectsOutputToContain('not the approved target');

        config(['deltasupport.financial_approved_database' => 'delta_support']);
        $this->artisan('financial:arm', [
            '--confirm' => 'arm-financial-automation',
            '--database' => 'delta_support',
        ])->assertFailed()->expectsOutputToContain('not the approved target');

        $name = $this->connectedName();
        config(['deltasupport.financial_approved_database' => $name]);
        $this->artisan('financial:arm', [
            '--confirm' => 'arm-financial-automation',
            '--database' => $name,
        ])->assertFailed()->expectsOutputToContain('initialization marker');

        $this->assertFalse(FinancialControl::current()->financial_automation_armed);
    }

    public function test_existing_business_records_block_activation(): void
    {
        $this->prepareApprovedDatabase();
        Member::query()->create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Existing Member',
            'mobile_no' => '9000000001',
            'email' => 'existing@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);

        $this->artisan('financial:arm', $this->armOptions())
            ->assertFailed()
            ->expectsOutputToContain('already has business records');

        $this->assertFalse(FinancialControl::current()->financial_automation_armed);
    }

    public function test_activation_is_audited_once_and_a_second_request_is_refused(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->prepareApprovedDatabase();

        $this->artisan('financial:arm', $this->armOptions())->assertSuccessful();
        $this->artisan('financial:arm', $this->armOptions())
            ->assertFailed()
            ->expectsOutputToContain('already armed');

        $this->assertTrue(FinancialControl::current()->financial_automation_armed);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
        $this->assertFalse(FinancialControl::current()->withdrawal_requests_enabled);
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'financial_automation_armed')->where('user_id', $admin->id)->count());
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'financial_automation_arm_refused')->count());
    }

    public function test_the_emergency_stop_refuses_activation_without_changing_the_stop(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->prepareApprovedDatabase();
        $row = FinancialControl::current();
        $row->financial_emergency_stop = true;
        $row->save();

        $this->artisan('financial:arm', $this->armOptions())
            ->assertFailed()
            ->expectsOutputToContain('emergency stop');

        $fresh = FinancialControl::current()->fresh();
        $this->assertFalse($fresh->financial_automation_armed);
        $this->assertTrue($fresh->financial_emergency_stop);
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'financial_automation_arm_refused')->count());
    }

    private function prepareApprovedDatabase(): void
    {
        $name = $this->connectedName();
        config(['deltasupport.financial_approved_database' => $name]);
        $this->package('DEMO-COAST', 'Coastal Weekend', '100.0000');
        $this->package('DEMO-HILLS', 'Hill Station Retreat', '1000.0000');
        app(FinancialActivationGuard::class)->recordInitialization((string) DB::getDefaultConnection());
    }

    private function connectedName(): string
    {
        $name = DB::connection()->getDatabaseName();
        $this->assertIsString($name);
        $this->assertNotSame('', $name);

        return $name;
    }

    /**
     * @return array<string, string>
     */
    private function armOptions(): array
    {
        return [
            '--confirm' => 'arm-financial-automation',
            '--database' => $this->connectedName(),
        ];
    }

    private function package(string $code, string $name, string $price): void
    {
        Package::query()->create([
            'code' => $code,
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => $name,
            'price_usd' => $price,
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
};
