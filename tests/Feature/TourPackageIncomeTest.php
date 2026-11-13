<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomeAccrual;
use App\Services\PackageIncomePolicy;
use App\Services\TourPackageIncomeActivation;
use App\Services\TourPackagePurchaseAudit;
use App\Services\WalletLedgerPoster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TourPackageIncomeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_tour_package_definition_matches_proposed_premium_schedule(): void
    {
        $definition = app(PackageIncomePolicy::class)->definition('DST-TOUR-003');
        $this->assertNotNull($definition);
        $this->assertSame('2.5000', $definition['roi_daily']);
        $this->assertSame(1000, $definition['roi_days']);
        $this->assertSame('2.4000', $definition['commission_pool']);
        $this->assertSame(400, $definition['commission_days']);
        $this->assertSame(10, (int) config('deltasupport.package_income.wait_days'));
    }

    public function test_both_packages_process_on_the_same_business_date_with_correct_amounts(): void
    {
        $this->arm();
        $this->activateTour('2026-11-01');
        $roiPackage = $this->package('DST-ROI-002', Package::TYPE_TOUR_ROI, '100.0000');
        $tourPackage = $this->package('DST-TOUR-003', Package::TYPE_PREMIUM, '1000.0000');
        $roiMember = $this->member('STROI0001', 'ST666666', 'roi@example.test');
        $tourMember = $this->member('STTOUR001', 'ST666666', 'tour@example.test');
        $this->purchase($roiMember, $roiPackage, '2026-11-01 06:00:00', 'ROI-A');
        $this->purchase($tourMember, $tourPackage, '2026-11-01 06:00:00', 'TOUR-A');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        $result = app(PackageIncomeAccrual::class)->runControlled('2026-11-11');
        $repeat = app(PackageIncomeAccrual::class)->runControlled('2026-11-11');
        $dry = app(PackageIncomeAccrual::class)->runControlled('2026-11-11', true);

        $this->assertSame(2, $result['roi_records']);
        $this->assertSame(0, $repeat['roi_records']);
        $this->assertSame(0, $dry['roi_records']);
        $this->assertSame(0, $dry['commission_records']);
        $this->assertSame('0.2500', PackageRoiAccrual::query()->where('package_code', 'DST-ROI-002')->value('amount'));
        $this->assertSame('2.5000', PackageRoiAccrual::query()->where('package_code', 'DST-TOUR-003')->value('amount'));
        $this->assertSame('0.2500', $roiMember->fresh()->roi_wallet_amount);
        $this->assertSame('2.5000', $tourMember->fresh()->roi_wallet_amount);
        $this->assertSame(2, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
    }

    public function test_tour_waiting_period_and_duration_edges(): void
    {
        $this->arm();
        $this->activateTour('2026-11-01');
        $member = $this->member('STTOUR002', 'ST666666', 'wait@example.test');
        $package = $this->package('DST-TOUR-003', Package::TYPE_PREMIUM, '1000.0000');
        $this->purchase($member, $package, '2026-11-01 06:00:00', 'TOUR-WAIT');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 00:30:00', 'Asia/Kolkata'));
        $before = app(PackageIncomeAccrual::class)->runControlled('2026-11-10');
        $this->assertSame(0, $before['roi_records']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        $first = app(PackageIncomeAccrual::class)->runControlled('2026-11-11');
        $this->assertSame(1, $first['roi_records']);
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);

        $last = CarbonImmutable::parse('2026-11-11', 'Asia/Kolkata')->addDays(999);
        CarbonImmutable::setTestNow($last->setTime(0, 30));
        app(PackageIncomeAccrual::class)->runControlled($last->toDateString());
        $this->assertSame(2, PackageRoiAccrual::query()->count());

        $afterLast = $last->addDay();
        CarbonImmutable::setTestNow($afterLast->setTime(0, 30));
        $after = app(PackageIncomeAccrual::class)->runControlled($afterLast->toDateString());
        $this->assertSame(0, $after['roi_records']);
        $this->assertSame(2, PackageRoiAccrual::query()->count());
    }

    public function test_tour_level_commission_uses_the_premium_pool_and_is_idempotent(): void
    {
        $this->arm();
        $this->activateTour('2026-11-01');
        $this->member('ST666666', 'ST666666', 'root@example.test');
        $locked = $this->member('ST100005', 'ST666666', 'locked@example.test');
        $fourth = $this->member('ST100004', 'ST100005', 'fourth@example.test');
        $third = $this->member('ST100003', 'ST100004', 'third@example.test');
        $second = $this->member('ST100002', 'ST100003', 'second@example.test');
        $sponsor = $this->member('ST100001', 'ST100002', 'sponsor@example.test');
        $buyer = $this->member('ST100000', 'ST100001', 'buyer@example.test');
        $package = $this->package('DST-TOUR-003', Package::TYPE_PREMIUM, '1000.0000');
        foreach ([$locked, $fourth, $third, $second, $sponsor] as $index => $upline) {
            $this->purchase($upline, $package, '2024-01-01 02:00:00', 'OLD-'.$index);
        }
        $this->purchase($buyer, $package, '2026-11-01 02:00:00', 'BUYER-TOUR');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        $this->artisan('financial:daily')->assertSuccessful();
        $this->artisan('financial:daily')->assertSuccessful();

        $this->assertSame('0.6000', PackageLevelAccrual::query()->where('member_id', 'ST100001')->value('amount'));
        $this->assertSame('2.4000', PackageLevelAccrual::query()->where('member_id', 'ST100001')->value('pool_amount'));
        $this->assertSame(4, PackageLevelAccrual::query()->count());
        $this->assertSame(4, WalletLedgerEntry::query()->where('wallet', 'working')->count());
        $this->assertSame('0.6000', $sponsor->fresh()->working_wallet_amount);
        $this->assertSame('2.5000', $buyer->fresh()->roi_wallet_amount);
    }

    public function test_partial_failure_recovery_does_not_double_tour_credits(): void
    {
        $this->arm();
        $this->activateTour('2026-11-01');
        $member = $this->member('STTOUR003', 'ST666666', 'partial@example.test');
        $this->purchase($member, $this->package('DST-TOUR-003', Package::TYPE_PREMIUM, '1000.0000'), '2026-11-01 06:00:00', 'PARTIAL');

        $calls = 0;
        $real = app(WalletLedgerPoster::class);
        $poster = Mockery::mock(WalletLedgerPoster::class)->makePartial();
        $poster->shouldReceive('append')->andReturnUsing(function (...$args) use (&$calls, $real) {
            $calls++;
            if ($calls === 1) {
                throw new RuntimeException('ledger failed on tour roi');
            }

            return $real->append(...$args);
        });
        $this->app->instance(WalletLedgerPoster::class, $poster);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:30:00', 'Asia/Kolkata'));
        try {
            app(PackageIncomeAccrual::class)->runControlled('2026-11-11');
            $this->fail('Tour ROI ledger failure should stop the run.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ledger failed on tour roi', $exception->getMessage());
        }

        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);

        $this->app->instance(WalletLedgerPoster::class, $real);
        $retry = app(PackageIncomeAccrual::class)->runControlled('2026-11-11');
        $this->assertSame(1, $retry['roi_records']);
        $this->assertSame('2.5000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->where('wallet', 'roi')->count());
    }

    public function test_tour_benefits_remain_on_the_seeded_catalog_row(): void
    {
        $this->seed(\Database\Seeders\ProductionCatalogSeeder::class);
        $tour = Package::query()->where('code', 'DST-TOUR-003')->firstOrFail();
        $this->assertSame(Package::TYPE_PREMIUM, $tour->package_type);
        $this->assertTrue((bool) $tour->roi_eligible);
        $this->assertTrue((bool) $tour->level_commission_eligible);
        $this->assertStringContainsString('Three holiday trips over three years', (string) $tour->benefits);
        $this->assertStringContainsString('Digha tour after 30 days', (string) $tour->tour_details);
        $this->assertStringContainsString('Goa and Gangtok', (string) $tour->tour_details);
    }

    public function test_read_only_tour_purchase_audit_classifies_waiting_and_eligible_rows(): void
    {
        $this->activateTour('2026-11-01');
        $package = $this->package('DST-TOUR-003', Package::TYPE_PREMIUM, '1000.0000');
        $waiting = $this->member('STWAIT01', 'ST666666', 'waiting@example.test');
        $ready = $this->member('STREADY01', 'ST666666', 'ready@example.test');
        $this->purchase($waiting, $package, '2026-11-05 06:00:00', 'WAIT');
        $this->purchase($ready, $package, '2026-10-01 06:00:00', 'READY');
        PackagePurchase::query()->create([
            'reference' => 'PUR-PEND',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $waiting->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'PEND',
            'status' => 'pending',
            'submitted_at' => '2026-11-10 06:00:00',
            'reviewed_at' => null,
        ]);

        $report = app(TourPackagePurchaseAudit::class)->report('2026-11-12');
        $this->assertSame(3, $report['totals']['all']);
        $this->assertSame(2, $report['totals']['approved']);
        $this->assertSame(1, $report['totals']['pending']);
        $this->assertTrue(collect($report['approved'])->firstWhere('reference', 'PUR-WAIT')['waiting']);
        $this->assertTrue(collect($report['approved'])->firstWhere('reference', 'PUR-READY')['eligible_today']);
        $this->assertSame('2026-11-01', $report['activation_business_date']);
        $this->assertSame('2.5000', $report['potential_daily_roi']);

        $this->artisan('financial:audit-tour-purchases', [
            '--database' => (string) DB::connection()->getDatabaseName(),
            '--date' => '2026-11-12',
        ])->assertSuccessful()->expectsOutputToContain('Read-only');
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

    private function member(string $memberId, ?string $sponsorId, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId === null ? null : 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $type, string $price): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => 'Package '.$code,
            'price_usd' => $price,
            'denomination' => 'USDT',
            'tour_title' => 'Tour title',
            'tour_details' => 'Tour details preserved',
            'benefits' => 'Tour benefits preserved',
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
            'status' => 'approved',
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
