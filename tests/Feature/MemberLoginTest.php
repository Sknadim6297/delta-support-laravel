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
use App\Services\PackagePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_and_inactive_members_log_in_with_member_id_only(): void
    {
        $active = $this->member('ST810001', 'active-login@example.test', 'active', 'active-secret');
        $inactive = $this->member('ST810002', 'inactive-login@example.test', 'inactive', 'inactive-secret');

        $this->get(route('public.login'))
            ->assertOk()
            ->assertSee('Member ID', false)
            ->assertSee('Enter your Member ID (STxxxxxx)', false)
            ->assertSee('Login', false)
            ->assertDontSee('Email Address / Member ID', false);

        $this->get(route('public.login'));
        $sessionId = session()->getId();

        $this->post(route('member.login.store'), [
            'member_id' => '  st810001  ',
            'password' => 'active-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertSame($active->member_id, session('member_context_id'));
        $this->assertSame('active', $active->fresh()->status);

        $this->post(route('member.logout'))->assertRedirect(route('login'));
        $this->assertNull(session('member_context_id'));

        $this->post(route('member.login.store'), [
            'member_id' => $inactive->member_id,
            'password' => 'inactive-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertSame('inactive', $inactive->fresh()->status);
        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Account Inactive — No Active Package')
            ->assertSeeText("Your account is currently inactive because you don't have an active package. Buy a package to unlock eligible benefits and start your membership journey.", false)
            ->assertSeeText('Buy a Package')
            ->assertSeeText('Explore Packages');
        $this->get(route('member.packages.mine'))->assertOk();
        $this->get(route('member.payments.index'))->assertOk();
        $this->get(route('member.invoices.index'))->assertOk();
        $this->get(route('member.profile'))->assertOk();
        $this->get(route('member.referral'))->assertOk();
        $this->get(route('member.change-password'))->assertOk();
    }

    public function test_inactive_member_can_browse_and_submit_a_package_without_activation(): void
    {
        $member = $this->member('ST810003', 'buyer-login@example.test', 'inactive', 'buyer-secret');
        $package = Package::query()->create([
            'code' => 'PKG-LOGIN',
            'package_type' => Package::TYPE_TOUR,
            'name' => 'Login Tour',
            'price_usd' => '25.0000',
            'denomination' => 'USDT',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->post(route('member.login.store'), [
            'member_id' => $member->member_id,
            'password' => 'buyer-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->get(route('member.packages.explore'))
            ->assertOk()
            ->assertSeeText('Login Tour')
            ->assertSeeText('Payment Currently Unavailable');

        $this->enablePayments();

        $this->post(route('member.packages.purchase', $package), [
            'payment_reference' => 'LOGINREF1001',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $member->refresh();
        $purchase = PackagePurchase::query()->firstOrFail();
        $this->assertSame('inactive', $member->status);
        $this->assertSame(PackagePurchase::PENDING, $purchase->status);
        $this->assertSame(0, Invoice::query()->count());

        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(PackagePurchaseService::class);
        $invoice = $service->approve($purchase, $admin);
        $repeat = $service->approve($purchase->fresh(), $admin);

        $this->assertTrue($invoice->is($repeat));
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Account Active')
            ->assertSeeText('Login Tour')
            ->assertSeeText('Opening this dashboard does not credit ROI or level income.');
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
    }

    public function test_invalid_credentials_email_login_and_blocked_status_are_rejected(): void
    {
        $member = $this->member('ST810004', 'reject-login@example.test', 'inactive', 'correct-secret');
        $member->update([
            'roi_wallet_amount' => '8.0000',
            'working_wallet_amount' => '3.0000',
            'activation_wallet_amount' => '1.5000',
        ]);

        $this->from(route('public.login'))
            ->post(route('member.login.store'), [
                'member_id' => $member->member_id,
                'password' => 'wrong-secret',
            ])
            ->assertRedirect(route('public.login'))
            ->assertSessionHasErrors('member_id');
        $this->assertNull(session('member_context_id'));

        $this->from(route('public.login'))
            ->post(route('member.login.store'), [
                'member_id' => 'ST000000',
                'password' => 'correct-secret',
            ])
            ->assertRedirect(route('public.login'))
            ->assertSessionHasErrors('member_id');

        $this->from(route('public.login'))
            ->post(route('member.login.store'), [
                'member_id' => $member->email,
                'password' => 'correct-secret',
            ])
            ->assertRedirect(route('public.login'))
            ->assertSessionHasErrors('member_id');
        $this->assertNull(session('member_context_id'));
        $this->assertSame('inactive', $member->fresh()->status);

        $member->update(['status' => 'suspended']);
        $this->from(route('public.login'))
            ->post(route('member.login.store'), [
                'member_id' => $member->member_id,
                'password' => 'correct-secret',
            ])
            ->assertRedirect(route('public.login'))
            ->assertSessionHasErrors('member_id');
        $this->assertSame('suspended', $member->fresh()->status);
        $this->assertNull(session('member_context_id'));

        $member->update(['status' => 'inactive']);
        $this->post(route('member.login.store'), [
            'member_id' => $member->member_id,
            'password' => 'correct-secret',
        ])->assertRedirect(route('member.dashboard'));

        $member->refresh();
        $this->assertSame('8.0000', $member->roi_wallet_amount);
        $this->assertSame('3.0000', $member->working_wallet_amount);
        $this->assertSame('1.5000', $member->activation_wallet_amount);
        $this->assertSame(0, RoiTransaction::query()->count());
        $this->assertSame(0, LevelCommissionTransaction::query()->count());
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, PackagePurchase::query()->count());
        $this->assertTrue(Hash::check('correct-secret', $member->password));
        $this->assertNotSame('correct-secret', $member->password);
    }

    public function test_member_login_is_rate_limited(): void
    {
        Cache::flush();
        $member = $this->member('ST810005', 'limited-login@example.test', 'inactive', 'limited-secret');

        foreach (range(1, 5) as $attempt) {
            $this->from(route('public.login'))
                ->post(route('member.login.store'), [
                    'member_id' => $member->member_id,
                    'password' => 'wrong-secret',
                ])
                ->assertRedirect(route('public.login'));
        }

        $this->post(route('member.login.store'), [
            'member_id' => $member->member_id,
            'password' => 'wrong-secret',
        ])->assertStatus(429);
    }

    private function enablePayments(): void
    {
        PaymentSetting::current()->update([
            'currency_label' => 'USDT',
            'network' => 'Admin selected network',
            'receiving_address' => 'delta-support-public-address',
            'qr_image_path' => 'payment-qr/example.webp',
            'instructions' => PaymentSetting::USDT_INSTRUCTION,
            'is_enabled' => true,
        ]);
    }

    private function member(string $memberId, string $email, string $status, string $password): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Login Member',
            'mobile_no' => '9000000099',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => $status,
        ]);
    }
}
