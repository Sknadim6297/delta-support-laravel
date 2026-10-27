<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\Rank;
use App\Models\RoiTransaction;
use App\Models\User;
use App\Support\FutureWalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsdtStandardizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_packages_purchases_and_invoices_are_usdt_and_historical_usd_stays_usd(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('STUSDT01');

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'code' => 'USDT-TOUR',
            'name' => 'USDT Tour',
            'package_type' => Package::TYPE_TOUR,
            'price_usd' => '12.5000',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $package = Package::query()->where('code', 'USDT-TOUR')->firstOrFail();
        $this->assertSame('USDT', $package->denomination);
        $this->assertSame('12.5000', $package->price_usd);

        $this->actingAs($admin)->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSeeText('USDT 12.5000')
            ->assertDontSee('$');

        PaymentSetting::current()->update([
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'qr_image_path' => 'payment-qr/example.webp',
            'instructions' => \App\Models\PaymentSetting::USDT_INSTRUCTION,
            'is_enabled' => true,
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'USDT-TX-1',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $purchase = PackagePurchase::query()->where('payment_reference', 'USDT-TX-1')->firstOrFail();
        $this->assertSame('USDT', $purchase->denomination);
        $this->assertSame('12.5000', $purchase->price_usd);
        $this->assertSame(PackagePurchase::PENDING, $purchase->status);

        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertRedirect();
        $this->assertSame(1, Invoice::query()->where('package_purchase_id', $purchase->id)->count());
        $this->assertSame(0, RoiTransaction::query()->count());

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('USDT', $invoice->denomination);
        $this->assertSame('12.5000', $invoice->price_usd);

        $historical = Package::query()->create([
            'code' => 'USD-OLD',
            'name' => 'Historical Tour',
            'price_usd' => '1.0000',
            'denomination' => 'USD',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.packages.purchase', $historical), [
                'payment_reference' => 'USD-TX-1',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $oldPurchase = PackagePurchase::query()->where('payment_reference', 'USD-TX-1')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.approve', $oldPurchase))->assertRedirect();
        $oldInvoice = Invoice::query()->where('package_purchase_id', $oldPurchase->id)->firstOrFail();
        $this->assertSame('USD', $oldPurchase->denomination);
        $this->assertSame('1.0000', $oldPurchase->price_usd);
        $this->assertSame('USD', $oldInvoice->denomination);
        $this->assertSame('1.0000', $oldInvoice->price_usd);

        $this->actingAs($admin)->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSeeText('USDT 12.5000')
            ->assertSeeText('USD 1.0000')
            ->assertDontSeeText('USDT 13.5000')
            ->assertDontSeeText('USD 13.5000');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('USDT 12.5000')
            ->assertSeeText('USD 1.0000')
            ->assertDontSeeText('USDT 13.5000');
    }

    public function test_payment_settings_name_usdt_without_assuming_a_network(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.payments.settings'))
            ->assertOk()
            ->assertSee('value="USDT"', false)
            ->assertSee('None is assumed.', false)
            ->assertSeeText('does not verify the blockchain')
            ->assertSeeText('private key');
    }

    public function test_incomplete_or_placeholder_payment_settings_cannot_enable_submissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.payments.settings'))
            ->put(route('admin.payments.settings.update'), [
                'currency_label' => 'USDT',
                'network' => 'Configured network',
                'receiving_address' => 'delta-support-public-address',
                'instructions' => PaymentSetting::USDT_INSTRUCTION,
                'is_enabled' => '1',
            ])->assertRedirect(route('admin.payments.settings'))
            ->assertSessionHasErrors('network');
        $this->assertFalse(PaymentSetting::current()->fresh()->is_enabled);

        $this->actingAs($admin)->put(route('admin.payments.settings.update'), [
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'instructions' => PaymentSetting::USDT_INSTRUCTION,
        ])->assertRedirect(route('admin.payments.settings'));

        $saved = PaymentSetting::current()->fresh();
        $this->assertFalse($saved->is_enabled);
        $this->assertSame(PaymentSetting::USDT_INSTRUCTION, $saved->instructions);
        $this->assertFalse($saved->isReady());

        $this->actingAs($admin)->from(route('admin.payments.settings'))
            ->put(route('admin.payments.settings.update'), [
                'currency_label' => 'USDT',
                'network' => 'Admin selected network',
                'receiving_address' => 'wallet private key phrase',
                'instructions' => PaymentSetting::USDT_INSTRUCTION,
            ])->assertSessionHasErrors('receiving_address');
        $this->assertSame('delta-support-public-address', PaymentSetting::current()->fresh()->receiving_address);
    }

    public function test_admin_member_preview_does_not_save_rank_or_wallets(): void
    {
        $rankId = Rank::query()->value('id');
        $member = $this->member('STUSDT02');
        $member->forceFill([
            'rank_id' => $rankId,
            'roi_wallet_amount' => '4.0000',
            'working_wallet_amount' => '5.0000',
            'activation_wallet_amount' => '6.0000',
        ])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.member-panel', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('USDT 4.0000');

        $member->refresh();
        $this->assertSame($rankId, $member->rank_id);
        $this->assertSame('4.0000', $member->roi_wallet_amount);
        $this->assertSame('5.0000', $member->working_wallet_amount);
        $this->assertSame('6.0000', $member->activation_wallet_amount);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertContains('idempotency_key', FutureWalletLedger::columns());
        $this->assertContains('denomination', FutureWalletLedger::columns());
    }

    private function member(string $memberId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'USDT Member',
            'mobile_no' => '90000'.substr(preg_replace('/\D/', '', $memberId).'00000', 0, 5),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
        ]);
    }
}
