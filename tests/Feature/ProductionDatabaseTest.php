<?php

namespace Tests\Feature;

use App\Models\CommissionPlanLevel;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Rules\StrongPassword;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_demo_seeder_refuses_production_and_provision_creates_one_admin(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new DemoCatalogSeeder)->setContainer($this->app)->run();
    }

    public function test_provision_command_creates_one_admin_and_does_not_print_the_password(): void
    {
        $this->artisan('admin:provision', [
            '--confirm' => 'provision-super-admin',
            '--email' => 'admin@deltasupport.local',
        ])->assertSuccessful()
            ->expectsOutputToContain('storage/app/admin-one-time-password.txt')
            ->doesntExpectOutputToContain('admin@1234');

        $admin = User::query()->firstOrFail();
        $password = (string) file_get_contents(storage_path('app/admin-one-time-password.txt'));
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(StrongPassword::accepts($password));
        $this->assertSame(1, User::query()->count());
        @unlink(storage_path('app/admin-one-time-password.txt'));

        $this->artisan('admin:provision', ['--confirm' => 'provision-super-admin'])
            ->assertFailed();
    }
}
