<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\RoiTransaction;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_seeds_members_qr_and_purchases_without_income(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);

        $active = Member::query()->where('member_id', 'ST910101')->firstOrFail();
        $pending = Member::query()->where('member_id', 'ST910102')->firstOrFail();
        $visitor = Member::query()->where('member_id', 'ST910103')->firstOrFail();

        $this->assertSame('active', $active->status);
        $this->assertSame('inactive', $pending->status);
        $this->assertSame('inactive', $visitor->status);
        $this->assertTrue(Hash::check(DemoCatalogSeeder::PASSWORD, $visitor->password));
        $this->assertSame('0.0000', $active->roi_wallet_amount);
        $this->assertSame('0.0000', $active->working_wallet_amount);
        $this->assertSame('0.0000', $active->activation_wallet_amount);

        $this->assertTrue(PaymentSetting::current()->isReady());
        Storage::disk('local')->assertExists(PaymentSetting::current()->qr_image_path);

        $verification = Package::query()->where('code', 'VERIFY-1')->firstOrFail();
        $coastal = Package::query()->where('code', 'DEMO-COAST')->firstOrFail();
        $hills = Package::query()->where('code', 'DEMO-HILLS')->firstOrFail();
        $this->assertFalse($verification->roi_eligible);
        $this->assertFalse($verification->level_commission_eligible);
        $this->assertTrue($coastal->roi_eligible);
        $this->assertTrue($coastal->level_commission_eligible);
        $this->assertTrue($hills->roi_eligible);
        $this->assertTrue($hills->level_commission_eligible);

        $this->assertSame(1, PackagePurchase::query()->where('status', PackagePurchase::APPROVED)->count());
        $this->assertSame(1, PackagePurchase::query()->where('status', PackagePurchase::PENDING)->count());
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(Package::TYPE_TOUR_ROI, PackagePurchase::query()->where('package_code', 'DEMO-COAST')->value('package_type'));
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }
}
