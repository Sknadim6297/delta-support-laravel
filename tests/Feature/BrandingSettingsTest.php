<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Member;
use App\Models\User;
use App\Support\BrandTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_page_shows_the_saved_company_and_default_colors(): void
    {
        CompanySetting::current()->update(['company_name' => 'Delta Brand Co']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.settings.branding'))
            ->assertOk()
            ->assertSeeText('Delta Brand Co')
            ->assertSeeText('Primary')
            ->assertSeeText('Change Logo')
            ->assertSeeText('Save Colors')
            ->assertSeeText('Reset to Default')
            ->assertDontSeeText('Remove Logo')
            ->assertSee('#0878d1', false)
            ->assertSeeText('Navy')
            ->assertSee('#092747', false)
            ->assertSee('site/img/logo.png', false);

        auth()->logout();

        $this->get(route('admin.settings.branding'))->assertRedirect(route('login'));
    }

    public function test_admin_can_upload_replace_and_remove_a_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.settings.branding.logo.update'), [
                'logo' => UploadedFile::fake()->image('mark.png', 120, 80),
            ])
            ->assertRedirect(route('admin.settings.branding'))
            ->assertSessionHas('success', 'Logo saved.');

        $first = CompanySetting::current()->fresh();
        $this->assertTrue(BrandTokens::isManagedLogo($first->logo_path));
        Storage::disk('local')->assertExists($first->logo_path);

        $this->get(route('admin.settings.branding'))
            ->assertSeeText('Remove Logo')
            ->assertSee(route('brand.logo'), false);
        $this->get(route('brand.logo'))->assertOk();
        auth()->logout();
        $this->get(route('home'))->assertSee(route('brand.logo'), false);
        $this->actingAs($admin);

        $this->post(route('admin.settings.branding.logo.update'), [
            'logo' => UploadedFile::fake()->create('notes.txt', 12, 'text/plain'),
        ])->assertSessionHasErrors('logo');
        $this->assertSame($first->logo_path, CompanySetting::current()->fresh()->logo_path);
        Storage::disk('local')->assertExists($first->logo_path);

        $this->post(route('admin.settings.branding.logo.update'), [
            'logo' => UploadedFile::fake()->image('wide.jpg', 90, 40),
        ])->assertRedirect(route('admin.settings.branding'));

        $second = CompanySetting::current()->fresh();
        $this->assertNotSame($first->logo_path, $second->logo_path);
        Storage::disk('local')->assertMissing($first->logo_path);
        Storage::disk('local')->assertExists($second->logo_path);

        $this->delete(route('admin.settings.branding.logo.destroy'))
            ->assertRedirect(route('admin.settings.branding'))
            ->assertSessionHas('success');

        $this->assertNull(CompanySetting::current()->fresh()->logo_path);
        Storage::disk('local')->assertMissing($second->logo_path);
        $this->get(route('brand.logo'))->assertNotFound();
        $this->get(route('admin.settings.branding'))->assertSee('site/img/logo.png', false);
        $this->assertFileExists(public_path('site/img/logo.png'));
    }

    public function test_oversized_logo_is_rejected_without_replacing_the_current_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('branding-logos/keep.png', 'logo');
        CompanySetting::current()->update(['logo_path' => 'branding-logos/keep.png']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.settings.branding.logo.update'), [
                'logo' => UploadedFile::fake()->image('huge.png')->size(3000),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertSame('branding-logos/keep.png', CompanySetting::current()->fresh()->logo_path);
        Storage::disk('local')->assertExists('branding-logos/keep.png');
    }

    public function test_saved_colors_apply_to_public_admin_member_and_login_layouts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $colors = [
            'primary' => '#112233',
            'primary_hover' => '#001122',
            'navy' => '#223344',
            'canvas' => '#f7f7f7',
            'border' => '#ccddee',
        ];

        $this->actingAs($admin)
            ->put(route('admin.settings.branding.colors.update'), ['colors' => $colors])
            ->assertRedirect(route('admin.settings.branding'))
            ->assertSessionHas('success', 'Brand colors saved.');

        $settings = CompanySetting::current()->fresh();
        $this->assertSame('#112233', $settings->primary_color);
        $this->assertSame('#001122', $settings->primary_hover_color);

        $this->get(route('admin.settings.branding'))
            ->assertSee('value="#112233"', false)
            ->assertSee('--color-brand: #112233', false);

        auth()->logout();

        $this->get(route('home'))->assertSee('--primary: #112233', false)->assertSee('--navy: #223344', false);
        $this->get(route('public.login'))->assertSee('--login-primary: #112233', false);
        $this->get(route('member.register'))->assertSee('--primary: #112233', false);
        $this->get(route('member.forgot'))->assertSee('--ds-primary: #112233', false);

        $member = Member::query()->create([
            'member_id' => 'ST910777',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Brand Member',
            'mobile_no' => '9000000777',
            'email' => 'brand-member@example.test',
            'password' => Hash::make('secret-secret'),
            'status' => 'inactive',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.change-password'))
            ->assertOk()
            ->assertSee('--color-brand: #112233', false)
            ->assertSee('--color-canvas: #f7f7f7', false);
    }

    public function test_invalid_or_partial_colors_do_not_change_saved_branding(): void
    {
        CompanySetting::current()->update([
            'primary_color' => '#112233',
            'navy_color' => '#223344',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->from(route('admin.settings.branding'))
            ->put(route('admin.settings.branding.colors.update'), [
                'colors' => [
                    'primary' => 'blue',
                    'primary_hover' => '#001122',
                    'navy' => '#223344',
                    'canvas' => '#f7f7f7',
                    'border' => '#ccddee',
                ],
            ])
            ->assertRedirect(route('admin.settings.branding'))
            ->assertSessionHasErrors('colors.primary');

        $settings = CompanySetting::current()->fresh();
        $this->assertSame('#112233', $settings->primary_color);
        $this->assertSame('#223344', $settings->navy_color);
    }

    public function test_reset_restores_the_default_brand_colors(): void
    {
        CompanySetting::current()->update([
            'primary_color' => '#112233',
            'primary_hover_color' => '#001122',
            'navy_color' => '#223344',
            'canvas_color' => '#f7f7f7',
            'border_color' => '#ccddee',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->delete(route('admin.settings.branding.colors.reset'))
            ->assertRedirect(route('admin.settings.branding'));

        $settings = CompanySetting::current()->fresh();
        $this->assertNull($settings->primary_color);
        $this->assertNull($settings->navy_color);

        auth()->logout();

        $this->get(route('home'))->assertSee('--ds-primary: #0878d1', false)->assertSee('--ds-foreground: #092747', false);
    }

    public function test_guests_and_non_admins_cannot_update_branding(): void
    {
        $colors = [
            'primary' => '#112233',
            'primary_hover' => '#001122',
            'navy' => '#223344',
            'canvas' => '#f7f7f7',
            'border' => '#ccddee',
        ];

        $this->put(route('admin.settings.branding.colors.update'), ['colors' => $colors])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.settings.branding.colors.update'), ['colors' => $colors])
            ->assertForbidden();

        $this->assertNull(CompanySetting::query()->first()?->primary_color);
    }
}
