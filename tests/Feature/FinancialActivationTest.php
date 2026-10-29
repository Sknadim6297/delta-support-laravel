<?php

namespace Tests\Feature;

use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Services\DailyFinancialScheduleRegistrar;
use App\Services\PackageIncomeAccrual;
use App\Services\ReferralLevelEligibility;
use App\Services\WithdrawalWorkflow;
use App\Support\WithdrawalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FinancialActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_controls_default_off_and_the_daily_cycle_is_not_scheduled(): void
    {
        $this->assertFalse(config('deltasupport.package_income.enabled'));
        $this->assertFalse(config('deltasupport.wallet_ledger.enabled'));
        $row = FinancialControl::current();
        $this->assertFalse($row->roi_calculation_enabled);
        $this->assertFalse($row->roi_wallet_crediting_enabled);
        $this->assertFalse($row->level_wallet_crediting_enabled);
        $this->assertFalse($row->external_payouts_enabled);
        $this->assertFalse($row->daily_financial_cycle_enabled);

        $schedule = new Schedule;
        app(DailyFinancialScheduleRegistrar::class)->register($schedule);
        $this->assertSame([], $schedule->events());
        $this->artisan('financial:daily')->assertExitCode(1);
        $this->artisan('financial:daily', ['--dry-run' => true, '--date' => '2026-10-29'])->assertSuccessful();
    }

    public function test_roi_respects_the_wait_cap_duplicate_rule_and_demo_exclusion(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
        config([
            'deltasupport.package_income.enabled' => false,
            'deltasupport.wallet_ledger.enabled' => false,
            'deltasupport.controls.daily_financial_cycle_enabled' => true,
            'deltasupport.controls.roi_calculation_enabled' => true,
            'deltasupport.controls.roi_wallet_crediting_enabled' => false,
        ]);
        $package = $this->package();
        $member = $this->member('STFIN0001', 'ready@example.test');
        $waiting = $this->purchase($member, $package, '2026-10-29 02:00:00', 'TX-WAIT');
        $ready = $this->purchase($member, $package, '2026-10-17 02:00:00', 'TX-READY');
        $demo = $this->member('STFIN0002', 'demo@local-test.invalid');
        $this->purchase($demo, $package, '2026-10-17 02:00:00', 'LOCAL-TEST-DEMO');
        $rejected = $this->member('STFIN0003', 'rejected@example.test');
        $this->purchase($rejected, $package, '2026-10-17 02:00:00', 'TX-REJECTED', 'rejected');

        $first = app(PackageIncomeAccrual::class)->runControlled('2026-10-29');
        $again = app(PackageIncomeAccrual::class)->runControlled('2026-10-29');

        $this->assertSame(1, $first['roi_records']);
        $this->assertSame(0, $again['roi_records']);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
        $this->assertSame('calculated', PackageRoiAccrual::query()->value('status'));
        $this->assertSame($ready->id, PackageRoiAccrual::query()->value('package_purchase_id'));
        $this->assertSame('0.2500', PackageRoiAccrual::query()->value('amount'));
        $this->assertNotSame($waiting->id, PackageRoiAccrual::query()->value('package_purchase_id'));
        $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $demo->fresh()->roi_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());

        config(['deltasupport.controls.roi_wallet_crediting_enabled' => true]);
        $credited = app(PackageIncomeAccrual::class)->runControlled('2026-10-29');
        $repeat = app(PackageIncomeAccrual::class)->runControlled('2026-10-29');

        $this->assertSame(1, $credited['roi_records']);
        $this->assertSame(0, $repeat['roi_records']);
        $this->assertSame('accrued', PackageRoiAccrual::query()->value('status'));
        $this->assertSame('0.2500', $member->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $member->fresh()->working_wallet_amount);
        $this->assertSame('0.0000', $demo->fresh()->roi_wallet_amount);
        $this->assertSame(1, WalletLedgerEntry::query()->count());
        $entry = WalletLedgerEntry::query()->firstOrFail();
        $this->assertSame('0.0000', $entry->opening_balance);
        $this->assertSame('0.2500', $entry->closing_balance);
    }

    public function test_direct_referral_unlocks_follow_the_stored_20_level_rule(): void
    {
        $eligibility = app(ReferralLevelEligibility::class);
        $this->assertSame(20, ReferralLevelEligibility::MAX_LEVELS);
        $this->assertSame([0, 4, 8, 12, 16, 20], [
            $eligibility->levelsForCount(0),
            $eligibility->levelsForCount(1),
            $eligibility->levelsForCount(2),
            $eligibility->levelsForCount(3),
            $eligibility->levelsForCount(4),
            $eligibility->levelsForCount(5),
        ]);
    }

    public function test_withdrawal_reservation_approval_and_rejection_do_not_send_a_payout(): void
    {
        config([
            'deltasupport.controls.withdrawal_requests_enabled' => true,
            'deltasupport.controls.withdrawal_approval_enabled' => true,
            'deltasupport.controls.external_payouts_enabled' => true,
        ]);
        $settings = WithdrawalSetting::current();
        $settings->forceFill([
            'eligible_wallets' => ['roi'],
            'supported_networks' => ['TRC20'],
            'charge_type' => 'none',
            'charge_value' => '0.0000',
            'minimum_amount' => '20.0000',
            'maximum_amount' => '40.0000',
            'address_validation_policy' => 'Public address only.',
            'member_cancellation_policy' => 'An administrator can reject a pending request.',
        ])->save();
        $member = $this->member('STFIN0004', 'withdraw@example.test', '50.0000');
        $member->forceFill([
            'security_pin_hash' => Hash::make('482913'),
            'security_pin_set_at' => now(),
        ])->save();
        $admin = User::factory()->create(['is_admin' => true]);
        $workflow = app(WithdrawalWorkflow::class);
        $key = (string) Str::uuid();

        $first = $workflow->submit($member, $this->withdrawal($key, '20.0000'));
        $replay = $workflow->submit($member, $this->withdrawal($key, '20.0000'));
        $second = $workflow->submit($member, $this->withdrawal((string) Str::uuid(), '20.0000'));

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(2, WithdrawalRequest::query()->count());
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        try {
            $workflow->submit($member, $this->withdrawal((string) Str::uuid(), '20.0000'));
            $this->fail('A third reservation was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $workflow->reject($first, $admin, 'The address could not be confirmed.');
        $approved = $workflow->approve($second->fresh(), $admin);
        $this->assertSame(WithdrawalStatus::REJECTED, $first->fresh()->status);
        $this->assertSame(WithdrawalStatus::APPROVED, $approved->status);
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);

        try {
            $workflow->settle($approved);
            $this->fail('An external payout was sent.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No payout provider', $exception->getMessage());
        }
        $this->assertNull($approved->fresh()->payout_txid);
    }

    public function test_saving_financial_controls_does_not_remove_history_and_guests_cannot_open_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        PackageRoiAccrual::query()->create([
            'reference' => 'PROI-KEEP-1',
            'idempotency_key' => 'keep|2026-10-29',
            'package_purchase_id' => $this->purchase($this->member('STFIN0005', 'keep@example.test'), $this->package(), '2026-10-01 00:00:00', 'TX-KEEP')->id,
            'member_id' => 'STFIN0005',
            'member_name' => 'Member STFIN0005',
            'package_code' => 'DEMO-COAST',
            'package_name' => 'Coastal Weekend',
            'business_date' => '2026-10-29',
            'amount' => '0.2500',
            'status' => 'accrued',
        ]);

        $this->get(route('admin.settings.financial'))->assertRedirect(route('login'));
        $this->actingAs($admin)->put(route('admin.settings.financial.update'), [
            'roi_calculation_enabled' => '1',
        ])->assertRedirect(route('admin.settings.financial'));

        $this->assertTrue(FinancialControl::current()->roi_calculation_enabled);
        $this->assertFalse(FinancialControl::current()->external_payouts_enabled);
        $this->actingAs($admin)->put(route('admin.settings.financial.update'), [])->assertRedirect();
        $this->assertFalse(FinancialControl::current()->fresh()->roi_calculation_enabled);
        $this->assertSame(1, PackageRoiAccrual::query()->count());
    }

    private function member(string $memberId, string $email, string $roi = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => substr(preg_replace('/\D/', '', $memberId).'0000000000', 0, 10),
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(): Package
    {
        return Package::query()->firstOrCreate(['code' => 'DEMO-COAST'], [
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Coastal Weekend',
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function purchase(Member $member, Package $package, string $reviewedAt, string $reference, string $status = 'approved'): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$reference,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => '100.0000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => $reference,
            'status' => $status,
            'submitted_at' => $reviewedAt,
            'reviewed_at' => $status === 'approved' ? $reviewedAt : null,
        ]);
    }

    /**
     * @return array{wallet: string, amount: string, network: string, address: string, idempotency_key: string}
     */
    private function withdrawal(string $key, string $amount): array
    {
        return [
            'wallet' => 'roi',
            'amount' => $amount,
            'network' => 'TRC20',
            'address' => 'TPublicAddressOnly123',
            'idempotency_key' => $key,
            'security_pin' => '482913',
        ];
    }
}
