<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomeAccrual;
use App\Services\TourPackageIncomeActivation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TourPackageForwardOnlyActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_missing_activation_date_fails_closed_for_tour_income(): void
    {
        $this->arm();
        $member = $this->member('STTOURF01', 'failclosed@example.test');
        $this->purchase($member, $this->tourPackage(true), '2026-10-01 06:00:00', 'NO-ACT');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 00:30:00', 'Asia/Kolkata'));
        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-12', true);
        $live = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');

        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, $live['roi_records']);
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertTrue(collect($dry['skipped'])->contains(fn (string $reason): bool => str_contains($reason, 'activation date is missing')));
    }

    public function test_existing_purchase_before_activation_starts_on_activation_floor_not_history(): void
    {
        $this->arm();
        $this->activateTour('2026-11-12');
        $member = $this->member('STTOURF02', 'existing@example.test');
        $this->purchase($member, $this->tourPackage(true), '2026-10-01 06:00:00', 'OLD');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        $before = app(PackageIncomeAccrual::class)->runControlled('2026-11-11', true);
        $this->assertSame(0, $before['roi_records']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 00:30:00', 'Asia/Kolkata'));
        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-12', true);
        $live = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');
        $repeat = app(PackageIncomeAccrual::class)->runControlled('2026-11-12');

        $this->assertSame(1, $dry['roi_records']);
        $this->assertSame(1, $live['roi_records']);
        $this->assertSame(0, $repeat['roi_records']);
        $this->assertSame('2026-11-12', PackageRoiAccrual::query()->value('business_date')->toDateString());
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '<', '2026-11-12')->count());
    }

    public function test_new_purchase_after_activation_still_respects_wait_days(): void
    {
        $this->arm();
        $this->activateTour('2026-11-01');
        $member = $this->member('STTOURF03', 'new@example.test');
        $this->purchase($member, $this->tourPackage(true), '2026-11-05 06:00:00', 'NEW');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-14 00:30:00', 'Asia/Kolkata'));
        $waiting = app(PackageIncomeAccrual::class)->runControlled('2026-11-14', true);
        $this->assertSame(0, $waiting['roi_records']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 00:30:00', 'Asia/Kolkata'));
        $ready = app(PackageIncomeAccrual::class)->runControlled('2026-11-15');
        $this->assertSame(1, $ready['roi_records']);
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);
    }

    public function test_duration_stays_anchored_to_original_first_accrual_and_is_not_restarted(): void
    {
        $this->arm();
        $this->activateTour('2026-11-12');
        $member = $this->member('STTOURF04', 'duration@example.test');
        // Original first accrual = 2026-10-11; ROI window ends 2026-10-11 + 999 days.
        $this->purchase($member, $this->tourPackage(true), '2026-10-01 06:00:00', 'DUR');

        $originalFirst = CarbonImmutable::parse('2026-10-11', 'Asia/Kolkata');
        $last = $originalFirst->addDays(999);
        CarbonImmutable::setTestNow($last->setTime(0, 30));
        app(PackageIncomeAccrual::class)->runControlled($last->toDateString());
        $this->assertSame(1, PackageRoiAccrual::query()->count());

        $after = $last->addDay();
        CarbonImmutable::setTestNow($after->setTime(0, 30));
        $result = app(PackageIncomeAccrual::class)->runControlled($after->toDateString());
        $this->assertSame(0, $result['roi_records']);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
    }

    public function test_recover_cannot_backfill_before_activation(): void
    {
        $this->arm();
        $this->activateTour('2026-11-12');
        $member = $this->member('STTOURF05', 'recover@example.test');
        $this->purchase($member, $this->tourPackage(true), '2026-10-01 06:00:00', 'REC');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 01:00:00', 'Asia/Kolkata'));
        $this->artisan('financial:recover')->assertSuccessful();

        $this->assertSame(0, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-11')->count());
        $this->assertSame(1, PackageRoiAccrual::query()->whereDate('business_date', '2026-11-12')->count());
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);
    }

    public function test_dst_roi_002_is_unaffected_when_tour_activation_is_missing(): void
    {
        $this->arm();
        $roiMember = $this->member('STROI002A', 'roi@example.test');
        $tourMember = $this->member('STTOURF06', 'tour@example.test');
        $this->purchase($roiMember, $this->roiPackage(), '2026-11-01 06:00:00', 'ROI');
        $this->purchase($tourMember, $this->tourPackage(true), '2026-11-01 06:00:00', 'TOUR');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        $result = app(PackageIncomeAccrual::class)->runControlled('2026-11-11');

        $this->assertSame(1, $result['roi_records']);
        $this->assertSame('0.2500', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $tourMember->fresh()->roi_wallet_amount);
        $this->assertSame(0, PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->count());
    }

    public function test_dedicated_activation_command_is_idempotent_and_refuses_date_change(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->tourPackage(false);
        $db = (string) DB::connection()->getDatabaseName();

        $this->artisan('financial:activate-tour-income', [
            '--database' => $db,
            '--date' => '2026-11-14',
            '--preview' => true,
        ])->assertSuccessful()->expectsOutputToContain('Preview only');

        $this->assertFalse((bool) Package::query()->where('code', 'DST-TOUR-003')->value('roi_eligible'));
        $this->assertSame(0, PackageIncomeActivation::query()->count());

        $this->artisan('financial:activate-tour-income', [
            '--database' => $db,
            '--date' => '2026-11-14',
            '--confirm' => TourPackageIncomeActivation::CONFIRM,
        ])->assertSuccessful()->expectsOutputToContain('Activated');

        $this->assertTrue((bool) Package::query()->where('code', 'DST-TOUR-003')->value('roi_eligible'));
        $this->assertSame(Package::TYPE_PREMIUM, Package::query()->where('code', 'DST-TOUR-003')->value('package_type'));
        $this->assertSame('2026-11-14', PackageIncomeActivation::query()->value('activation_business_date')->toDateString());

        $this->artisan('financial:activate-tour-income', [
            '--database' => $db,
            '--date' => '2026-11-14',
            '--confirm' => TourPackageIncomeActivation::CONFIRM,
        ])->assertSuccessful()->expectsOutputToContain('already complete');

        $this->artisan('financial:activate-tour-income', [
            '--database' => $db,
            '--date' => '2026-11-15',
            '--confirm' => TourPackageIncomeActivation::CONFIRM,
        ])->assertFailed()->expectsOutputToContain('cannot be silently changed');

        $this->assertSame(1, PackageIncomeActivation::query()->count());
        $this->assertSame('2026-11-14', PackageIncomeActivation::query()->value('activation_business_date')->toDateString());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_emergency_stop_still_blocks_tour_posting_after_activation(): void
    {
        $this->arm();
        $this->activateTour('2026-11-12');
        $row = FinancialControl::current();
        $row->financial_emergency_stop = true;
        $row->save();
        $member = $this->member('STTOURF07', 'stop@example.test');
        $this->purchase($member, $this->tourPackage(true), '2026-10-01 06:00:00', 'STOP');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful()->expectsOutputToContain('Emergency stop');
        $this->assertSame(0, PackageRoiAccrual::query()->count());
    }

    private function activateTour(string $date): void
    {
        PackageIncomeActivation::query()->create([
            'package_code' => 'DST-TOUR-003',
            'activation_business_date' => $date,
            'activated_at' => now(),
            'activated_by' => 'test',
            'confirm_token' => TourPackageIncomeActivation::CONFIRM,
            'terms_snapshot' => [
                'roi_daily' => '2.5000',
                'roi_days' => 1000,
                'commission_pool' => '2.4000',
                'commission_days' => 400,
            ],
        ]);
    }

    private function arm(): void
    {
        $row = FinancialControl::current();
        $row->financial_automation_armed = true;
        $row->financial_emergency_stop = false;
        $row->save();
    }

    private function member(string $memberId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function tourPackage(bool $eligible): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-TOUR-003'], [
            'package_type' => $eligible ? Package::TYPE_PREMIUM : Package::TYPE_TOUR,
            'name' => 'Delta Support 3-Year Tour Package',
            'price_usd' => '1000.0000',
            'denomination' => 'USDT',
            'tour_title' => 'Enjoy digha',
            'tour_details' => 'Three holiday trips over three years',
            'benefits' => 'Three holiday trips over three years',
            'roi_eligible' => $eligible,
            'level_commission_eligible' => $eligible,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function roiPackage(): Package
    {
        return Package::query()->updateOrCreate(['code' => 'DST-ROI-002'], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Delta Support Digha Tour + Monthly Income',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 20,
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
            'status' => 'approved',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
