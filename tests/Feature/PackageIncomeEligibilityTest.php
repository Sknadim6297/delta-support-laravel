<?php

namespace Tests\Feature;

use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_packages_match_roi_and_level_income_eligibility_rules(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $verification = Package::query()->where('code', 'VERIFY-1')->firstOrFail();
        $coastal = Package::query()->where('code', 'DEMO-COAST')->firstOrFail();
        $hills = Package::query()->where('code', 'DEMO-HILLS')->firstOrFail();

        $this->assertSame('Verification Package', $verification->name);
        $this->assertSame(Package::TYPE_TOUR, $verification->package_type);
        $this->assertFalse($verification->roi_eligible);
        $this->assertFalse($verification->level_commission_eligible);
        $this->assertFalse($verification->reservesFutureIncome());

        $this->assertSame('Coastal Weekend', $coastal->name);
        $this->assertSame(Package::TYPE_TOUR_ROI, $coastal->package_type);
        $this->assertTrue($coastal->roi_eligible);
        $this->assertTrue($coastal->level_commission_eligible);
        $this->assertTrue($coastal->reservesFutureIncome());

        $this->assertSame('Hill Station Retreat', $hills->name);
        $this->assertSame(Package::TYPE_TOUR_ROI, $hills->package_type);
        $this->assertTrue($hills->roi_eligible);
        $this->assertTrue($hills->level_commission_eligible);
        $this->assertTrue($hills->reservesFutureIncome());

        $admin = User::query()->where('is_admin', true)->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.finance.roi-plans'))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Verification Package',
                'Not eligible',
                'Not eligible',
                'Coastal Weekend',
                'Eligible',
                'Eligible',
                'Hill Station Retreat',
                'Eligible',
                'Eligible',
            ]);

        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_seeder_corrects_coastal_weekend_flags_without_rewriting_purchases(): void
    {
        $coastal = Package::query()->create([
            'code' => 'DEMO-COAST',
            'package_type' => Package::TYPE_TOUR,
            'name' => 'Coastal Weekend',
            'price_usd' => '49.0000',
            'denomination' => 'USDT',
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 20,
        ]);
        $member = Member::query()->create([
            'member_id' => 'ST910199',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Snapshot Member',
            'mobile_no' => '9101990001',
            'email' => 'snapshot-coastal@example.test',
            'status' => 'active',
        ]);
        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-COAST-SNAP',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $coastal->id,
            'package_code' => $coastal->code,
            'package_name' => $coastal->name,
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '49.0000',
            'denomination' => 'USDT',
            'currency_label' => 'USDT',
            'network' => 'BEP20 Demo Network',
            'payment_reference' => 'HISTORICALCOAST49',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now(),
        ]);

        $this->seed(DemoCatalogSeeder::class);

        $coastal->refresh();
        $purchase->refresh();

        $this->assertSame(Package::TYPE_TOUR_ROI, $coastal->package_type);
        $this->assertTrue($coastal->roi_eligible);
        $this->assertTrue($coastal->level_commission_eligible);
        $this->assertSame(Package::TYPE_TOUR, $purchase->package_type);
        $this->assertSame('49.0000', $purchase->price_usd);
        $this->assertSame('PUR-COAST-SNAP', $purchase->reference);
        $this->assertSame(PackagePurchase::APPROVED, $purchase->status);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_admin_package_type_maps_eligibility_flags_without_crediting_income(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'VERIFY-1',
            'name' => 'Verification Package',
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '100.0000',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DEMO-COAST',
            'name' => 'Coastal Weekend',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '100.0000',
            'sort_order' => 20,
            'is_active' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DEMO-HILLS',
            'name' => 'Hill Station Retreat',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '1000.0000',
            'sort_order' => 21,
            'is_active' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $verification = Package::query()->where('code', 'VERIFY-1')->firstOrFail();
        $coastal = Package::query()->where('code', 'DEMO-COAST')->firstOrFail();
        $hills = Package::query()->where('code', 'DEMO-HILLS')->firstOrFail();

        $this->assertFalse($verification->roi_eligible);
        $this->assertFalse($verification->level_commission_eligible);
        $this->assertTrue($coastal->roi_eligible);
        $this->assertTrue($coastal->level_commission_eligible);
        $this->assertTrue($hills->roi_eligible);
        $this->assertTrue($hills->level_commission_eligible);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }
}
