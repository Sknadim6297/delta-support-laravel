<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_and_change_password_screens_render_the_password_form(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'email' => 'security-admin@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('admin.settings.security'))
            ->assertOk()
            ->assertSeeText('Change password')
            ->assertSeeText('security-admin@example.com')
            ->assertSee(route('admin.change-password.index'), false);

        $this->get(route('admin.change-password.index'))
            ->assertOk()
            ->assertSee('id="changePasswordForm"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="new_password"', false)
            ->assertSee('name="new_password_confirmation"', false)
            ->assertSee('id="currentPassword"', false)
            ->assertSee('id="newPassword"', false)
            ->assertSee('id="confirmPassword"', false);
    }

    public function test_user_can_change_password_with_valid_old_and_matching_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $this->actingAs($user);

        $this->post(route('admin.change-password.update'), [
            'current_password' => 'old-password-123',
            'new_password' => 'New-Password-456',
            'new_password_confirmation' => 'New-Password-456',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertTrue(Hash::check('New-Password-456', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertTrue(auth()->check());
    }

    public function test_wrong_old_password_does_not_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $this->actingAs($user);

        $this->from(route('admin.change-password.index'))
            ->post(route('admin.change-password.update'), [
                'current_password' => 'wrong-old-password',
                'new_password' => 'New-Password-456',
                'new_password_confirmation' => 'New-Password-456',
            ])
            ->assertRedirect(route('admin.change-password.index'))
            ->assertSessionHasErrors('current_password');

        $user->refresh();

        $this->assertTrue(Hash::check('correct-old-password', $user->password));
        $this->assertFalse(Hash::check('New-Password-456', $user->password));
    }

    public function test_new_password_and_confirmation_must_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $this->actingAs($user);

        $this->from(route('admin.change-password.index'))
            ->post(route('admin.change-password.update'), [
                'current_password' => 'old-password-123',
                'new_password' => 'New-Password-456',
                'new_password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('admin.change-password.index'))
            ->assertSessionHasErrors('new_password');

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', $user->password));
    }

    public function test_password_fields_are_not_repopulated_after_validation_failure(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $this->actingAs($user);

        $this->from(route('admin.change-password.index'))
            ->post(route('admin.change-password.update'), [
                'current_password' => 'submitted-current-password',
                'new_password' => 'submitted-new-password',
                'new_password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('admin.change-password.index'));

        $this->get(route('admin.change-password.index'))
            ->assertDontSee('value="submitted-current-password"', false)
            ->assertDontSee('value="submitted-new-password"', false)
            ->assertDontSee('value="different-password"', false);
    }

    public function test_empty_required_fields_show_validation_errors(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $this->actingAs($user);

        $this->from(route('admin.change-password.index'))
            ->post(route('admin.change-password.update'), [
                'current_password' => '',
                'new_password' => '',
                'new_password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.change-password.index'))
            ->assertSessionHasErrors(['current_password', 'new_password', 'new_password_confirmation']);
    }

    public function test_new_password_works_for_login_and_old_password_no_longer_works(): void
    {
        $user = User::factory()->create([
            'email' => 'change-password-user@example.com',
            'password' => Hash::make('old-password-123'),
        ]);

        $this->actingAs($user);

        $this->post(route('admin.change-password.update'), [
            'current_password' => 'old-password-123',
            'new_password' => 'New-Password-456',
            'new_password_confirmation' => 'New-Password-456',
        ]);

        $this->assertFalse(auth()->attempt([
            'email' => $user->email,
            'password' => 'old-password-123',
        ]));

        $this->assertTrue(auth()->attempt([
            'email' => $user->email,
            'password' => 'New-Password-456',
        ]));
    }

    public function test_first_login_must_replace_a_weak_password_before_the_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'must_change_password' => true,
            'password' => Hash::make('Old-Password-123'),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.change-password.index'));

        $this->get(route('admin.change-password.index'))->assertOk();

        $this->from(route('admin.change-password.index'))
            ->post(route('admin.change-password.update'), [
                'current_password' => 'Old-Password-123',
                'new_password' => 'admin@1234',
                'new_password_confirmation' => 'admin@1234',
            ])
            ->assertRedirect(route('admin.change-password.index'))
            ->assertSessionHasErrors('new_password');

        $this->post(route('admin.change-password.update'), [
            'current_password' => 'Old-Password-123',
            'new_password' => 'New-Password-456',
            'new_password_confirmation' => 'New-Password-456',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }
}
