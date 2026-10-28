<?php

namespace Tests\Feature;

use App\Models\CommissionPlanLevel;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Services\FinancialScheduleRegistrar;
use App\Services\PackageIncomeReadiness;
use App\Services\PackageLevelCommissionEngine;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageIncomeReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_roi_eligible_package_is_blocked_without_a_stored_rate_and_creates_no_credit(): void
    {
        $member = $this->member('STROI001', 'STROOT001');
        $this->member('STROOT001', null);
        $package = $this->package('ROI-YES', true, true);
        $this->purchase($member, $package, 'tour_roi');

        $row = app(PackageIncomeReadiness::class)->member($member->member_id);

        $this->assertTrue($row['roi_eligible']);
        $this->assertSame('blocked', $row['roi']['status']);
        $this->assertStringContainsString('No amount was calculated', $row['roi']['reason']);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
    }

    public function test_verification_package_is_skipped_for_roi_and_level_income(): void
    {
        $member = $this->member('STVER001', 'STROOT001');
        $this->member('STROOT001', null);
        $package = $this->package('VERIFY-1', false, false);
        $this->purchase($member, $package, 'tour');

        $row = app(PackageIncomeReadiness::class)->member($member->member_id);

        $this->assertFalse($row['roi_eligible']);
        $this->assertFalse($row['level_eligible']);
        $this->assertSame('skipped', $row['roi']['status']);
        $this->assertSame('skipped', $row['level']['status']);
        $this->assertStringContainsString('not marked ROI eligible', $row['roi']['reason']);
        $this->assertStringContainsString('not a level-income eligible', $row['level']['reason']);
    }

    public function test_unpaid_member_is_skipped_and_receives_no_income(): void
    {
        $member = $this->member('STUNPAID1', 'STROOT001');
        $before = $member->only(['roi_wallet_amount', 'working_wallet_amount', 'activation_wallet_amount']);

        $row = app(PackageIncomeReadiness::class)->member($member->member_id);

        $this->assertSame([], $row['approved_packages']);
        $this->assertSame('skipped', $row['roi']['status']);
        $this->assertSame('skipped', $row['level']['status']);
        $this->assertSame('No approved package purchase.', $row['roi']['reason']);
        $this->assertSame($before, $member->fresh()->only(['roi_wallet_amount', 'working_wallet_amount', 'activation_wallet_amount']));
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_twenty_unlocked_levels_do_not_calculate_a_commission_without_a_base(): void
    {
        $root = $this->member('STROOT020', null);
        $sponsor = $this->member('STSPON020', $root->member_id);
        $package = $this->package('HILLS-20', true, true);
        $this->purchase($sponsor, $package, 'tour_roi');
        foreach (range(1, 5) as $index) {
            $direct = $this->member('STDIR020'.$index, $sponsor->member_id);
            $this->purchase($direct, $package, 'tour_roi');
        }

        $row = app(PackageIncomeReadiness::class)->member($sponsor->member_id);

        $this->assertSame(5, $row['qualifying_directs']);
        $this->assertSame(20, $row['unlocked_levels']);
        $this->assertSame('blocked', $row['level']['status']);
        $this->assertStringContainsString('No commission base is stored', $row['level']['reason']);
        $this->assertSame('25.0000', CommissionPlanLevel::query()->where('level', 1)->value('percentage'));
        $this->assertSame('7.5000', CommissionPlanLevel::query()->where('level', 20)->value('percentage'));
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
        $this->assertSame('0.0000', $sponsor->fresh()->working_wallet_amount);
    }

    public function test_member_with_no_qualifying_directs_has_level_income_locked(): void
    {
        $root = $this->member('STROOTLOCK', null);
        $member = $this->member('STLOCK001', $root->member_id);
        $package = $this->package('HILLS-LOCK', true, true);
        $this->purchase($member, $package, 'tour_roi');

        $row = app(PackageIncomeReadiness::class)->member($member->member_id);

        $this->assertSame(0, $row['unlocked_levels']);
        $this->assertSame('skipped', $row['level']['status']);
        $this->assertStringContainsString('unlock 0 levels', $row['level']['reason']);
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_missing_sponsor_blocks_level_income_without_a_credit(): void
    {
        $member = $this->member('STMISS001', 'STABSENT');
        $package = $this->package('HILLS-MISS', true, true);
        $this->purchase($member, $package, 'tour_roi');

        $row = app(PackageIncomeReadiness::class)->member($member->member_id);

        $this->assertSame('STABSENT', $row['sponsor_gap']);
        $this->assertSame('skipped', $row['level']['status']);
        $this->assertStringContainsString('no member record', $row['level']['reason']);
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_repeated_and_overlapping_previews_do_not_insert_income(): void
    {
        $root = $this->member('STROOTDUP', null);
        $member = $this->member('STDUP001', $root->member_id);
        $package = $this->package('ROI-DUP', true, true);
        $this->purchase($member, $package, 'tour_roi');
        $service = app(PackageIncomeReadiness::class);
        $before = $service->ledger();

        $service->report([$member->member_id]);
        DB::transaction(function () use ($service, $member): void {
            $service->report([$member->member_id]);
            DB::transaction(function () use ($service, $member): void {
                $service->report([$member->member_id]);
            });
        });

        $this->assertSame($before, $service->ledger());
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());

        $schedule = new Schedule;
        app(FinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
    }

    public function test_preview_keeps_wallet_balances_and_history_empty(): void
    {
        $root = $this->member('STROOTWAL', null);
        $member = $this->member('ST100003', $root->member_id);
        $member->forceFill([
            'roi_wallet_amount' => '1.5000',
            'working_wallet_amount' => '2.5000',
            'activation_wallet_amount' => '3.5000',
        ])->save();
        $package = $this->package('ROI-WAL', true, true);
        $this->purchase($member, $package, 'tour_roi');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.finance.income-test.preview'), ['preview' => 'isolated'])
            ->assertOk()
            ->assertSeeText('no income rows were written')
            ->assertSeeText('No amount was calculated');

        $member->refresh();
        $this->assertSame('1.5000', $member->roi_wallet_amount);
        $this->assertSame('2.5000', $member->working_wallet_amount);
        $this->assertSame('3.5000', $member->activation_wallet_amount);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.income.roi'))
            ->assertOk()
            ->assertSeeText('No ROI records found.');
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.income.level'))
            ->assertOk()
            ->assertSeeText('No level income records found.');
    }

    public function test_package_commission_engine_refuses_to_credit_and_keeps_twenty_level_rates(): void
    {
        $root = $this->member('STROOTENG', null);
        $member = $this->member('STENG001', $root->member_id);
        $coastal = $this->package('DEMO-COAST', true, true);
        $hills = $this->package('DEMO-HILLS', true, true);
        $verification = $this->package('VERIFY-1', false, false);
        $this->purchase($member, $coastal, 'tour_roi');
        $this->assertTrue($coastal->roi_eligible);
        $this->assertTrue($hills->level_commission_eligible);
        $this->assertFalse($verification->roi_eligible);
        $this->assertFalse($verification->level_commission_eligible);

        $engine = app(PackageLevelCommissionEngine::class);
        $report = $engine->evaluate([$member->member_id]);
        $this->assertFalse($report['calculation_available']);
        $this->assertSame('25.0000', CommissionPlanLevel::query()->where('level', 1)->value('percentage'));
        $this->assertSame('100.0000', $this->combinedPlanRate());
        $before = $member->fresh()->working_wallet_amount;

        try {
            $engine->credit();
            $this->fail('The package commission engine credited a wallet.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('does not credit wallets', $exception->getMessage());
        }

        $this->assertSame($before, $member->fresh()->working_wallet_amount);
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_income_test_page_is_admin_only_and_hidden_in_production(): void
    {
        $this->get(route('admin.finance.income-test'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.finance.income-test'))
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.finance.income-test'))
            ->assertOk()
            ->assertSeeText('Income Scheduler Testing')
            ->assertSeeText('Preview ROI Calculation')
            ->assertSeeText('ST100001');

        $this->app['env'] = 'production';
        $this->actingAs($admin)
            ->get(route('admin.finance.income-test'))
            ->assertNotFound();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->actingAs($admin)
            ->post(route('admin.finance.income-test.preview'), ['preview' => 'roi'])
            ->assertNotFound();
    }

    private function combinedPlanRate(): string
    {
        $total = '0.0000';
        foreach (CommissionPlanLevel::query()->orderBy('level')->pluck('percentage') as $percentage) {
            $total = bcadd($total, (string) $percentage, 4);
        }

        return $total;
    }

    private function member(string $memberId, ?string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId ?: $memberId,
            'sponsor_name' => $sponsorId ? 'Sponsor' : 'None',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'activation_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'roi_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, bool $roi, bool $level): Package
    {
        return Package::query()->create([
            'code' => $code,
            'package_type' => $roi || $level ? Package::TYPE_TOUR_ROI : Package::TYPE_TOUR,
            'name' => $code,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => $roi,
            'level_commission_eligible' => $level,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $type): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id.'-'.$type,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $type,
            'price_usd' => $package->price_usd,
            'denomination' => 'USDT',
            'currency_label' => 'USDT',
            'network' => 'Test network',
            'payment_reference' => 'PAY'.$member->member_id.$type,
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => now(),
        ]);
    }
}
