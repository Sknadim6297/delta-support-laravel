<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PackagePurchaseReservation;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Services\PackagePurchaseService;
use App\Services\WalletLedgerPoster;
use App\Services\WorkingWalletFunds;
use App\Services\WithdrawalBalances;
use App\Services\MemberTransferWorkflow;
use App\Models\MemberTransferSetting;
use App\Support\WalletLedgerDirection;
use App\Support\WithdrawalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use App\Models\FinancialControl;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletPackagePurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'deltasupport.wallet_package_purchase.enabled' => true,
            'deltasupport.wallet_ledger.enabled' => true,
        ]);
    }

    public function test_wallet_request_reserves_funds_without_debit_activation_or_accrual(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-USDT', 'USDT', '40.0000');
        $key = (string) Str::uuid();

        $purchase = app(PackagePurchaseService::class)->submitWallet($member, $package, $key);
        $replay = app(PackagePurchaseService::class)->submitWallet($member->fresh(), $package, $key);

        $this->assertSame($purchase->id, $replay->id);
        $this->assertSame(PackagePurchase::PENDING, $purchase->fresh()->status);
        $this->assertSame(PackagePurchase::FUNDING_WORKING_WALLET, $purchase->funding_source);
        $this->assertSame('working', $purchase->funding_wallet);
        $this->assertSame(PackagePurchaseReservation::ACTIVE, $purchase->walletReservation->status);
        $this->assertSame('40.0000', (string) $purchase->walletReservation->reserved_amount);
        $this->assertSame('100.0000', (string) $member->fresh()->working_wallet_amount);
        $this->assertSame('60.0000', app(WorkingWalletFunds::class)->available($member->fresh()));
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_insufficient_funds_usd_package_and_invalid_wallet_are_rejected(): void
    {
        $member = $this->member('30.0000');
        $usdt = $this->package('WALLET-USDT-LOW', 'USDT', '40.0000');
        $usd = $this->package('WALLET-USD', 'USD', '10.0000');

        try {
            app(PackagePurchaseService::class)->submitWallet($member, $usdt, (string) Str::uuid());
            $this->fail('Insufficient funds must reject a wallet package request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet', $exception->errors());
        }

        try {
            app(PackagePurchaseService::class)->submitWallet($member, $usd, (string) Str::uuid());
            $this->fail('USD packages must not use the Working Wallet.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('package', $exception->errors());
        }

        $this->assertSame(0, PackagePurchase::query()->count());
        $this->assertSame(0, PackagePurchaseReservation::query()->count());
    }

    public function test_withdrawal_and_generic_holds_reduce_package_purchase_available_funds(): void
    {
        $member = $this->member('100.0000');
        WithdrawalRequest::query()->create([
            'reference' => 'WD-WALLET-PKG',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'source_wallet' => 'working',
            'amount' => '20.0000',
            'charge_amount' => '0.2000',
            'net_amount' => '19.8000',
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'receiving_address' => 'public-address',
            'status' => WithdrawalStatus::PENDING_REVIEW,
        ]);
        WalletLedgerEntry::query()->create([
            'wallet' => 'working',
            'direction' => WalletLedgerDirection::HOLD,
            'amount' => '10.0000',
            'opening_balance' => '100.0000',
            'closing_balance' => '100.0000',
            'denomination' => 'USDT',
            'member_id' => $member->member_id,
            'reference' => 'HOLD-GENERIC',
            'idempotency_key' => (string) Str::uuid(),
            'actor' => 'system',
            'reason' => 'Generic hold',
        ]);

        $this->assertSame('70.0000', app(WorkingWalletFunds::class)->available($member->fresh()));

        $package = $this->package('WALLET-75', 'USDT', '75.0000');
        try {
            app(PackagePurchaseService::class)->submitWallet($member, $package, (string) Str::uuid());
            $this->fail('A package must not exceed funds after withdrawal and hold reservations.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet', $exception->errors());
        }
    }

    public function test_multiple_package_reservations_repeat_and_approval_debits_once(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-REPEAT', 'USDT', '40.0000');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(PackagePurchaseService::class);

        $first = $service->submitWallet($member, $package, (string) Str::uuid());
        $second = $service->submitWallet($member, $package, (string) Str::uuid());

        $this->assertSame(2, PackagePurchase::query()->where('package_id', $package->id)->count());
        $this->assertSame('20.0000', app(WorkingWalletFunds::class)->available($member->fresh()));
        $this->assertSame(PackagePurchase::PENDING, $first->status);

        $invoice = $service->approve($first, $admin);
        $firstInvoiceReplay = $service->approve($first, $admin);

        $this->assertSame($invoice->id, $firstInvoiceReplay->id);
        $this->assertSame(PackagePurchase::APPROVED, $first->fresh()->status);
        $this->assertSame(PackagePurchaseReservation::SETTLED, $first->walletReservation->fresh()->status);
        $this->assertSame('60.0000', (string) $member->fresh()->working_wallet_amount);
        $this->assertSame('20.0000', app(WorkingWalletFunds::class)->available($member->fresh()));
        $this->assertSame(1, WalletLedgerEntry::query()->where('package_purchase_id', $first->id)->where('direction', WalletLedgerDirection::DEBIT)->count());

        $this->assertSame(0, \App\Models\RoiTransaction::query()->count());
        $this->assertSame(0, \App\Models\PackageRoiAccrual::query()->count());
        $this->assertSame(0, \App\Models\PackageLevelAccrual::query()->count());
    }

    public function test_rejection_releases_reservation_without_wallet_debit(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-REJECT', 'USDT', '25.0000');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(PackagePurchaseService::class);
        $purchase = $service->submitWallet($member, $package, (string) Str::uuid());

        $rejected = $service->reject($purchase, $admin, 'Receipt could not be confirmed.');
        $rejectedAgain = $service->reject($rejected, $admin, 'Repeated rejection.');

        $this->assertSame(PackagePurchase::REJECTED, $rejectedAgain->fresh()->status);
        $this->assertSame(PackagePurchaseReservation::RELEASED, $purchase->walletReservation->fresh()->status);
        $this->assertSame('100.0000', (string) $member->fresh()->working_wallet_amount);
        $this->assertSame('100.0000', app(WorkingWalletFunds::class)->available($member->fresh()));
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $this->assertSame(0, \App\Models\Invoice::query()->count());
    }

    public function test_wallet_approval_rolls_back_member_and_ledger_on_ledger_failure(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-ROLLBACK', 'USDT', '30.0000');
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(PackagePurchaseService::class);
        $purchase = $service->submitWallet($member, $package, (string) Str::uuid());

        WalletLedgerEntry::creating(function (): void {
            throw new RuntimeException('Simulated ledger failure.');
        });

        try {
            $service->approve($purchase, $admin);
            $this->fail('The approval should fail when ledger insertion fails.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ledger failure', $exception->getMessage());
        }

        $this->assertSame(PackagePurchase::PENDING, $purchase->fresh()->status);
        $this->assertSame(PackagePurchaseReservation::ACTIVE, $purchase->walletReservation->fresh()->status);
        $this->assertSame('100.0000', (string) $member->fresh()->working_wallet_amount);
        $this->assertSame(0, WalletLedgerEntry::query()->count());
    }

    public function test_package_reservation_blocks_transfers_and_withdrawal_available_funds(): void
    {
        $member = $this->member('100.0000');
        $receiver = $this->member('0.0000');
        $package = $this->package('WALLET-RESERVE-TRANSFER', 'USDT', '60.0000');
        app(PackagePurchaseService::class)->submitWallet($member, $package, (string) Str::uuid());

        MemberTransferSetting::current()->forceFill([
            'member_transfers_enabled' => true,
            'minimum_amount' => '1.0000',
            'maximum_amount' => '1000.0000',
            'daily_limit' => '5000.0000',
            'fee_amount' => '0.0000',
            'fee_type' => 'flat',
            'fee_value' => '0.0000',
        ])->save();

        try {
            app(MemberTransferWorkflow::class)->quote($member->fresh(), $receiver->member_id, '50.0000');
            $this->fail('Reserved package funds must not be transferable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame('40.0000', app(WithdrawalBalances::class)->available($member->fresh(), 'working'));
    }

    public function test_posting_controls_emergency_stop_and_admin_authorization_block_wallet_approval(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-GUARD', 'USDT', '20.0000');
        $purchase = app(PackagePurchaseService::class)->submitWallet($member, $package, (string) Str::uuid());
        $nonAdmin = User::factory()->create(['is_admin' => false]);

        try {
            app(PackagePurchaseService::class)->approve($purchase, $nonAdmin);
            $this->fail('A non-admin must not approve a wallet package.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('authorized administrator', $exception->getMessage());
        }

        config(['deltasupport.wallet_package_purchase.enabled' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        try {
            app(PackagePurchaseService::class)->approve($purchase, $admin);
            $this->fail('Package posting must fail while explicitly disabled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase posting is disabled', $exception->getMessage());
        }

        config(['deltasupport.wallet_package_purchase.enabled' => true]);
        FinancialControl::current()->forceFill(['financial_emergency_stop' => true])->save();
        try {
            app(PackagePurchaseService::class)->approve($purchase, $admin);
            $this->fail('The emergency stop must block wallet package posting.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('emergency stop', $exception->getMessage());
        }

        $this->assertSame(PackagePurchase::PENDING, $purchase->fresh()->status);
        $this->assertSame(PackagePurchaseReservation::ACTIVE, $purchase->walletReservation->fresh()->status);
        $this->assertSame('100.0000', (string) $member->fresh()->working_wallet_amount);
    }

    public function test_settled_reservation_is_append_only_and_cannot_be_released_or_deleted(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-IMMUTABLE', 'USDT', '10.0000');
        $admin = User::factory()->create(['is_admin' => true]);
        $purchase = app(PackagePurchaseService::class)->submitWallet($member, $package, (string) Str::uuid());
        app(PackagePurchaseService::class)->approve($purchase, $admin);
        $reservation = $purchase->walletReservation->fresh();

        try {
            $reservation->status = PackagePurchaseReservation::RELEASED;
            $reservation->save();
            $this->fail('Settled reservations must be immutable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('settled or released', $exception->getMessage());
        }

        try {
            $reservation->delete();
            $this->fail('Reservations must not be deleted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
    }

    public function test_approval_replay_rejects_missing_immutable_debit_ledger_entry(): void
    {
        $member = $this->member('100.0000');
        $package = $this->package('WALLET-REPLAY-GUARD', 'USDT', '10.0000');
        $admin = User::factory()->create(['is_admin' => true]);
        $purchase = app(PackagePurchaseService::class)->submitWallet($member, $package, (string) Str::uuid());
        app(PackagePurchaseService::class)->approve($purchase, $admin);

        DB::table('wallet_ledger_entries')->where('package_purchase_id', $purchase->id)->delete();

        try {
            app(PackagePurchaseService::class)->approve($purchase->fresh(), $admin);
            $this->fail('Approval replay must reject missing debit ledger state.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase', $exception->errors());
        }
    }

    private function member(string $working): Member
    {
        return Member::query()->create([
            'member_id' => 'STW'.strtoupper(Str::random(6)),
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Wallet Package Member',
            'mobile_no' => '9000000000',
            'email' => strtolower('w'.Str::random(8).'@example.test'),
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => $working,
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function package(string $code, string $denomination, string $price): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => 'Package '.$code,
            'package_type' => Package::TYPE_TOUR_ROI,
            'price_usd' => $price,
            'denomination' => $denomination,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
