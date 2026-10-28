<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_dashboard_renders_all_sidebar_links_without_route_errors(): void
    {
        $member = Member::create([
            'member_id' => 'STSIDEBAR1',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Sidebar Member',
            'mobile_no' => '9000000000',
            'pan_card_no' => 'SIDEBAR0001',
            'email' => 'sidebar@example.test',
            'status' => 'active',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('My Referral Link')
            ->assertSeeText('Register New Member')
            ->assertSee(route('member.registration'), false)
            ->assertSeeText('Referral Tree')
            ->assertSee('member/genealogy/tree-view', false)
            ->assertSee('member/rewards', false)
            ->assertSeeText('Explore Packages')
            ->assertSeeText('My Payments')
            ->assertSeeText('My Invoices')
            ->assertSeeText('My Packages')
            ->assertDontSeeText('My Tour Benefits')
            ->assertDontSeeText('Tour Packages')
            ->assertSeeText('Help & Support', false)
            ->assertDontSeeText('Entry to Activation Wallet by Gateway')
            ->assertDontSeeText('Replied Ticket List')
            ->assertDontSee('admin.report.salary');
    }

    public function test_member_coming_soon_page_is_member_only_and_names_feature(): void
    {
        $member = Member::create([
            'member_id' => 'STSIDEBAR2',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Sidebar Member 2',
            'mobile_no' => '9000000001',
            'pan_card_no' => 'SIDEBAR0002',
            'email' => 'sidebar2@example.test',
            'status' => 'active',
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.coming-soon', ['feature' => 'ticket-entry']))
            ->assertOk()
            ->assertSeeText('Ticket Entry')
            ->assertSeeText('Coming Soon');

        $this->withSession(['member_context_id' => null])
            ->get(route('member.coming-soon', ['feature' => 'ticket-entry']))
            ->assertForbidden();
    }
}
