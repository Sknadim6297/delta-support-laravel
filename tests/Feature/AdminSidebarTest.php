<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_all_requested_sidebar_sections(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('All Members')
            ->assertSeeText('Package Purchases')
            ->assertSeeText('Pending Payments')
            ->assertSeeText('All Invoices')
            ->assertSeeText('ROI Plans')
            ->assertSeeText('All Packages')
            ->assertSeeText('Add Package')
            ->assertDontSeeText('Tour Packages')
            ->assertDontSeeText('Tour Management')
            ->assertDontSeeText('Transfer to Activation Wallet from Admin List')
            ->assertDontSeeText('Force Credit to ROI Wallet Entry')
            ->assertDontSeeText('Replied Ticket List')
            ->assertDontSee('admin2/', false);
    }

    public function test_admin_coming_soon_is_admin_only_and_names_feature(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.coming-soon', ['feature' => 'roi-wallet-pending-list']))
            ->assertOk()
            ->assertSeeText('ROI Wallet Pending List')
            ->assertSeeText('Coming Soon');

        auth()->logout();

        $this->get(route('admin.coming-soon', ['feature' => 'roi-wallet-pending-list']))
            ->assertRedirect(route('login'));
    }
}
