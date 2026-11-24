<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackagePurchaseService;
use App\Support\PackageValidityDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageValidityDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_limited_and_unlimited_validity_separate_from_roi_days(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $before = WalletLedgerEntry::query()->count();

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-VAL-001',
            'name' => 'Validity Package',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 1,
            'is_active' => '1',
            'tour_nights' => '1',
            'tour_days' => '2',
            'roi_daily' => '0.1100',
            'roi_days' => '900',
            'validity_duration' => '12',
            'validity_unit' => 'months',
        ])->assertRedirect();

        $package = Package::query()->where('code', 'DST-VAL-001')->firstOrFail();
        $this->assertFalse((bool) $package->validity_unlimited);
        $this->assertSame(12, (int) $package->validity_duration);
        $this->assertSame('months', $package->validity_unit);
        $this->assertSame(900, (int) $package->activePlanVersion->roi_days);
        $this->assertSame(1, (int) $package->activePlanVersion->tour_nights);
        $this->assertSame(12, (int) $package->activePlanVersion->validity_duration);
        $this->assertSame('12 Months', PackageValidityDisplay::label($package));

        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'DST-VAL-001',
            'name' => 'Validity Package',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '50.0000',
            'sort_order' => 1,
            'is_active' => '1',
            'tour_nights' => '1',
            'tour_days' => '2',
            'roi_daily' => '0.1100',
            'roi_days' => '900',
            'validity_unlimited' => '1',
        ])->assertRedirect(route('admin.packages.edit', $package));

        $package->refresh()->load('activePlanVersion');
        $this->assertTrue((bool) $package->validity_unlimited);
        $this->assertNull($package->validity_duration);
        $this->assertNull($package->validity_unit);
        $this->assertSame('Unlimited', PackageValidityDisplay::label($package));
        $this->assertSame(900, (int) $package->activePlanVersion->roi_days);

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('Validity')
            ->assertSeeText('Unlimited');

        $this->actingAs($admin)
            ->get(route('admin.packages.show', $package))
            ->assertOk()
            ->assertSeeText('Package Validity')
            ->assertSeeText('Unlimited');

        $this->assertSame($before, WalletLedgerEntry::query()->count());
    }

    public function test_validity_change_does_not_rewrite_historical_purchase_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = Member::query()->create([
            'member_id' => 'STVAL0001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Validity Member',
            'mobile_no' => '9000000088',
            'email' => 'validity.member@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-VAL-002',
            'name' => 'Validity Snapshot',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '100.0000',
            'sort_order' => 2,
            'is_active' => '1',
            'roi_daily' => '0.2500',
            'roi_days' => '800',
            'validity_duration' => '1',
            'validity_unit' => 'years',
        ])->assertRedirect();

        $package = Package::query()->where('code', 'DST-VAL-002')->firstOrFail();
        $pending = PackagePurchase::query()->create([
            'reference' => 'PUR-VAL-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-VAL-1',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => now(),
            'funding_source' => PackagePurchase::FUNDING_EXTERNAL,
        ]);

        app(PackagePurchaseService::class)->approve($pending, $admin);
        $approved = $pending->fresh();
        $this->assertFalse((bool) $approved->validity_unlimited);
        $this->assertSame(1, (int) $approved->validity_duration);
        $this->assertSame('years', $approved->validity_unit);

        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'DST-VAL-002',
            'name' => 'Validity Snapshot',
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => '100.0000',
            'sort_order' => 2,
            'is_active' => '1',
            'roi_daily' => '0.2500',
            'roi_days' => '800',
            'validity_duration' => '6',
            'validity_unit' => 'months',
        ])->assertRedirect();

        $this->assertSame(1, (int) $approved->fresh()->validity_duration);
        $this->assertSame('years', $approved->fresh()->validity_unit);
        $this->assertSame(6, (int) $package->fresh()->validity_duration);
        $this->assertSame('months', $package->fresh()->validity_unit);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_validity_duration_without_unit_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'DST-VAL-003',
            'name' => 'Bad Validity',
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '50.0000',
            'sort_order' => 3,
            'is_active' => '1',
            'validity_duration' => '10',
        ])->assertSessionHasErrors('validity_duration');
    }
}
