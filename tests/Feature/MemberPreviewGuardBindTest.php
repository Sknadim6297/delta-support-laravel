<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberPreviewGuard;
use App\Support\MemberContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberPreviewGuardBindTest extends TestCase
{
    use RefreshDatabase;

    public function test_bind_from_request_accepts_issued_cookie_token(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = Member::query()->create([
            'member_id' => 'STBIND01',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Bind Test',
            'mobile_no' => '9000000001',
            'email' => 'stbind01@example.test',
            'password' => Hash::make('secret-secret'),
            'status' => 'active',
        ]);

        $guard = app(MemberPreviewGuard::class);
        $request = Request::create('/member/dashboard', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $issued = $guard->issue($admin, $member, $request);

        $bound = Request::create('/member/dashboard', 'GET', [], [
            $guard->cookieName() => $issued['plain_token'],
        ]);
        $bound->setLaravelSession($this->app['session']->driver());
        $guard->bindFromRequest($bound);

        $this->assertTrue(MemberContext::isPreview($bound));
        $this->assertSame('STBIND01', MemberContext::id($bound));
        $this->assertSame((int) $admin->id, MemberContext::previewAdminId($bound));
    }
}
