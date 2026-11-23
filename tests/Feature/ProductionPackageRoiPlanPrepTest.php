<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\WalletLedgerEntry;
use App\Services\OriginalTourPlanComparison;
use App\Services\PackageIncomePolicy;
use App\Services\ProductionPackageRoiDiagnosis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionPackageRoiPlanPrepTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_production_codes_and_five_roi_durations_are_mapped(): void
    {
        $preview = app(OriginalTourPlanComparison::class)->preview();
        $byCode = collect($preview['packages'])->keyBy('proposed_package_code');

        foreach (OriginalTourPlanComparison::PRODUCTION_PACKAGE_CODES as $code) {
            $this->assertTrue($byCode->has($code), $code.' missing from original tour plan');
        }

        $this->assertSame(900, $byCode['DST-TOUR-001']['proposed_roi_days']);
        $this->assertSame(800, $byCode['DST-ROI-002']['proposed_roi_days']);
        $this->assertSame(700, $byCode['DST-TOUR-003']['proposed_roi_days']);
        $this->assertSame(600, $byCode['DST-TOUR-004']['proposed_roi_days']);
        $this->assertSame(570, $byCode['DST-TOUR-005']['proposed_roi_days']);
        $this->assertFalse($byCode['DST-RY-006']['has_daily_roi']);
        $this->assertNull($byCode['DST-RY-006']['proposed_roi_days']);
    }

    public function test_candidates_are_not_merged_into_active_package_income(): void
    {
        $policy = app(PackageIncomePolicy::class);
        $candidates = config('deltasupport.original_tour_plan.candidate_package_income');

        $this->assertIsArray($candidates);
        $this->assertArrayHasKey('DST-TOUR-001', $candidates);
        $this->assertArrayHasKey('DST-TOUR-005', $candidates);
        $this->assertArrayNotHasKey('DST-RY-006', $candidates);

        $this->assertNull($policy->definition('DST-TOUR-001'));
        $this->assertNull($policy->definition('DST-TOUR-004'));
        $this->assertNull($policy->definition('DST-TOUR-005'));
        $this->assertSame('0.2500', $policy->definition('DST-ROI-002')['roi_daily']);
        $this->assertSame('2.5000', $policy->definition('DST-TOUR-003')['roi_daily']);
        $this->assertFalse((bool) config('deltasupport.original_tour_plan.active'));
        $this->assertFalse((bool) config('deltasupport.original_tour_plan.applies_to_new_purchases'));
    }

    public function test_policy_supports_roi_only_definitions_without_commission_pool(): void
    {
        config([
            'deltasupport.package_income.packages.DST-TOUR-001' => [
                'roi_daily' => '0.1058',
                'roi_days' => 900,
                'commission_pool' => null,
                'commission_days' => null,
            ],
        ]);

        $definition = app(PackageIncomePolicy::class)->definition('DST-TOUR-001');
        $this->assertSame('0.1058', $definition['roi_daily']);
        $this->assertSame(900, $definition['roi_days']);
        $this->assertNull($definition['commission_pool']);
        $this->assertNull($definition['commission_days']);
    }

    public function test_catalog_activation_migration_activates_existing_six_without_creating_or_enabling_income(): void
    {
        foreach ([
            ['DST-TOUR-001', '50.0000', false],
            ['DST-ROI-002', '100.0000', true],
            ['DST-TOUR-003', '500.0000', true],
            ['DST-TOUR-004', '1000.0000', false],
            ['DST-TOUR-005', '5000.0000', false],
            ['DST-RY-006', '50.0000', false],
        ] as [$code, $price, $roi]) {
            Package::query()->create([
                'code' => $code,
                'name' => $code,
                'price_usd' => $price,
                'denomination' => 'USDT',
                'package_type' => Package::TYPE_TOUR,
                'roi_eligible' => $roi,
                'level_commission_eligible' => $roi,
                'is_active' => false,
                'sort_order' => 1,
            ]);
        }

        Package::query()->create([
            'code' => 'VERIFY-1',
            'name' => 'Verification',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR,
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $beforeCount = Package::query()->count();

        $migration = require database_path('migrations/2026_11_23_150000_ensure_six_production_tour_packages_active.php');
        $migration->up();

        $this->assertSame($beforeCount, Package::query()->count());
        foreach (OriginalTourPlanComparison::PRODUCTION_PACKAGE_CODES as $code) {
            $pkg = Package::query()->where('code', $code)->firstOrFail();
            $this->assertTrue((bool) $pkg->is_active, $code);
        }

        $share = Package::query()->where('code', 'DST-RY-006')->firstOrFail();
        $this->assertFalse((bool) $share->roi_eligible);
        $this->assertFalse((bool) $share->level_commission_eligible);

        $verify = Package::query()->where('code', 'VERIFY-1')->firstOrFail();
        $this->assertFalse((bool) $verify->is_active, '150000 must not activate VERIFY-1');

        $this->assertNull(app(PackageIncomePolicy::class)->definition('DST-TOUR-001'));
        $this->assertFalse((bool) config('deltasupport.associate_commission.posting_enabled'));
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_diagnose_command_is_read_only_and_reports_missing_roi(): void
    {
        Package::query()->create([
            'code' => 'DST-TOUR-001',
            'name' => 'SILVER',
            'price_usd' => '50.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR,
            'roi_eligible' => false,
            'level_commission_eligible' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'GOLD',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $ledgerBefore = WalletLedgerEntry::query()->count();
        $termsBefore = PackageIncomeTerm::query()->count();

        $exit = Artisan::call('financial:diagnose-production-packages', ['--json' => true]);
        $this->assertSame(0, $exit);
        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);
        $this->assertFalse($report['mutates']);
        $this->assertContains('DST-TOUR-001', $report['missing_package_income_for_roi_tiers']);
        $this->assertContains('DST-TOUR-004', $report['missing_package_income_for_roi_tiers']);
        $this->assertContains('DST-TOUR-005', $report['missing_package_income_for_roi_tiers']);
        $this->assertFalse($report['associate']['posting_enabled_config']);
        $this->assertTrue($report['associate']['do_not_enable']);
        $this->assertSame($ledgerBefore, WalletLedgerEntry::query()->count());
        $this->assertSame($termsBefore, PackageIncomeTerm::query()->count());
    }

    public function test_diagnosis_service_explains_associate_posting_ui_source(): void
    {
        $report = app(ProductionPackageRoiDiagnosis::class)->report();

        $this->assertStringContainsString('postingEnabled()', $report['associate']['ui_source']);
        $this->assertStringContainsString('config cache', strtolower($report['associate']['discrepancy_explanation']));
        $this->assertFalse($report['original_tour_plan']['active']);
        $this->assertFalse($report['original_tour_plan']['candidate_merged']);
    }

    public function test_approved_purchase_snapshot_survives_diagnosis(): void
    {
        \App\Models\Member::query()->create([
            'member_id' => 'ST666666',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Admin Root',
            'mobile_no' => '9000000888',
            'email' => 'diagroot@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'DST-ROI-002',
            'name' => 'GOLD',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $purchase = PackagePurchase::query()->create([
            'reference' => 'PUR-DIAG-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => 'ST666666',
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX-DIAG-1',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now()->subDays(30),
            'reviewed_at' => now()->subDays(30),
        ]);
        $term = PackageIncomeTerm::query()->create([
            'package_purchase_id' => $purchase->id,
            'reference' => 'PIT-PUR-DIAG-1',
            'income_eligible' => true,
            'monthly_amount' => '0.2500',
            'duration_months' => 800,
            'snapshotted_at' => now()->subDays(30),
        ]);

        app(ProductionPackageRoiDiagnosis::class)->report();
        Artisan::call('financial:diagnose-production-packages');

        $this->assertSame('0.2500', (string) $term->fresh()->monthly_amount);
        $this->assertSame(800, $term->fresh()->duration_months);
        $this->assertSame(1, PackageIncomeTerm::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }
}
