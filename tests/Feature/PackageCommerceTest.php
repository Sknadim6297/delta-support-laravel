<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\RoiTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_package_crud_keeps_usd_precision_and_hides_inactive_packages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'pkg-1',
            'name' => 'Tour Package One',
            'price_usd' => '1500.2500',
            'description' => 'Tour benefits',
            'tour_title' => 'City tour',
            'tour_details' => 'Saved tour details',
            'duration' => '3 days',
            'benefits' => 'Hotel stay',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $package = Package::query()->firstOrFail();
        $this->assertSame('PKG-1', $package->code);
        $this->assertSame('1500.2500', $package->price_usd);

        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'code' => 'PKG-1',
            'name' => 'Tour Package One',
            'price_usd' => '10.1000',
            'sort_order' => 1,
        ])->assertRedirect(route('admin.packages.index'));

        $this->assertSame('10.1000', $package->fresh()->price_usd);
        $this->assertFalse($package->fresh()->is_active);

        $member = $this->member('STPKG0001');
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.packages.explore'))
            ->assertOk()
            ->assertDontSeeText('Tour Package One');
    }

    public function test_manual_payment_approval_is_idempotent_and_isolated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->member('STPKG0002');
        $other = $this->member('STPKG0003', 'other@example.test');
        $package = Package::query()->create([
            'code' => 'PKG-2',
            'name' => 'Repeatable Tour',
            'price_usd' => '250.5000',
            'tour_title' => 'Coast tour',
            'tour_details' => 'Coast details',
            'duration' => '5 days',
            'benefits' => 'Transfers',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        PaymentSetting::current()->update([
            'currency_label' => 'Configured token',
            'network' => 'Configured network',
            'instructions' => 'Pay the package amount and submit the transaction reference.',
            'is_enabled' => true,
        ]);

        $key = (string) Str::uuid();
        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'TXREF123456',
                'idempotency_key' => $key,
            ])->assertRedirect();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'TXREF123456',
                'idempotency_key' => $key,
            ])->assertRedirect();

        $this->assertSame(1, PackagePurchase::query()->count());
        $purchase = PackagePurchase::query()->firstOrFail();
        $this->assertSame(PackagePurchase::PENDING, $purchase->status);
        $this->assertSame('250.5000', $purchase->price_usd);

        $this->withSession(['member_context_id' => $owner->member_id])
            ->from(route('member.packages.show', $package))
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'TXREF123456',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('payment_reference');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->from(route('member.packages.show', $package))
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'bad',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('payment_reference');

        $package->update(['price_usd' => '999.0000']);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $purchase))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $purchase))
            ->assertRedirect();

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(0, RoiTransaction::query()->count());
        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('250.5000', $invoice->price_usd);
        $this->assertSame('250.5000', $purchase->fresh()->price_usd);
        $this->assertSame(PackagePurchase::APPROVED, $purchase->fresh()->status);

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.invoices.show', $invoice))
            ->assertForbidden();

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.invoices.pdf', $invoice))
            ->assertForbidden();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        auth()->logout();
        $this->get(route('admin.invoices.pdf', $invoice))->assertRedirect(route('login'));

        $second = $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'TXREF654321',
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $second->assertRedirect();
        $this->assertSame(2, PackagePurchase::query()->count());
        $this->assertSame('999.0000', PackagePurchase::query()->where('payment_reference', 'TXREF654321')->firstOrFail()->price_usd);
    }

    public function test_rejection_records_a_reason_and_does_not_create_an_invoice(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STPKG0004');
        $package = Package::query()->create([
            'code' => 'PKG-3',
            'name' => 'Review Tour',
            'price_usd' => '80.0000',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        PaymentSetting::current()->update([
            'currency_label' => 'Configured token',
            'network' => 'Configured network',
            'instructions' => 'Submit the reference for manual review.',
            'is_enabled' => true,
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'REJECT12345',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $purchase = PackagePurchase::query()->firstOrFail();
        $this->actingAs($admin)
            ->from(route('admin.payments.index', ['status' => 'pending']))
            ->post(route('admin.payments.reject', $purchase), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.payments.reject', $purchase), ['reason' => 'Reference could not be matched.'])
            ->assertRedirect(route('admin.payments.index', ['status' => 'rejected']));

        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(PackagePurchase::REJECTED, $purchase->fresh()->status);
    }

    public function test_admin_can_activate_a_member_without_changing_wallet_balances(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STPKG0006');
        $member->update([
            'status' => 'inactive',
            'roi_wallet_amount' => '4.5000',
            'working_wallet_amount' => '3.2500',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.members.status', $member->member_id), ['status' => 'active'])
            ->assertRedirect();

        $member->refresh();
        $this->assertSame('active', $member->status);
        $this->assertSame('4.5000', $member->roi_wallet_amount);
        $this->assertSame('3.2500', $member->working_wallet_amount);
    }

    public function test_new_navigation_routes_load_and_legacy_money_posts_stay_closed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STPKG0005');

        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.payments.settings'))->assertOk();
        $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.wallets.show', ['wallet' => 'roi']))->assertOk()->assertSeeText('not enabled');
        $this->actingAs($admin)->get(route('admin.catalog-reports.show', ['report' => 'earnings']))->assertOk()->assertSeeText('not calculated');
        $this->actingAs($admin)->get(route('admin.coming-soon', ['feature' => 'roi-plans']))->assertOk()->assertSeeText('Coming Soon');

        $this->withSession(['member_context_id' => $member->member_id])->get(route('member.referral'))->assertOk()->assertSeeText($member->member_id);
        $this->withSession(['member_context_id' => $member->member_id])->get(route('member.support'))->assertOk();
        $this->withSession(['member_context_id' => $member->member_id])->get(route('member.coming-soon', ['feature' => 'request-withdrawal']))->assertOk()->assertSeeText('Coming Soon');

        config(['deltasupport.legacy_financial_actions' => false]);
        $this->actingAs($admin)->post(route('admin.investments.store'), [])->assertForbidden();
    }

    private function member(string $memberId, string $email = 'owner@example.test'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Package Member',
            'mobile_no' => '9000000099',
            'email' => $email,
            'status' => 'active',
        ]);
    }
}
