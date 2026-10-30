<?php

namespace Tests\Feature;

use App\Services\FinancialControls;
use App\Models\CommissionPlanLevel;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\MemberTransferSetting;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\CommissionPlan;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ProductionDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_has_configuration_and_no_people_or_money(): void
    {
        $this->seed(ProductionDatabaseSeeder::class);

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, PackagePurchase::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(3, Package::query()->count());
        $this->assertSame('100.0000', Package::query()->where('code', 'DEMO-COAST')->value('price_usd'));
        $this->assertSame('1000.0000', Package::query()->where('code', 'DEMO-HILLS')->value('price_usd'));
        $this->assertSame('USDT', Package::query()->where('code', 'VERIFY-1')->value('denomination'));
        $this->assertSame(20, CommissionPlanLevel::query()->count());
        $this->assertSame(0, CommissionPlanLevel::query()->where('is_active', true)->count());
        $this->assertSame('USDT', PaymentSetting::current()->currency_label);
        $this->assertFalse(PaymentSetting::current()->is_enabled);
        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertFalse(config('deltasupport.wallet_ledger.enabled'));
        $this->assertSame([], app(CommissionPlan::class)->integrityErrors());
        $controls = FinancialControl::current();
        foreach (FinancialControls::FLAGS as $flag) {
            $this->assertFalse((bool) $controls->{$flag}, $flag);
        }
        $this->assertFalse(MemberTransferSetting::current()->transfersEnabled());
        $this->assertSame(32, DB::table('level_commissions')->count());
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
    }

    public function test_demo_seeder_refuses_production_and_provision_creates_one_admin(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new DemoCatalogSeeder)->setContainer($this->app)->run();
    }

    public function test_provision_command_creates_one_admin_and_does_not_print_the_password(): void
    {
        $password = 'Launch-Secret-2026!';
        $this->exposeRootPassword($password);

        try {
            $this->artisan('admin:provision', [
                '--confirm' => 'provision-super-admin',
                '--email' => 'admin@deltasupport.local',
                '--database' => DB::connection()->getDatabaseName(),
            ])->assertSuccessful()
                ->expectsOutputToContain('ST666666')
                ->doesntExpectOutputToContain($password);

            $admin = User::query()->firstOrFail();
            $hash = $admin->password;
            $this->assertTrue($admin->is_admin);
            $this->assertTrue($admin->must_change_password);
            $this->assertNotSame($password, $hash);
            $this->assertTrue(Hash::check($password, $hash));
            $this->assertSame(1, User::query()->where('is_admin', true)->count());
            $this->assertSame(0, Member::query()->count());
            $this->assertFileDoesNotExist(storage_path('app/admin-one-time-password.txt'));

            $this->exposeRootPassword($password);
            $this->artisan('admin:provision', [
                '--confirm' => 'provision-super-admin',
                '--database' => DB::connection()->getDatabaseName(),
            ])->assertFailed();
            $this->assertSame($hash, User::query()->firstOrFail()->password);
            $this->assertSame(1, User::query()->count());

            $this->post(route('login.submit'), [
                'member_id' => 'ST666666',
                'password' => $password,
            ])->assertRedirect(route('admin.change-password.index'));
            $this->assertAuthenticatedAs($admin);

            $this->get(route('dashboard'))
                ->assertRedirect(route('admin.change-password.index'));
            $this->get(route('admin.change-password.index'))
                ->assertOk()
                ->assertSeeText('Change Password');
            $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
        } finally {
            $this->clearRootPassword();
        }
    }

    public function test_provision_refuses_a_missing_password_and_the_development_database(): void
    {
        $this->clearRootPassword();

        $this->artisan('admin:provision', [
            '--confirm' => 'provision-super-admin',
            '--database' => DB::connection()->getDatabaseName(),
        ])->assertFailed();
        $this->assertSame(0, User::query()->count());

        $this->exposeRootPassword('Launch-Secret-2026!');
        try {
            $this->artisan('admin:provision', [
                '--confirm' => 'provision-super-admin',
                '--database' => 'delta_support',
            ])->assertFailed()
                ->expectsOutputToContain('protected');
        } finally {
            $this->clearRootPassword();
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_prepare_refuses_protected_database_names(): void
    {
        $this->artisan('production:prepare', [
            '--confirm' => 'prepare-production-database',
            '--database' => 'delta_support',
        ])->assertFailed()
            ->expectsOutputToContain('protected');

        $this->artisan('production:prepare', [
            '--database' => 'delta_support_production',
        ])->assertFailed()
            ->expectsOutputToContain('Refused');
    }

    public function test_sponsor_st666666_registers_the_first_member_without_a_root_member_row(): void
    {
        Mail::fake();

        $this->post(route('member.register.store'), [
            'member_name' => 'First Member',
            'sponsor_id' => 'ST666666',
            'mobile_no' => '9876500001',
            'email' => 'first-member@example.com',
        ])->assertRedirect(route('member.register'));

        $member = Member::query()->firstOrFail()->refresh();
        $this->assertSame('ST100000', $member->member_id);
        $this->assertSame('ST666666', $member->sponsor_id);
        $this->assertSame('Admin', $member->sponsor_name);
        $this->assertSame('0.0000', (string) $member->activation_wallet_amount);
        $this->assertSame('0.0000', (string) $member->working_wallet_amount);
        $this->assertSame('0.0000', (string) $member->roi_wallet_amount);
        $this->assertSame(0, Member::query()->where('member_id', 'ST666666')->count());
        $this->assertSame(0, PackagePurchase::query()->count());
        $this->assertSame(1, Member::query()->count());
    }

    private function exposeRootPassword(string $password): void
    {
        putenv('ROOT_ADMIN_PASSWORD='.$password);
        $_ENV['ROOT_ADMIN_PASSWORD'] = $password;
        $_SERVER['ROOT_ADMIN_PASSWORD'] = $password;
    }

    private function clearRootPassword(): void
    {
        putenv('ROOT_ADMIN_PASSWORD');
        unset($_ENV['ROOT_ADMIN_PASSWORD'], $_SERVER['ROOT_ADMIN_PASSWORD']);
    }
}
