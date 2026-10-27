<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\RoiTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->enablePayments();

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
        $this->enablePayments();

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

    public function test_package_form_validates_price_type_and_image_and_requires_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $memberUser = User::factory()->create(['is_admin' => false]);

        $this->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'price_usd' => '1.0000',
            'sort_order' => 1,
        ])->assertRedirect(route('login'));

        $this->actingAs($memberUser)->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'price_usd' => '1.0000',
            'sort_order' => 1,
        ])->assertForbidden();

        $this->actingAs($admin)->from(route('admin.packages.create'))->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'package_type' => 'tour',
            'price_usd' => '15.25999',
            'sort_order' => 1,
        ])->assertSessionHasErrors('price_usd');

        $this->actingAs($admin)->from(route('admin.packages.create'))->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'package_type' => 'unknown',
            'price_usd' => '15.2500',
            'sort_order' => 1,
        ])->assertSessionHasErrors('package_type');

        Storage::fake('local');
        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'package_type' => Package::TYPE_PREMIUM,
            'price_usd' => '15.2500',
            'sort_order' => 4,
            'is_active' => '1',
            'destinations' => 'Draft destinations',
            'duration' => 'Draft duration',
            'image' => UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('image');
        $this->assertSame(0, Package::query()->count());

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'PKG-SECURE',
            'name' => 'Closed Package',
            'package_type' => Package::TYPE_PREMIUM,
            'price_usd' => '15.2500',
            'sort_order' => 4,
            'is_active' => '1',
            'destinations' => 'Draft destinations',
            'duration' => 'Draft duration',
            'image' => UploadedFile::fake()->image('cover.jpg', 40, 40),
        ])->assertRedirect(route('admin.packages.index'));

        $package = Package::query()->firstOrFail();
        $this->assertSame('15.2500', $package->price_usd);
        $this->assertSame(Package::TYPE_PREMIUM, $package->package_type);
        $this->assertTrue($package->roi_eligible);
        $this->assertTrue($package->level_commission_eligible);
        $this->assertNotNull($package->image_path);
        Storage::disk('local')->assertExists($package->image_path);
        $this->assertSame(0, RoiTransaction::query()->count());

        $this->get(route('public.packages.image', $package))->assertOk();
        $this->actingAs($admin)->patch(route('admin.packages.status', $package), ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($package->fresh()->is_active);
        $this->get(route('public.packages.image', $package))->assertNotFound();
        $this->get(route('public.packages'))->assertOk()->assertDontSeeText('Closed Package')->assertDontSee('₹', false);
    }

    public function test_purchases_keep_snapshots_and_stay_isolated_while_pending(): void
    {
        $owner = $this->member('STPKG0010');
        $other = $this->member('STPKG0011', 'other-package@example.test');
        $first = Package::query()->create([
            'code' => 'PKG-A',
            'package_type' => Package::TYPE_TOUR,
            'name' => 'First Tour',
            'price_usd' => '40.0000',
            'destinations' => 'Original destination',
            'duration' => '2 days / 1 night',
            'benefits' => 'Original benefit',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $second = Package::query()->create([
            'code' => 'PKG-B',
            'package_type' => Package::TYPE_TOUR_ROI,
            'name' => 'Second Tour',
            'price_usd' => '80.5000',
            'roi_eligible' => true,
            'level_commission_eligible' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->enablePayments();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $first), [
                'payment_reference' => 'OWNREF100001',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $first), [
                'payment_reference' => 'OWNREF100002',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $second), [
                'payment_reference' => 'OWNREF100003',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $this->assertSame(3, PackagePurchase::query()->where('member_id', $owner->member_id)->count());
        $this->assertSame(3, PackagePurchase::query()->where('status', PackagePurchase::PENDING)->count());
        $this->assertSame(0, RoiTransaction::query()->count());

        $first->update([
            'price_usd' => '70.0000',
            'destinations' => 'Changed destination',
            'benefits' => 'Changed benefit',
        ]);

        $original = PackagePurchase::query()->where('payment_reference', 'OWNREF100001')->firstOrFail();
        $this->assertSame('40.0000', $original->price_usd);
        $this->assertSame('Original destination', $original->destinations);
        $this->assertSame('Original benefit', $original->benefits);
        $this->assertSame('70.0000', $first->fresh()->price_usd);

        $first->update(['is_active' => false]);
        $this->withSession(['member_context_id' => $owner->member_id])
            ->from(route('member.packages.explore'))
            ->post(route('member.packages.purchase', $first), [
                'payment_reference' => 'OWNREF100004',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('package');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertSeeText($original->reference)
            ->assertSeeText('Pending Verification')
            ->assertSeeText('USD 40.0000');

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertDontSeeText($original->reference);

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.payments.show', $original))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.tours.index'))
            ->assertRedirect(route('admin.packages.index'));
        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.tours'))
            ->assertRedirect(route('member.packages.mine'));
    }

    public function test_members_can_browse_active_packages_while_payment_is_unavailable(): void
    {
        $member = $this->member('STPKG0020', 'browse@example.test');
        $visible = Package::query()->create([
            'code' => 'PKG-OPEN',
            'name' => 'Visible Tour',
            'price_usd' => '25.5000',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Package::query()->create([
            'code' => 'PKG-CLOSED',
            'name' => 'Hidden Tour',
            'price_usd' => '90.0000',
            'is_active' => false,
            'sort_order' => 2,
        ]);
        $this->assertFalse(PaymentSetting::current()->isReady());

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.packages.explore'))
            ->assertOk()
            ->assertSeeText('Visible Tour')
            ->assertSeeText('USD 25.5000')
            ->assertSeeText('Payment Currently Unavailable')
            ->assertDontSeeText('Hidden Tour');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.packages.show', $visible))
            ->assertOk()
            ->assertSeeText('Payment Currently Unavailable')
            ->assertDontSeeText('Submit for verification');

        $this->withSession(['member_context_id' => $member->member_id])
            ->from(route('member.packages.show', $visible))
            ->post(route('member.packages.purchase', $visible), [
                'payment_reference' => 'UNAVAILABLE1',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('package');

        $this->assertSame(0, PackagePurchase::query()->count());
        $this->flushSession();
        $this->get(route('member.packages.show', $visible))->assertForbidden();
    }

    public function test_payment_settings_and_manual_activation_stay_admin_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $memberUser = User::factory()->create(['is_admin' => false]);
        $owner = $this->member('STPKG0021', 'payer@example.test');
        $stranger = $this->member('STPKG0022', 'stranger@example.test');
        $package = Package::query()->create([
            'code' => 'PKG-PAY',
            'name' => 'Payable Tour',
            'price_usd' => '60.2500',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->put(route('admin.payments.settings.update'), [
            'currency_label' => 'Configured asset',
            'network' => 'Configured chain',
            'receiving_address' => 'public-receiving-address',
            'instructions' => 'Send the USD amount and submit the transaction ID.',
            'is_enabled' => '1',
        ])->assertRedirect(route('login'));

        $this->actingAs($memberUser)->put(route('admin.payments.settings.update'), [
            'currency_label' => 'Configured asset',
            'network' => 'Configured chain',
            'instructions' => 'Send the USD amount and submit the transaction ID.',
            'is_enabled' => '1',
        ])->assertForbidden();

        $this->actingAs($admin)->from(route('admin.payments.settings'))->put(route('admin.payments.settings.update'), [
            'currency_label' => 'Configured asset',
            'network' => 'Configured chain',
            'receiving_address' => 'public-receiving-address',
            'instructions' => 'Send the USD amount and submit the transaction ID.',
            'is_enabled' => '1',
        ])->assertRedirect(route('admin.payments.settings'))->assertSessionHasErrors('network');

        $settings = PaymentSetting::current()->fresh();
        $this->assertFalse($settings->is_enabled);

        $this->actingAs($admin)->put(route('admin.payments.settings.update'), [
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'instructions' => \App\Models\PaymentSetting::USDT_INSTRUCTION,
        ])->assertRedirect(route('admin.payments.settings'));
        $this->assertFalse(PaymentSetting::current()->fresh()->is_enabled);
        $this->enablePayments();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->from(route('member.packages.show', $package))
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => '  short  ',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('payment_reference');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => '  TXID100001  ',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $purchase = PackagePurchase::query()->firstOrFail();
        $this->assertSame(PackagePurchase::PENDING, $purchase->status);
        $this->assertSame('TXID100001', $purchase->payment_reference);
        $this->assertSame('60.2500', $purchase->price_usd);

        $this->withSession(['member_context_id' => $stranger->member_id])
            ->from(route('member.packages.show', $package))
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'TXID100001',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasErrors('payment_reference');

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.payments.index'))
            ->assertOk()
            ->assertSeeText('Pending Verification')
            ->assertSeeText('60.2500');

        $this->actingAs($admin)
            ->get(route('admin.payments.show', $purchase))
            ->assertOk()
            ->assertSeeText('TXID100001')
            ->assertSeeText('USD 60.2500')
            ->assertSeeText($owner->member_id);

        auth()->logout();
        $this->withSession(['member_context_id' => $stranger->member_id])
            ->get(route('admin.payments.show', $purchase))
            ->assertRedirect(route('login'));

        $package->update(['price_usd' => '500.0000']);
        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertRedirect();
        $this->actingAs($otherAdmin)->post(route('admin.payments.approve', $purchase))->assertRedirect();

        $purchase->refresh();
        $this->assertSame(PackagePurchase::APPROVED, $purchase->status);
        $this->assertSame('60.2500', $purchase->price_usd);
        $this->assertSame($admin->id, $purchase->reviewed_by);
        $this->assertNotNull($purchase->reviewed_at);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(0, RoiTransaction::query()->count());

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertSeeText('Active')
            ->assertDontSeeText('Pending Verification');

        $this->withSession(['member_context_id' => $stranger->member_id])
            ->get(route('member.packages.mine'))
            ->assertOk()
            ->assertDontSeeText('TXID100001')
            ->assertDontSeeText($purchase->reference);
    }

    public function test_approved_invoice_is_unique_and_keeps_its_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = $this->member('STPKG0030', 'invoice-owner@example.test');
        $other = $this->member('STPKG0031', 'invoice-other@example.test');
        $package = Package::query()->create([
            'code' => 'PKG-INV',
            'name' => 'Invoice Tour',
            'price_usd' => '33.2500',
            'tour_title' => 'Saved tour',
            'benefits' => 'Saved benefit',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        CompanySetting::current()->update([
            'company_name' => 'Snapshot Company',
            'address' => 'Snapshot address',
            'email' => 'accounts@example.test',
            'phone' => '9000002222',
        ]);
        $this->enablePayments();

        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'INVTX100001',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $purchase = PackagePurchase::query()->firstOrFail();
        $this->assertSame(0, Invoice::query()->count());

        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertRedirect();
        $this->assertSame(1, Invoice::query()->count());

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('33.2500', $invoice->price_usd);
        $this->assertSame('PKG-INV', $invoice->package_code);
        $this->assertSame($purchase->reference, $invoice->purchase_reference);
        $this->assertStringContainsString('Saved benefit', (string) $invoice->package_details);
        $this->assertSame('Snapshot Company', $invoice->company_name);
        $this->assertSame('Package Member', $invoice->member_name);

        $package->update(['name' => 'Renamed Tour', 'price_usd' => '90.0000', 'benefits' => 'Changed benefit']);
        $owner->update(['member_name' => 'Changed Name']);
        CompanySetting::current()->update(['company_name' => 'Changed Company', 'address' => 'Changed address']);
        $invoice->refresh();
        $this->assertSame('Invoice Tour', $invoice->package_name);
        $this->assertSame('33.2500', $invoice->price_usd);
        $this->assertStringContainsString('Saved benefit', (string) $invoice->package_details);
        $this->assertSame('Snapshot Company', $invoice->company_name);
        $this->assertSame('Package Member', $invoice->member_name);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index', ['q' => $invoice->invoice_number, 'status' => 'approved']))
            ->assertOk()
            ->assertSeeText($invoice->invoice_number)
            ->assertSeeText('USD 33.2500');

        $this->actingAs($admin)
            ->get(route('admin.invoices.index', ['from' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertDontSeeText($invoice->invoice_number);

        $this->actingAs($admin)->get(route('admin.invoices.print', $invoice))->assertOk()->assertSeeText('Print')->assertSeeText('Saved benefit');

        $pdf = $this->actingAs($admin)->get(route('admin.invoices.pdf', $invoice));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString($invoice->invoice_number, $pdf->getContent());
        $this->assertStringContainsString('33.2500', $pdf->getContent());

        $this->withSession(['member_context_id' => $owner->member_id])
            ->get(route('member.invoices.print', $invoice))
            ->assertOk()
            ->assertSeeText($invoice->invoice_number);

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.invoices.print', $invoice))
            ->assertForbidden();

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.invoices.show', $invoice))
            ->assertForbidden();

        $rejectedPackage = Package::query()->create([
            'code' => 'PKG-NOINV',
            'name' => 'Unpaid Tour',
            'price_usd' => '12.0000',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->withSession(['member_context_id' => $owner->member_id])
            ->post(route('member.packages.purchase', $rejectedPackage), [
                'payment_reference' => 'INVTX100002',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $rejected = PackagePurchase::query()->where('payment_reference', 'INVTX100002')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.reject', $rejected), ['reason' => 'Not matched.'])->assertRedirect();
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(0, RoiTransaction::query()->count());
    }

    private function enablePayments(): void
    {
        PaymentSetting::current()->update([
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'qr_image_path' => 'payment-qr/example.webp',
            'instructions' => \App\Models\PaymentSetting::USDT_INSTRUCTION,
            'is_enabled' => true,
        ]);
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
