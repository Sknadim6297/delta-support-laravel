<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_selected_members_panel_data(): void
    {
        $rahul = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $arif = $this->createMember('ST100002', 'Arif Khan', 'arif@example.com');

        Investment::create([
            'investment_id' => 'INV-RAHUL',
            'member_id' => $rahul->member_id,
            'member_name' => $rahul->member_name,
            'amount' => '100.0000',
            'status' => 'active',
        ]);
        Investment::create([
            'investment_id' => 'INV-ARIF',
            'member_id' => $arif->member_id,
            'member_name' => $arif->member_name,
            'amount' => '900.0000',
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('admin.member-panel', ['member_id' => $rahul->member_id]))
            ->assertOk()
            ->assertSeeText('Rahul Das')
            ->assertSeeText('USDT 100.0000')
            ->assertDontSeeText('Arif Khan');

        $this->get(route('admin.member-panel', ['member_id' => $arif->member_id]))
            ->assertOk()
            ->assertSeeText('Arif Khan')
            ->assertSeeText('USDT 900.0000')
            ->assertDontSeeText('Rahul Das');
    }

    public function test_non_admin_cannot_open_a_member_panel(): void
    {
        $member = $this->createMember('ST100003', 'Member User', 'member@example.com');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.member-panel', ['member_id' => $member->member_id]))
            ->assertForbidden();
    }

    public function test_admin_without_member_context_is_sent_to_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('member.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_logout_from_member_area_without_member_context(): void
    {
        $member = $this->createMember('ST100004', 'Preview Member', 'preview@example.com');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->withSession(['member_context_id' => $member->member_id]);

        $this->post(route('member.logout'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_member_panel_logout_for_st100047_clears_preview_and_admin_session(): void
    {
        $member = $this->createMember('ST100047', 'Preview Member', 'preview047@example.com');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('admin.member-panel', ['member_id' => 'ST100047']))
            ->assertOk();

        $this->post(route('member.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(session()->has('member_context_id'));
        $this->assertDatabaseHas('members', ['member_id' => $member->member_id]);
    }

    public function test_member_logout_requires_post(): void
    {
        $member = $this->createMember('ST100005', 'Logout Member', 'logout@example.com');
        $this->withSession(['member_context_id' => $member->member_id]);

        $this->get(route('member.logout'))
            ->assertMethodNotAllowed();

        $this->assertTrue(session()->has('member_context_id'));
    }

    private function createMember(string $memberId, string $name, string $email): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
