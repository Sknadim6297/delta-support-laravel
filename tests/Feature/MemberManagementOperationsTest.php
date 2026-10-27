<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\RoiTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberManagementOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_lists_search_paginate_and_hide_passwords(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inactive = $this->member('ST300001', 'Inactive One', 'inactive-one@example.test', 'inactive');
        $active = $this->member('ST300002', 'Active One', 'active-one@example.test', 'active');

        foreach (range(3, 32) as $index) {
            $this->member(
                'ST30' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'Inactive ' . $index,
                'inactive' . $index . '@example.test',
                'inactive'
            );
        }

        $this->actingAs($admin)
            ->get(route('admin.members.inactive', ['member_name' => 'inactive-one@example.test']))
            ->assertOk()
            ->assertSeeText('Inactive Member List')
            ->assertSeeText($inactive->member_id)
            ->assertSeeText('Unpaid')
            ->assertSeeText('View / Edit')
            ->assertDontSeeText($active->member_id)
            ->assertDontSee('password-toggle', false)
            ->assertDontSee($inactive->password, false);

        $this->actingAs($admin)
            ->get(route('admin.members.inactive', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('Showing 31 to 31 of 31 entries');

        $this->actingAs($admin)
            ->get(route('admin.members.active'))
            ->assertOk()
            ->assertSeeText($active->member_id)
            ->assertDontSeeText('No active members found.');

        $this->actingAs($admin)
            ->get(route('admin.members.active', ['member_id' => $active->member_id]))
            ->assertOk()
            ->assertSeeText('Active One')
            ->assertDontSeeText($inactive->member_id);

        $this->actingAs($admin)
            ->get(route('admin.members.directory', ['q' => $inactive->email, 'status' => 'inactive']))
            ->assertOk()
            ->assertSeeText($inactive->member_id)
            ->assertSeeText('Unpaid')
            ->assertDontSeeText($active->member_name);

        auth()->logout();

        $this->get(route('admin.members.inactive'))->assertRedirect(route('login'));
    }

    public function test_profile_update_preserves_sponsor_wallets_and_reloads_the_member(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST300100', 'Original Name', 'original@example.test', 'inactive');
        $member->update([
            'activation_wallet_amount' => '1.2500',
            'working_wallet_amount' => '2.2500',
            'roi_wallet_amount' => '3.2500',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members.update', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSee('value="ST300100"', false);

        $this->actingAs($admin)
            ->post(route('admin.members.update-member'), [
                'member_id' => $member->member_id,
                'sponsor_id' => 'ST999999',
                'sponsor_name' => 'Forged Sponsor',
                'member_name' => 'Updated Name',
                'wallet_address' => 'qa-wallet',
                'mobile_no' => '9000000100',
                'pan_card_no' => 'ABCDE1234Z',
                'email' => 'updated@example.test',
            ])
            ->assertRedirect(route('admin.members.update', ['member_id' => $member->member_id]));

        $member->refresh();
        $this->assertSame('Updated Name', $member->member_name);
        $this->assertSame('ST666666', $member->sponsor_id);
        $this->assertSame('Admin', $member->sponsor_name);
        $this->assertSame('1.2500', (string) $member->activation_wallet_amount);
        $this->assertSame('2.2500', (string) $member->working_wallet_amount);
        $this->assertSame('3.2500', (string) $member->roi_wallet_amount);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, DB::table('wallet_ledger_entries')->count());

        $this->actingAs($admin)
            ->from(route('admin.members.update'))
            ->post(route('admin.members.update-member'), [
                'member_id' => $member->member_id,
                'member_name' => 'X',
                'mobile_no' => '9000000100',
                'pan_card_no' => 'ABCDE1234Z',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['member_name', 'email']);
    }

    public function test_package_approval_activates_once_and_pending_or_rejected_purchases_do_not(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST300200', 'Package Person', 'package-person@example.test', 'inactive');
        $member->update([
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
        ]);
        $package = Package::query()->create([
            'code' => 'PKG-QA',
            'name' => 'QA Package',
            'price_usd' => '25.0000',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PaymentSetting::current()->update([
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'qr_image_path' => 'payment-qr/example.webp',
            'instructions' => PaymentSetting::USDT_INSTRUCTION,
            'is_enabled' => true,
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'QAREF100001',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $this->assertSame('inactive', $member->fresh()->status);
        $this->actingAs($admin)
            ->get(route('admin.members.inactive', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Pending package');

        $purchase = PackagePurchase::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.reject', $purchase), [
            'reason' => 'Reference could not be matched.',
        ])->assertRedirect();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertSame(0, Invoice::query()->count());

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.packages.purchase', $package), [
                'payment_reference' => 'QAREF100002',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();

        $approved = PackagePurchase::query()->where('payment_reference', 'QAREF100002')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.approve', $approved))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payments.approve', $approved))->assertRedirect();

        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame(1, Member::query()->where('email', 'package-person@example.test')->count());
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
        $this->assertSame('0.0000', (string) $member->fresh()->roi_wallet_amount);

        $this->actingAs($admin)
            ->get(route('admin.members.active', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Approved package')
            ->assertSeeText($member->member_id);

        $this->actingAs($admin)
            ->get(route('admin.members.inactive', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertDontSeeText($member->member_name);
    }

    private function member(string $memberId, string $name, string $email, string $status): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '91' . substr(preg_replace('/\D/', '', $memberId), -8),
            'pan_card_no' => 'QA' . substr($memberId, -8),
            'email' => $email,
            'password' => bcrypt('secret-password'),
            'status' => $status,
        ]);
    }
}
