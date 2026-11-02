<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\Invoice;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\PaymentDecision;
use App\Models\PaymentSetting;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageIncomePolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCustomerPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_record_a_cash_purchase_for_an_existing_customer(): void
    {
        Carbon::setTestNow('2026-11-02 09:30:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST440001', 'inactive');
        $member->update([
            'activation_wallet_amount' => '10.0000',
            'working_wallet_amount' => '20.0000',
            'roi_wallet_amount' => '30.0000',
        ]);
        $package = $this->package('CASH-1', '1500.2500', true);
        $hidden = $this->package('CASH-HIDDEN', '10.0000', false);
        $prior = $this->priorPurchase($member);
        $controls = FinancialControl::current()->only(FinancialControl::current()->getFillable());
        PaymentSetting::current()->forceFill(['is_enabled' => false, 'network' => 'BEP20'])->save();

        $this->actingAs($admin)
            ->get(route('admin.purchases.create'))
            ->assertOk()
            ->assertSeeText('Purchase for Customer')
            ->assertSeeText('Find Customer')
            ->assertSeeText('Customer details')
            ->assertSeeText('Record Purchase')
            ->assertSeeText('This customer already has an active purchase of this package.')
            ->assertSeeText('Cash')
            ->assertSeeText('Bank Transfer')
            ->assertSeeText('USDT')
            ->assertSeeText($package->name)
            ->assertDontSeeText($hidden->name);

        $key = (string) Str::uuid();
        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'member_id' => 'st440001',
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_CASH,
                'payment_reference' => 'RCPT-1001',
                'idempotency_key' => $key,
            ])
            ->assertRedirect();

        $purchase = PackagePurchase::query()->where('payment_reference', 'RCPT-1001')->firstOrFail();
        $invoice = Invoice::query()->where('package_purchase_id', $purchase->id)->firstOrFail();

        $this->assertSame(PackagePurchase::APPROVED, $purchase->status);
        $this->assertSame('Cash', $purchase->network);
        $this->assertSame(PackagePurchase::METHOD_CASH, $purchase->payment_method);
        $this->assertSame('1500.2500', $purchase->price_usd);
        $this->assertSame('USDT', $purchase->denomination);
        $this->assertSame($admin->id, $purchase->reviewed_by);
        $this->assertNotNull($purchase->reviewed_at);
        $this->assertSame($purchase->reviewed_at->toDateTimeString(), $invoice->approved_at->toDateTimeString());
        $this->assertSame(PackagePurchase::METHOD_CASH, $invoice->payment_method);
        $this->assertSame(1, PaymentDecision::query()->where('package_purchase_id', $purchase->id)->where('action', 'approved')->count());
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'customer_package_purchase')->where('subject', $purchase->reference)->count());
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame('10.0000', (string) $member->fresh()->activation_wallet_amount);
        $this->assertSame('20.0000', (string) $member->fresh()->working_wallet_amount);
        $this->assertSame('30.0000', (string) $member->fresh()->roi_wallet_amount);
        $this->assertFalse(PaymentSetting::current()->is_enabled);
        $this->assertSame($controls, FinancialControl::current()->only(array_keys($controls)));
        $this->assertFalse((bool) config('deltasupport.package_income.enabled'));
        $this->assertSame(0, PackageRoiAccrual::query()->count());
        $this->assertSame(0, PackageLevelAccrual::query()->count());
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());

        $prior->refresh();
        $this->assertSame('PRIOR-KEEP', $prior->payment_reference);
        $this->assertSame('2026-01-15 04:00:00', $prior->reviewed_at->format('Y-m-d H:i:s'));

        $policy = app(PackageIncomePolicy::class);
        $this->assertSame('2026-11-02', $policy->approvalDate($purchase)->toDateString());
        $this->assertSame('2026-11-12', $policy->firstAccrualDate($purchase)->toDateString());

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSeeText('Cash')
            ->assertSeeText('RCPT-1001');

        $this->actingAs($admin)
            ->get(route('admin.invoices.pdf', $invoice))
            ->assertOk()
            ->assertSee('Payment method Cash', false);

        $this->actingAs($admin)
            ->post(route('admin.purchases.store'), [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_CASH,
                'payment_reference' => 'RCPT-1001',
                'idempotency_key' => $key,
            ])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame(1, PackagePurchase::query()->where('payment_reference', 'RCPT-1001')->count());
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'customer_package_purchase')->count());

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertSeeText($package->name)
            ->assertSeeText('Cash')
            ->assertSeeText('Approved');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.invoices.index'))
            ->assertOk()
            ->assertSeeText($invoice->invoice_number);
    }

    public function test_bank_transfer_and_usdt_are_recorded_without_enabling_member_payments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST440002', 'active');
        $bankPackage = $this->package('PAY-BANK', '80.0000', true);
        $usdtPackage = $this->package('PAY-USDT', '90.0000', true);
        PaymentSetting::current()->forceFill([
            'currency_label' => 'USDT',
            'network' => 'BEP20',
            'is_enabled' => false,
        ])->save();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $bankPackage->id,
            'payment_method' => PackagePurchase::METHOD_BANK,
            'payment_reference' => 'NEFT-88921',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $usdtPackage->id,
            'payment_method' => PackagePurchase::METHOD_USDT,
            'payment_reference' => '0xabc123def4567890',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $bank = PackagePurchase::query()->where('payment_reference', 'NEFT-88921')->firstOrFail();
        $usdt = PackagePurchase::query()->where('payment_reference', '0xabc123def4567890')->firstOrFail();
        $this->assertSame('Bank Transfer', $bank->network);
        $this->assertSame(PackagePurchase::METHOD_BANK, $bank->payment_method);
        $this->assertSame('BEP20', $usdt->network);
        $this->assertSame('USDT', $usdt->currency_label);
        $this->assertSame(PackagePurchase::APPROVED, $bank->status);
        $this->assertSame(PackagePurchase::APPROVED, $usdt->status);
        $this->assertFalse(PaymentSetting::current()->is_enabled);
        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_an_active_package_cannot_be_purchased_again_and_a_different_package_can(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST440010', 'active');
        $package = $this->package('DUP-1', '50.0000', true);
        $other = $this->package('DUP-2', '60.0000', true);

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'payment_method' => PackagePurchase::METHOD_CASH,
            'payment_reference' => 'RCPT-3001',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $existing = PackagePurchase::query()->where('payment_reference', 'RCPT-3001')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('admin.purchases.preview', [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
            ]))
            ->assertOk()
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('message', 'This customer already has an active purchase of this package.')
            ->assertJsonPath('active_purchase.reference', $existing->reference)
            ->assertJsonPath('active_purchase.status', 'Approved')
            ->assertJsonPath('active_purchase.activation', 'Active')
            ->assertJsonPath('pending_purchase', null);

        $this->actingAs($admin)
            ->from(route('admin.purchases.create'))
            ->post(route('admin.purchases.store'), [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_BANK,
                'payment_reference' => 'NEFT-3002',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('admin.purchases.create'))
            ->assertSessionHasErrors([
                'package_id' => 'This customer already has an active purchase of this package.',
            ]);

        $this->assertSame(1, Invoice::query()->count());
        $this->assertDatabaseMissing('package_purchases', ['payment_reference' => 'NEFT-3002']);

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $other->id,
            'payment_method' => PackagePurchase::METHOD_CASH,
            'payment_reference' => 'RCPT-3003',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame(2, Invoice::query()->count());
        $this->assertSame(PackagePurchase::APPROVED, PackagePurchase::query()->where('package_id', $other->id)->value('status'));
    }

    public function test_a_pending_purchase_is_not_reported_as_active_and_can_still_be_recorded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST440011', 'inactive');
        $package = $this->package('PEND-1', '25.0000', true);
        $pending = PackagePurchase::query()->create([
            'reference' => 'PUR-PENDING-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'price_usd' => '25.0000',
            'denomination' => 'USDT',
            'network' => 'USDT',
            'payment_reference' => 'TX-PENDING-1',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => '2026-11-01 08:00:00',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.purchases.preview', [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
            ]))
            ->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('message', null)
            ->assertJsonPath('active_purchase', null)
            ->assertJsonPath('pending_purchase.reference', $pending->reference)
            ->assertJsonPath('pending_purchase.status', 'Pending')
            ->assertJsonPath('pending_purchase.activation', 'Not active');

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'payment_method' => PackagePurchase::METHOD_CASH,
            'payment_reference' => 'RCPT-PENDING',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $pending->refresh();
        $this->assertSame(PackagePurchase::PENDING, $pending->status);
        $this->assertSame(PackagePurchase::APPROVED, PackagePurchase::query()->where('payment_reference', 'RCPT-PENDING')->value('status'));
    }

    public function test_member_package_history_shows_real_statuses_without_an_invented_expiry(): void
    {
        $member = $this->member('ST440012', 'active');
        $package = $this->package('HIST-1', '15.0000', true);
        PackagePurchase::query()->create([
            'reference' => 'PUR-HIST-PENDING',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => 'Pending History',
            'price_usd' => '15.0000',
            'denomination' => 'USDT',
            'network' => 'USDT',
            'payment_reference' => 'TX-HIST-P',
            'status' => PackagePurchase::PENDING,
            'submitted_at' => '2026-11-01 08:00:00',
        ]);
        PackagePurchase::query()->create([
            'reference' => 'PUR-HIST-REJECTED',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => 'Rejected History',
            'price_usd' => '15.0000',
            'denomination' => 'USDT',
            'network' => 'USDT',
            'payment_reference' => 'TX-HIST-R',
            'status' => PackagePurchase::REJECTED,
            'rejection_reason' => 'Amount did not match.',
            'submitted_at' => '2026-10-01 08:00:00',
            'reviewed_at' => '2026-10-02 08:00:00',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertSeeText('Pending History')
            ->assertSeeText('Pending')
            ->assertSeeText('Not active')
            ->assertSeeText('Rejected History')
            ->assertSeeText('Rejected')
            ->assertSeeText('View Details')
            ->assertDontSeeText('Expiry date');
    }

    public function test_duplicate_unknown_and_unauthorized_purchases_are_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST440003', 'active');
        $package = $this->package('PAY-3', '40.0000', true);
        $other = $this->package('PAY-3B', '41.0000', true);
        $key = (string) Str::uuid();

        $this->actingAs($admin)->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'payment_method' => PackagePurchase::METHOD_CASH,
            'payment_reference' => 'RCPT-2002',
            'idempotency_key' => $key,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->from(route('admin.purchases.create'))
            ->post(route('admin.purchases.store'), [
                'member_id' => $member->member_id,
                'package_id' => $other->id,
                'payment_method' => PackagePurchase::METHOD_CASH,
                'payment_reference' => 'RCPT-2002',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('admin.purchases.create'))
            ->assertSessionHasErrors('payment_reference');

        $this->actingAs($admin)
            ->from(route('admin.purchases.create'))
            ->post(route('admin.purchases.store'), [
                'member_id' => 'ST999999',
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_CASH,
                'payment_reference' => 'RCPT-2003',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('member_id');

        $this->actingAs($admin)
            ->from(route('admin.purchases.create'))
            ->post(route('admin.purchases.store'), [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_BANK,
                'payment_reference' => 'OTHER-REF',
                'idempotency_key' => $key,
            ])
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, PackagePurchase::query()->where('member_id', $member->member_id)->count());

        auth()->logout();
        $this->post(route('admin.purchases.store'), [
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'payment_method' => PackagePurchase::METHOD_CASH,
            'payment_reference' => 'RCPT-2004',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.purchases.store'), [
                'member_id' => $member->member_id,
                'package_id' => $package->id,
                'payment_method' => PackagePurchase::METHOD_CASH,
                'payment_reference' => 'RCPT-2005',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('admin.purchases.member', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson(['found' => true, 'member_id' => $member->member_id]);

        $this->actingAs($admin)
            ->getJson(route('admin.purchases.member', ['member_id' => 'ST000000']))
            ->assertNotFound();
    }

    private function member(string $memberId, string $status): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Customer '.$memberId,
            'mobile_no' => '90000'.substr($memberId, -5),
            'email' => strtolower($memberId).'@example.test',
            'status' => $status,
        ]);
    }

    private function package(string $code, string $price, bool $active): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => 'Package '.$code,
            'price_usd' => $price,
            'denomination' => 'USDT',
            'package_type' => Package::TYPE_TOUR_ROI,
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => $active,
            'sort_order' => 1,
        ]);
    }

    private function priorPurchase(Member $member): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-PRIOR-1',
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $this->package('PRIOR', '5.0000', true)->id,
            'package_code' => 'PRIOR',
            'package_name' => 'Earlier Package',
            'price_usd' => '5.0000',
            'denomination' => 'USDT',
            'network' => 'Cash',
            'payment_reference' => 'PRIOR-KEEP',
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => '2026-01-15 04:00:00',
            'reviewed_at' => '2026-01-15 04:00:00',
        ]);
    }
}
