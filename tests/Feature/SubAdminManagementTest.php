<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\AdminUserPermission;
use App\Models\Member;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AdminAccess;
use App\Support\AdminPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function mainAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'is_admin' => true,
            'admin_role' => User::ADMIN_ROLE_MAIN,
            'is_active' => true,
            'email' => 'main-admin-'.uniqid().'@example.test',
        ], $overrides));
    }

    private function password(): string
    {
        return 'SubAdmin!Pass12';
    }

    public function test_main_admin_login_with_email_still_works(): void
    {
        $admin = $this->mainAdmin([
            'email' => 'root@example.test',
            'password' => Hash::make($this->password()),
        ]);

        $this->post(route('login.submit'), [
            'email' => 'root@example.test',
            'password' => $this->password(),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_main_admin_can_create_sub_admin_with_module_permissions(): void
    {
        $main = $this->mainAdmin();

        $response = $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Support Desk',
            'login_id' => 'support01',
            'email' => 'support01@example.test',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard', 'members', 'support'],
        ]);

        $sub = User::query()->where('login_id', 'SUPPORT01')->first();
        $this->assertNotNull($sub);
        $response->assertRedirect(route('admin.sub-admins.show', $sub));
        $this->assertTrue(Hash::check($this->password(), $sub->password));
        $this->assertEqualsCanonicalizing(
            ['dashboard', 'members', 'support'],
            app(AdminAccess::class)->permissionsFor($sub)
        );
    }

    public function test_legacy_action_keys_expand_to_full_module_access(): void
    {
        $sub = User::factory()->subAdmin()->create([
            'login_id' => 'LEGACY01',
            'is_active' => true,
        ]);

        // Simulate previously stored action-level keys.
        AdminUserPermission::query()->insert([
            ['user_id' => $sub->id, 'permission_key' => 'members.view', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $sub->id, 'permission_key' => 'members.create', 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(AdminAccess::class)->forgetCache($sub);

        $this->assertTrue(app(AdminAccess::class)->can($sub, 'members'));
        $this->assertSame('members', AdminPermissionRegistry::permissionForRoute('admin.members.store'));
        $this->actingAs($sub)->get(route('admin.members.registration'))->assertOk();
        $this->actingAs($sub)->get(route('admin.members.check-sponsor-id', ['sponsor_id' => 'ST666666']))->assertOk();
    }

    public function test_member_management_module_allows_full_registration_workflow(): void
    {
        $sub = User::factory()->subAdmin()->create([
            'login_id' => 'REG01',
            'is_active' => true,
            'password' => Hash::make($this->password()),
        ]);
        app(AdminAccess::class)->syncPermissions($sub, ['dashboard', 'members']);

        $this->actingAs($sub)->get(route('admin.members.registration'))
            ->assertOk()
            ->assertSee('id="admin-mobile"', false)
            ->assertSee('autocomplete="tel"', false);

        $this->actingAs($sub)
            ->get(route('admin.members.check-sponsor-id', ['sponsor_id' => 'ST666666']))
            ->assertOk();

        $payload = [
            'sponsor_id' => 'ST666666',
            'member_name' => 'Registered By Sub',
            'mobile_no' => '9876543210',
            'email' => 'subreg'.uniqid().'@example.test',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
        ];

        $response = $this->actingAs($sub)->post(route('admin.members.store'), $payload);
        $response->assertRedirect();
        $this->assertTrue(
            Member::query()->where('email', $payload['email'])->exists(),
            'Member registration by Sub-Admin with members module must persist.'
        );

        $this->actingAs($sub)->get(route('admin.members.update'))->assertOk();
        $this->actingAs($sub)->get(route('admin.members.directory'))->assertOk();
    }

    public function test_duplicate_login_id_and_reserved_permissions_are_rejected(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'One',
            'login_id' => 'DESK01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard'],
        ])->assertRedirect();

        $this->actingAs($main)->from(route('admin.sub-admins.create'))->post(route('admin.sub-admins.store'), [
            'name' => 'Two',
            'login_id' => 'desk01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'permissions' => ['dashboard'],
        ])->assertRedirect(route('admin.sub-admins.create'))
            ->assertSessionHasErrors('login_id');

        $this->actingAs($main)->from(route('admin.sub-admins.create'))->post(route('admin.sub-admins.store'), [
            'name' => 'Hacker',
            'login_id' => 'HACK01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'permissions' => ['dashboard', 'sub_admins.manage', 'level_income.authorize'],
        ])->assertRedirect(route('admin.sub-admins.create'))
            ->assertSessionHasErrors('permissions');
    }

    public function test_sub_admin_module_sidebar_and_route_authorization(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Limited',
            'login_id' => 'LIMIT01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard', 'members', 'support'],
        ])->assertRedirect();

        auth()->logout();
        $this->post(route('login.submit'), [
            'member_id' => 'LIMIT01',
            'password' => $this->password(),
        ])->assertRedirect(route('dashboard'));

        $sub = User::query()->where('login_id', 'LIMIT01')->firstOrFail();

        $this->actingAs($sub)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Member Management')
            ->assertSeeText('Support Tickets')
            ->assertDontSeeText('Sub-Admin Management')
            ->assertDontSeeText('Financial Controls')
            ->assertDontSeeText('Wallet Overview');

        $this->actingAs($sub)->get(route('admin.members.directory'))->assertOk();
        $this->actingAs($sub)->get(route('admin.members.registration'))->assertOk();
        $this->actingAs($sub)->get(route('admin.support.index'))->assertOk();
        $this->actingAs($sub)->get(route('admin.wallets.overview'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));
        $this->actingAs($sub)->get(route('admin.settings.financial'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));
        $this->actingAs($sub)->post(route('admin.finance.level-income-rules.authorize'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));

        $this->actingAs($sub)->get(route('admin.access-denied', ['reason' => 'page']))
            ->assertOk()
            ->assertSeeText("You don't have the access of this page. Sorry.");
    }

    public function test_inactive_sub_admin_cannot_login_and_existing_session_is_blocked(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Temp',
            'login_id' => 'TEMP01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard'],
        ]);
        $sub = User::query()->where('login_id', 'TEMP01')->firstOrFail();
        $this->actingAs($sub)->get(route('dashboard'))->assertOk();

        $this->actingAs($main)->post(route('admin.sub-admins.deactivate', $sub))->assertRedirect();
        $sub->refresh();
        $this->assertFalse($sub->is_active);
        $this->actingAs($sub)->get(route('dashboard'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'inactive']));

        auth()->logout();
        $this->post(route('login.submit'), [
            'member_id' => 'TEMP01',
            'password' => $this->password(),
        ])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_password_reset_and_permission_updates(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Ops',
            'login_id' => 'OPS01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard', 'members'],
        ]);
        $sub = User::query()->where('login_id', 'OPS01')->firstOrFail();

        DB::table('sessions')->insert([
            'id' => 'sess-sub-1',
            'user_id' => $sub->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $newPassword = 'NewSubAdmin!99';
        $this->actingAs($main)->put(route('admin.sub-admins.password.update', $sub), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('admin.sub-admins.show', $sub));

        $this->assertDatabaseMissing('sessions', ['user_id' => $sub->id]);
        $this->assertTrue(Hash::check($newPassword, $sub->fresh()->password));
        $this->assertTrue(StrongPassword::accepts($newPassword));

        $this->actingAs($main)->put(route('admin.sub-admins.update', $sub), [
            'name' => 'Ops Updated',
            'login_id' => 'OPS01',
            'email' => $sub->email,
            'is_active' => '1',
            'permissions' => ['dashboard', 'support'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['dashboard', 'support'],
            app(AdminAccess::class)->permissionsFor($sub->fresh())
        );
    }

    public function test_registry_module_policy_and_reserved_routes(): void
    {
        $this->assertContains('members', AdminPermissionRegistry::moduleKeys());
        $this->assertSame('members', AdminPermissionRegistry::permissionForRoute('admin.members.store'));
        $this->assertSame('members', AdminPermissionRegistry::permissionForRoute('admin.members.registration'));
        $this->assertSame('payments', AdminPermissionRegistry::permissionForRoute('admin.payments.approve'));
        $this->assertSame('level_income.authorize', AdminPermissionRegistry::permissionForRoute('admin.finance.level-income-rules.authorize'));
        $this->assertSame('packages.authorize_accrual', AdminPermissionRegistry::permissionForRoute('admin.packages.authorize-accrual'));
        $this->assertTrue(AdminPermissionRegistry::isReserved('sub_admins.manage'));
        $this->assertSame(
            ['members', 'payments'],
            AdminPermissionRegistry::normalizeToModuleKeys(['members.view', 'members.create', 'payments.approve'])
        );
        foreach (AdminPermissionRegistry::modulesForUi() as $module) {
            $this->assertCount(1, $module['permissions']);
        }
    }

    public function test_unmapped_and_reserved_module_routes_denied(): void
    {
        $sub = User::factory()->subAdmin()->create([
            'login_id' => 'DENY01',
            'is_active' => true,
        ]);
        app(AdminAccess::class)->syncPermissions($sub, ['dashboard']);

        $this->actingAs($sub)->get(route('admin.investments.active-investments'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));
        $this->actingAs($sub)->post(route('admin.finance.level-income-rules.authorize'))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));
    }

    public function test_permission_assignment_ui_is_module_based(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->get(route('admin.sub-admins.create'))
            ->assertOk()
            ->assertSeeText('full module access')
            ->assertSeeText('Member Management')
            ->assertDontSeeText('(action)');
    }

    public function test_main_admin_can_delete_sub_admin_and_access_is_revoked(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Delete Me',
            'login_id' => 'DELME01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard', 'members'],
        ])->assertRedirect();

        $sub = User::query()->where('login_id', 'DELME01')->firstOrFail();
        $subId = $sub->id;

        DB::table('sessions')->insert([
            'id' => 'sess-del-1',
            'user_id' => $subId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $memberBefore = Member::query()->count();

        $this->actingAs($main)->get(route('admin.sub-admins.index'))
            ->assertOk()
            ->assertSeeText('DELME01')
            ->assertSeeText('Delete');

        $this->actingAs($main)->delete(route('admin.sub-admins.destroy', $sub))
            ->assertRedirect(route('admin.sub-admins.index'))
            ->assertSessionHas('status');

        $this->assertNull(User::query()->find($subId));
        $this->assertNotNull(User::withTrashed()->find($subId));
        $this->assertTrue(User::withTrashed()->findOrFail($subId)->trashed());
        $this->assertFalse(User::withTrashed()->findOrFail($subId)->is_active);
        $this->assertDatabaseMissing('admin_user_permissions', ['user_id' => $subId]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $subId]);
        $this->assertSame($memberBefore, Member::query()->count());

        $this->actingAs($main)->get(route('admin.sub-admins.index'))
            ->assertOk()
            ->assertSeeText('No Sub-Admin accounts yet.')
            ->assertDontSee('<span class="ds-mm-id">DELME01</span>', false);

        auth()->logout();
        $this->post(route('login.submit'), [
            'member_id' => 'DELME01',
            'password' => $this->password(),
        ])->assertSessionHasErrors();
        $this->assertGuest();

        $this->assertTrue(
            AdminActivityLog::query()
                ->where('action', 'sub_admin_deleted')
                ->where('user_id', $main->id)
                ->where('subject', 'sub_admin:'.$subId)
                ->exists()
        );

        // Soft-deleted accounts are not reachable by route binding.
        $this->actingAs($main)->get(route('admin.sub-admins.show', $subId))->assertNotFound();
    }

    public function test_sub_admin_cannot_delete_administrators(): void
    {
        $main = $this->mainAdmin();
        $sub = User::factory()->subAdmin()->create([
            'login_id' => 'NODELETE',
            'is_active' => true,
        ]);
        $other = User::factory()->subAdmin()->create([
            'login_id' => 'TARGET01',
            'is_active' => true,
        ]);
        app(AdminAccess::class)->syncPermissions($sub, ['dashboard', 'members']);

        $this->actingAs($sub)->delete(route('admin.sub-admins.destroy', $other))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));

        $this->assertNotNull(User::query()->find($other->id));
        $this->assertFalse($other->fresh()->trashed());

        $this->actingAs($sub)->delete(route('admin.sub-admins.destroy', $sub))
            ->assertRedirect(route('admin.access-denied', ['reason' => 'page']));

        $this->assertNotNull(User::query()->find($sub->id));
    }

    public function test_main_admin_account_cannot_be_deleted_via_sub_admin_destroy(): void
    {
        $main = $this->mainAdmin();
        $otherMain = $this->mainAdmin(['email' => 'other-main-'.uniqid().'@example.test']);

        $this->actingAs($main)->delete(route('admin.sub-admins.destroy', $otherMain))
            ->assertNotFound();

        $this->assertNotNull(User::query()->find($otherMain->id));
        $this->assertFalse($otherMain->fresh()->trashed());
    }

    public function test_deleted_login_id_can_be_reused_for_new_sub_admin(): void
    {
        $main = $this->mainAdmin();
        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'First',
            'login_id' => 'REUSE01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard'],
        ])->assertRedirect();

        $first = User::query()->where('login_id', 'REUSE01')->firstOrFail();
        $this->actingAs($main)->delete(route('admin.sub-admins.destroy', $first))->assertRedirect();

        $this->actingAs($main)->post(route('admin.sub-admins.store'), [
            'name' => 'Second',
            'login_id' => 'REUSE01',
            'password' => $this->password(),
            'password_confirmation' => $this->password(),
            'is_active' => '1',
            'permissions' => ['dashboard', 'support'],
        ])->assertRedirect();

        $second = User::query()->where('login_id', 'REUSE01')->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertEqualsCanonicalizing(
            ['dashboard', 'support'],
            app(AdminAccess::class)->permissionsFor($second)
        );
    }
}
