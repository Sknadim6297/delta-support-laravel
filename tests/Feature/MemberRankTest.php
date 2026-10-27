<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRankTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_own_rank_business_and_roadmap(): void
    {
        $member = $this->member('STRANK101', 'Rank Owner', 'ST666666');
        $child = $this->member('STRANK102', 'Rank Child', $member->member_id);
        $this->investment($child, 'INVRANK101', '6850.0000');
        $this->memberSession($member);

        $this->get(route('member.rank'))
            ->assertOk()
            ->assertSeeText('Silver')
            ->assertSeeText('USDT 6850.0000')
            ->assertSeeText('Levels Unlocked')
            ->assertSeeText('Gold')
            ->assertSeeText('USDT 5150.0000')
            ->assertSeeText('Rank Progress Roadmap');

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSeeText('Rank Summary')
            ->assertSeeText('USDT 6850.0000');
    }

    public function test_member_level_report_shows_server_computed_locked_levels(): void
    {
        $member = $this->member('STRANK103', 'Level Owner', 'ST666666');
        $child = $this->member('STRANK104', 'Level Child', $member->member_id);
        $this->investment($child, 'INVRANK102', '6000.0000');
        $this->memberSession($member);

        $this->get(route('member.reports.level-income'))
            ->assertOk()
            ->assertSeeText('L1')
            ->assertSeeText('L4')
            ->assertSeeText('L5')
            ->assertSeeText('Locked levels are not eligible for Level Commission')
            ->assertSee('level-locked', false)
            ->assertSee('level-unlocked', false);
    }

    public function test_member_cannot_change_rank_context_with_member_id_parameter(): void
    {
        $member = $this->member('STRANK105', 'Own Rank Member', 'ST666666');
        $other = $this->member('STRANK106', 'Other Rank Member', 'ST666666');
        $this->memberSession($member);

        $this->get(route('member.rank', ['member_id' => $other->member_id]))
            ->assertOk()
            ->assertSeeText('Own Rank Member')
            ->assertDontSeeText('Other Rank Member');
    }

    public function test_admin_member_panel_uses_selected_member_rank_context(): void
    {
        $member = $this->member('STRANK107', 'Selected Rank Member', 'ST666666');
        $child = $this->member('STRANK108', 'Selected Rank Child', $member->member_id);
        $this->investment($child, 'INVRANK103', '6000.0000');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.member-panel', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertSeeText('Selected Rank Member')
            ->assertSeeText('Rank Summary')
            ->assertSeeText('Silver');
    }

    private function member(string $id, string $name, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $id,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => $name,
            'mobile_no' => '98765' . substr($id, -5),
            'pan_card_no' => 'RANK' . substr($id, -6),
            'email' => strtolower($id) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function investment(Member $member, string $id, string $amount): Investment
    {
        return Investment::create([
            'investment_id' => $id,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => 'active',
        ]);
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }
}
