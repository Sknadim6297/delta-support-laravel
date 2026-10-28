<?php

namespace Tests\Feature;

use App\Models\ImpersonationLog;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_a_member_dashboard_and_return_to_the_admin_session(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST100001', 'Sk Nadim');
        $originalPassword = $member->password;

        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $member))
            ->assertRedirect(route('member.dashboard'));

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Admin Preview Mode')
            ->assertSeeText('ST100001')
            ->assertSeeText('Sk Nadim');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame('ST100001', session('member_context_id'));
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame($originalPassword, $member->fresh()->password);

        $log = ImpersonationLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('ST100001', $log->member_id);
        $this->assertSame('started', $log->action);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->ended_at);
        $this->assertSame('127.0.0.1', $log->ip_address);

        $this->get(route('admin.members.directory'))
            ->assertRedirect(route('member.dashboard'));

        $this->post(route('admin.impersonation.stop'))
            ->assertRedirect(route('admin.members.directory'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertFalse(session()->has('member_context_id'));
        $this->assertSame('ended', $log->fresh()->action);
        $this->assertNotNull($log->fresh()->ended_at);

        $this->get(route('admin.members.directory'))
            ->assertOk()
            ->assertSeeText('Login as Member');
    }

    public function test_nested_invalid_guest_and_non_admin_impersonation_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST100002', 'Rima Das');
        $other = $this->member('ST100003', 'Other Member');

        $this->post(route('admin.members.impersonate', $member))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.members.impersonate', $member))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', 999999))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.members.impersonate', $member))
            ->assertMethodNotAllowed();

        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $member))
            ->assertRedirect(route('member.dashboard'));

        $this->post(route('admin.members.impersonate', $other))
            ->assertForbidden();

        $this->assertSame('ST100002', session('member_context_id'));
    }

    public function test_impersonation_blocks_member_changes_and_logout_clears_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = $this->member('ST100004', 'Preview Member');

        $package = Package::query()->create([
            'code' => 'PKG-PREVIEW',
            'name' => 'Preview Tour',
            'price_usd' => '49.0000',
            'tour_title' => 'Preview',
            'tour_details' => 'Preview details',
            'duration' => '2 days',
            'benefits' => 'None',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $member));

        $this->put(route('member.profile.update'), [
            'member_name' => 'Changed Name',
            'email' => 'changed@example.test',
            'mobile_no' => '9000000001',
            'wallet_address' => '0xchanged',
        ])->assertForbidden();

        $this->post(route('member.packages.purchase', $package), [
            'payment_reference' => 'TXREF123456',
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
        ])->assertForbidden();

        $this->post(route('member.change-password.post'), [
            'current_password' => 'secret-secret',
            'new_password' => 'another-secret',
            'new_password_confirmation' => 'another-secret',
        ])->assertForbidden();

        $this->assertSame('Preview Member', $member->fresh()->member_name);
        $this->assertSame('9000000004', $member->fresh()->mobile_no);
        $this->assertTrue(Hash::check('secret-secret', $member->fresh()->password));
        $this->assertSame(0, PackagePurchase::query()->count());

        $this->post(route('member.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertFalse(session()->has('member_context_id'));
        $this->assertNotNull(ImpersonationLog::query()->first()->ended_at);
    }

    public function test_ordinary_member_login_does_not_start_an_admin_preview(): void
    {
        $member = $this->member('ST100005', 'Normal Member');

        $this->post(route('member.login.store'), [
            'member_id' => 'st100005',
            'password' => 'secret-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertSame('ST100005', session('member_context_id'));
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertSame(0, ImpersonationLog::query()->count());

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSeeText('Admin Preview Mode');
    }

    private function member(string $memberId, string $name): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9000000004',
            'email' => strtolower($memberId).'@example.test',
            'password' => Hash::make('secret-secret'),
            'status' => 'active',
        ]);
    }
}
