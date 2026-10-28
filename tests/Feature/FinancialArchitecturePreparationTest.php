<?php

namespace Tests\Feature;

use App\Models\CommissionPlanLevel;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Services\CommissionPlan;
use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeTermRecorder;
use App\Services\PackagePurchaseService;
use App\Services\ReferralLevelEligibility;
use App\Services\WalletBalanceReconciliation;
use App\Support\LegacyFinancialActions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FinancialArchitecturePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delta_support_plan_has_twenty_inactive_decimal_rates_and_leaves_legacy_rates_unchanged(): void
    {
        $plan = app(CommissionPlan::class);

        $this->assertFalse($plan->isActive());
        $this->assertSame([], $plan->integrityErrors());
        $this->assertSame(20, CommissionPlanLevel::query()->count());
        $this->assertSame(20, CommissionPlanLevel::query()->distinct()->count('level'));
        $this->assertSame('25.0000', CommissionPlanLevel::query()->where('level', 1)->value('percentage'));
        $this->assertSame('12.5000', CommissionPlanLevel::query()->where('level', 2)->value('percentage'));
        $this->assertSame('7.5000', CommissionPlanLevel::query()->where('level', 3)->value('percentage'));
        $this->assertSame('2.5000', CommissionPlanLevel::query()->where('level', 17)->value('percentage'));
        $this->assertSame('5.0000', CommissionPlanLevel::query()->where('level', 18)->value('percentage'));
        $this->assertSame('7.5000', CommissionPlanLevel::query()->where('level', 20)->value('percentage'));
        $this->assertNull(CommissionPlanLevel::query()->where('level', 21)->value('id'));

        $total = '0.0000';
        foreach (CommissionPlanLevel::query()->orderBy('level')->pluck('percentage') as $percentage) {
            $total = bcadd($total, (string) $percentage, 4);
        }
        $this->assertSame('100.0000', $total);

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('level_commissions'));
        $this->assertSame(32, \Illuminate\Support\Facades\DB::table('level_commissions')->count());
        $this->assertFalse(class_exists(\App\Services\LevelCommissionRateResolver::class));
        $this->assertFalse(class_exists(\App\Services\LevelCommissionGenerationService::class));
    }

    public function test_eligibility_counts_distinct_direct_members_with_an_approved_qualifying_package(): void
    {
        $sponsor = $this->member('STELIG001', 'ST666666');
        $sponsor->forceFill([
            'activation_wallet_amount' => '1.5000',
            'working_wallet_amount' => '2.5000',
            'roi_wallet_amount' => '3.5000',
        ])->save();
        $package = $this->package('PKG-ROI', Package::TYPE_TOUR_ROI);
        $tour = $this->package('PKG-TOUR', Package::TYPE_TOUR);

        $first = $this->member('STELIG002', $sponsor->member_id);
        $this->purchase($first, $package, PackagePurchase::APPROVED, 'TXELIG0001');
        $this->purchase($first, $package, PackagePurchase::APPROVED, 'TXELIG0002');
        $this->purchase($first, $tour, PackagePurchase::APPROVED, 'TXELIG0003');

        $pending = $this->member('STELIG003', $sponsor->member_id);
        $this->purchase($pending, $package, PackagePurchase::PENDING, 'TXELIG0004');

        $rejected = $this->member('STELIG004', $sponsor->member_id);
        $this->purchase($rejected, $package, PackagePurchase::REJECTED, 'TXELIG0005');

        $tourOnly = $this->member('STELIG005', $sponsor->member_id);
        $this->purchase($tourOnly, $tour, PackagePurchase::APPROVED, 'TXELIG0006');

        $otherSponsor = $this->member('STELIG006', 'ST666666');
        $outsider = $this->member('STELIG007', $otherSponsor->member_id);
        $this->purchase($outsider, $package, PackagePurchase::APPROVED, 'TXELIG0007');
        $this->purchase($sponsor, $package, PackagePurchase::APPROVED, 'TXELIG0008');

        $unrelated = $this->member('STELIG008', 'ST666666');
        $eligibility = app(ReferralLevelEligibility::class);
        $this->assertSame([
            'qualifying_direct_count' => 1,
            'unlocked_levels' => 4,
        ], $eligibility->forMember($sponsor->member_id));
        $this->assertSame(1, $eligibility->forMember($otherSponsor->member_id)['qualifying_direct_count']);
        $this->assertSame(0, $eligibility->forMember($unrelated->member_id)['qualifying_direct_count']);
        $this->assertSame(0, $eligibility->levelsForCount(0));
        $this->assertSame(8, $eligibility->levelsForCount(2));
        $this->assertSame(12, $eligibility->levelsForCount(3));
        $this->assertSame(16, $eligibility->levelsForCount(4));
        $this->assertSame(20, $eligibility->levelsForCount(5));
        $this->assertSame(20, $eligibility->levelsForCount(6));

        $sponsor->refresh();
        $this->assertSame('1.5000', $sponsor->activation_wallet_amount);
        $this->assertSame('2.5000', $sponsor->working_wallet_amount);
        $this->assertSame('3.5000', $sponsor->roi_wallet_amount);
        $this->assertSame($sponsor->member_id, $first->fresh()->sponsor_id);
        $this->assertSame(0, RoiTransaction::query()->count());
    }

    public function test_income_terms_do_not_invent_rates_or_change_purchase_and_invoice_snapshots(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STTERM001', 'ST666666');
        $member->forceFill([
            'activation_wallet_amount' => '4.0000',
            'working_wallet_amount' => '5.0000',
            'roi_wallet_amount' => '6.0000',
        ])->save();
        $package = $this->package('PKG-TERM', Package::TYPE_PREMIUM);
        $purchase = $this->purchase($member, $package, PackagePurchase::PENDING, 'TXTERM0001', '33.2500');

        $invoice = app(PackagePurchaseService::class)->approve($purchase, $admin);

        $purchase->refresh();
        $this->assertSame('33.2500', $purchase->price_usd);
        $this->assertSame('33.2500', $invoice->price_usd);
        $this->assertSame(0, PackageIncomeTerm::query()->count());
        $this->assertSame(1, Invoice::query()->count());

        $term = app(PackageIncomeTermRecorder::class)->record($purchase);
        $this->assertSame('PIT-'.$purchase->reference, $term->reference);
        $this->assertNull($term->income_eligible);
        $this->assertNull($term->monthly_rate_percentage);
        $this->assertNull($term->monthly_amount);
        $this->assertNull($term->duration_months);
        $this->assertNull($term->commission_base);
        $this->assertNull($term->first_accrual_rule);
        $this->assertNull($term->business_timezone);
        $this->assertNotNull($term->snapshotted_at);

        $again = app(PackageIncomeTermRecorder::class)->record($purchase, [
            'monthly_rate_percentage' => '10.0000',
            'monthly_amount' => '99.0000',
            'duration_months' => 25,
            'commission_base' => 'monthly_roi',
        ]);
        $this->assertSame($term->id, $again->id);
        $this->assertNull($again->fresh()->monthly_rate_percentage);
        $this->assertSame('33.2500', $purchase->fresh()->price_usd);
        $this->assertSame('33.2500', $invoice->fresh()->price_usd);

        $this->expectException(RuntimeException::class);
        $term->update(['monthly_rate_percentage' => '10.0000']);
    }

    public function test_financial_generation_stays_disabled_and_reconciliation_does_not_change_balances(): void
    {
        config([
            'deltasupport.legacy_financial_actions' => false,
            'financial.accelerated.enabled' => true,
        ]);

        $member = $this->member('STSAFE001', 'ST666666');
        $member->forceFill([
            'activation_wallet_amount' => '7.2500',
            'working_wallet_amount' => '8.2500',
            'roi_wallet_amount' => '9.2500',
        ])->save();

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
        $this->assertFalse(LegacyFinancialActions::enabled());

        foreach (['roi:generate', 'rank:advance', 'financial:cycle'] as $command) {
            $this->artisan($command)
                ->expectsOutputToContain(LegacyFinancialActions::DISABLED_MESSAGE)
                ->assertExitCode(1);
        }

        $report = app(WalletBalanceReconciliation::class)->inspect($member->fresh());
        $this->assertSame('9.2500', $report['roi']['balance']);
        $this->assertSame('0.0000', $report['roi']['credits']);
        $this->assertSame('9.2500', $report['roi']['variance']);
        $this->assertSame('8.2500', $report['working']['variance']);
        $this->assertSame('7.2500', $report['activation']['variance']);

        $member->refresh();
        $this->assertSame('7.2500', $member->activation_wallet_amount);
        $this->assertSame('8.2500', $member->working_wallet_amount);
        $this->assertSame('9.2500', $member->roi_wallet_amount);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, PackageIncomeTerm::query()->count());
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr('9'.$memberId, 0, 10),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }

    private function package(string $code, string $type): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $type,
            'name' => $code,
            'price_usd' => '33.2500',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $status, string $paymentReference, string $price = '33.2500'): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$paymentReference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'currency_label' => 'Configured token',
            'network' => 'Configured network',
            'payment_reference' => $paymentReference,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }
}
