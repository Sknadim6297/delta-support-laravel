<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_can_update_name_without_changing_email_or_password(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'name' => 'Delta Support Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('current-password'),
        ]);
        $other = User::factory()->create([
            'is_admin' => true,
            'name' => 'Other Admin',
            'email' => 'other@example.test',
        ]);
        $member = Member::query()->create([
            'member_id' => 'ST910888',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Untouched Member',
            'mobile_no' => '9000000888',
            'email' => 'member@example.test',
            'password' => Hash::make('member-password'),
            'status' => 'inactive',
        ]);
        $originalHash = $admin->password;

        $this->actingAs($admin)
            ->put(route('admin.settings.profile.update'), [
                'name' => 'Updated Admin',
                'email' => 'admin@example.test',
            ])
            ->assertRedirect(route('admin.settings.profile'))
            ->assertSessionHas('success', 'Profile updated.');

        $admin->refresh();
        $this->assertSame('Updated Admin', $admin->name);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertSame($originalHash, $admin->password);
        $this->assertSame('Other Admin', $other->fresh()->name);
        $this->assertSame('member@example.test', $member->fresh()->email);
        $this->assertAuthenticatedAs($admin);

        $this->get(route('admin.settings.profile'))
            ->assertOk()
            ->assertSee('value="Updated Admin"', false)
            ->assertSeeText('Updated Admin');
    }

    public function test_email_change_requires_the_current_password_and_rejects_duplicates(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'name' => 'Delta Support Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('current-password'),
        ]);
        User::factory()->create([
            'is_admin' => true,
            'email' => 'taken@example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.update'), [
                'name' => 'Renamed Admin',
                'email' => 'new-admin@example.test',
            ])
            ->assertRedirect(route('admin.settings.profile'))
            ->assertSessionHasErrors('email_password');

        $this->assertSame('admin@example.test', $admin->fresh()->email);
        $this->assertSame('Delta Support Admin', $admin->fresh()->name);

        $this->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.update'), [
                'name' => 'Renamed Admin',
                'email' => 'new-admin@example.test',
                'email_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email_password');

        $this->assertSame('admin@example.test', $admin->fresh()->email);

        $this->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.update'), [
                'name' => 'Renamed Admin',
                'email' => 'taken@example.test',
                'email_password' => 'current-password',
            ])
            ->assertSessionHasErrors('email');

        $this->put(route('admin.settings.profile.update'), [
            'name' => 'Renamed Admin',
            'email' => 'new-admin@example.test',
            'email_password' => 'current-password',
        ])->assertRedirect(route('admin.settings.profile'));

        $admin->refresh();
        $this->assertSame('Renamed Admin', $admin->name);
        $this->assertSame('new-admin@example.test', $admin->email);

        $this->get(route('admin.settings.profile'))
            ->assertSee('value="new-admin@example.test"', false)
            ->assertSeeText('new-admin@example.test');
    }

    public function test_password_change_is_hashed_and_the_new_password_can_log_in(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.test',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-password', $admin->fresh()->password));

        $this->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.password'), [
                'current_password' => 'current-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $this->from(route('admin.settings.profile'))
            ->put(route('admin.settings.profile.password'), [
                'current_password' => 'current-password',
                'password' => 'new-password-456',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors('password');

        $this->put(route('admin.settings.profile.password'), [
            'current_password' => 'current-password',
            'password' => 'visible-secret-value',
            'password_confirmation' => 'not-the-same',
        ]);

        $this->get(route('admin.settings.profile'))
            ->assertDontSee('value="visible-secret-value"', false)
            ->assertDontSee('value="current-password"', false);

        $this->put(route('admin.settings.profile.password'), [
            'current_password' => 'current-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])
            ->assertRedirect(route('admin.settings.profile'))
            ->assertSessionHas('success', 'Password updated.');

        $admin->refresh();
        $this->assertTrue(Hash::check('new-password-456', $admin->password));
        $this->assertFalse(Hash::check('current-password', $admin->password));
        $this->assertNotSame('new-password-456', $admin->password);
        $this->assertAuthenticatedAs($admin);

        auth()->logout();

        $this->post(route('login.submit'), [
            'member_id' => 'admin@example.test',
            'password' => 'current-password',
        ])->assertSessionHasErrors('member_id');

        $this->post(route('login.submit'), [
            'member_id' => 'admin@example.test',
            'password' => 'new-password-456',
        ])->assertRedirect('/dashboard');
    }

    public function test_guests_and_non_admins_cannot_update_the_profile(): void
    {
        $this->put(route('admin.settings.profile.update'), [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
        ])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.settings.profile.update'), [
                'name' => 'Intruder',
                'email' => 'intruder@example.test',
            ])
            ->assertForbidden();
    }

    public function test_password_updates_are_rate_limited(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('current-password'),
        ]);

        $payload = [
            'current_password' => 'wrong-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ];

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($admin)
                ->from(route('admin.settings.profile'))
                ->put(route('admin.settings.profile.password'), $payload)
                ->assertRedirect(route('admin.settings.profile'));
        }

        $this->actingAs($admin)
            ->put(route('admin.settings.profile.password'), $payload)
            ->assertStatus(429);

        $this->assertTrue(Hash::check('current-password', $admin->fresh()->password));
    }
}
