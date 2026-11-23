<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberPreviewSession;
use App\Models\User;
use App\Services\MemberPreviewGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Simulates two browser cookie jars:
 * - Admin jar: primary web session only
 * - Preview jar: dedicated member-preview cookie only
 * - Combined jar: both cookies (real same-browser tabs)
 */
class IsolatedAdminMemberSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_preview_cookie_jars_stay_independent_across_refresh_navigation_and_logout(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin-iso@example.test',
            'password' => Hash::make('AdminPass!234'),
        ]);
        $member = $this->member('ST100000', 'Rahul Das');
        $other = $this->member('ST100099', 'Other Member');
        $cookieName = app(MemberPreviewGuard::class)->cookieName();

        // --- Admin jar: real HTTP login (email → web guard) ---
        $this->post(route('member.login.store'), [
            'email' => 'admin-iso@example.test',
            'password' => 'AdminPass!234',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('member_context_id'));

        $start = $this->post(route('admin.members.impersonate', $member))
            ->assertRedirect(route('member.dashboard'));

        $plain = $start->getCookie($cookieName)?->getValue();
        $this->assertNotNull($plain);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.[A-Za-z0-9]+$/', $plain);
        $this->assertFalse(session()->has('member_context_id'), 'Admin session must not store member identity');

        // --- Combined jar (real browser: web session + encrypted preview cookie) ---
        $this->withCookie($cookieName, $plain)
            ->get(route('admin.members.directory'))
            ->assertOk()
            ->assertSeeText('Member preview active')
            ->assertSeeText('ST100000');

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('member_context_id'));

        $this->withCookie($cookieName, $plain)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Admin Preview Mode')
            ->assertSeeText('Rahul Das')
            ->assertSeeText('ST100000');

        // Refresh member panel (combined jar)
        $this->withCookie($cookieName, $plain)
            ->get(route('member.wallets.overview'))
            ->assertOk()
            ->assertSeeText('Admin Preview Mode');

        // Refresh admin panel (combined jar) — identity remains admin
        $this->withCookie($cookieName, $plain)
            ->get(route('dashboard'))
            ->assertOk();
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue((bool) Auth::user()?->is_admin);

        // --- Preview-only jar: drop web auth, keep preview cookie ---
        Auth::logout();
        $this->flushSession();

        $this->withCookie($cookieName, $plain)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Rahul Das');
        $this->assertGuest();

        $this->withCookie($cookieName, $plain)
            ->get(route('admin.members.directory'))
            ->assertRedirect(route('login'));

        $this->withCookie($cookieName, $plain)
            ->put(route('member.profile.update'), [
                'member_name' => 'Hacked',
                'email' => 'hack@example.test',
                'mobile_no' => '9111111111',
            ])->assertForbidden();

        // --- Admin-only jar: no preview cookie ---
        $this->actingAs($admin);
        $this->get(route('admin.members.directory'))
            ->assertOk()
            ->assertSeeText('Member preview active'); // grant still in DB
        $this->assertAuthenticatedAs($admin);

        // End preview from admin — does not log out admin
        $this->actingAs($admin)
            ->post(route('admin.impersonation.stop'))
            ->assertRedirect(route('admin.members.directory'));
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(0, MemberPreviewSession::query()->whereNull('revoked_at')->count());

        // Preview cookie revoked — preview-only jar can no longer open member panel
        $this->flushSession();
        Auth::logout();
        $this->withCookie($cookieName, $plain)
            ->get(route('member.dashboard'))
            ->assertForbidden();

        // Re-login admin, start preview, admin logout revokes grant
        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $member))
            ->assertRedirect(route('member.dashboard'));
        $this->assertSame(1, MemberPreviewSession::query()->whereNull('revoked_at')->count());

        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, MemberPreviewSession::query()->whereNull('revoked_at')->count());

        // Switching members is explicit (second Login as Member), not accidental
        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $member));
        $first = MemberPreviewSession::query()->whereNull('revoked_at')->first();
        $this->actingAs($admin)
            ->post(route('admin.members.impersonate', $other));
        $second = MemberPreviewSession::query()->whereNull('revoked_at')->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('ST100099', $second->member_id);
        $this->assertNotNull($first->fresh()->revoked_at);
    }

    public function test_normal_member_login_uses_web_session_not_preview_cookie(): void
    {
        $member = $this->member('ST200001', 'Normal Login');
        $cookieName = app(MemberPreviewGuard::class)->cookieName();

        $this->post(route('member.login.store'), [
            'member_id' => 'ST200001',
            'password' => 'secret-secret',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertSame('ST200001', session('member_context_id'));
        $this->assertSame(0, MemberPreviewSession::query()->count());

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertDontSeeText('Admin Preview Mode')
            ->assertSeeText('Normal Login');

        // No preview cookie should be set for ordinary login
        $this->assertNull(
            collect($this->app['cookie']->getQueuedCookies())
                ->first(fn ($c) => $c->getName() === $cookieName)
        );
    }

    private function member(string $memberId, string $name): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9000000099',
            'email' => strtolower($memberId).'@example.test',
            'password' => Hash::make('secret-secret'),
            'status' => 'active',
        ]);
    }
}
