<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberGenealogyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_tree_view_uses_authenticated_member_as_root(): void
    {
        $member = $this->member('ST100001', 'Root Member', 'ST666666');
        $child = $this->member('ST100002', 'Child Member', $member->member_id);
        $otherRoot = $this->member('ST100003', 'Other Root', 'ST666666');
        $this->memberSession($member);

        $response = $this->get(route('member.genealogy.tree-view', ['member_id' => $otherRoot->member_id]));

        $response->assertOk()
            ->assertSeeText($member->member_id)
            ->assertSeeText($child->member_id)
            ->assertSeeText('Expand All')
            ->assertSeeText('Fit Tree')
            ->assertSee('data-tree-toggle', false)
            ->assertSee('status-active', false)
            ->assertSeeText('1 child')
            ->assertDontSeeText($otherRoot->member_id);

        $this->assertStringNotContainsString('id="memberTreeId" readonly', $response->getContent());
        $this->assertStringContainsString('id="memberTreeId"', $response->getContent());
    }

    public function test_member_level_results_only_include_authenticated_members_downline(): void
    {
        $member = $this->member('ST100004', 'Root Member', 'ST666666');
        $child = $this->member('ST100005', 'Child Member', $member->member_id);
        $grandchild = $this->member('ST100006', 'Grandchild Member', $child->member_id);
        $otherRoot = $this->member('ST100007', 'Other Root', 'ST666666');
        $this->member('ST100008', 'Other Child', $otherRoot->member_id);
        $this->memberSession($member);

        $this->getJson(route('member.genealogy.level-view.members', [
            'member_id' => $otherRoot->member_id,
        ]))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['member_id' => $child->member_id, 'level' => 1])
            ->assertJsonFragment(['member_id' => $grandchild->member_id, 'level' => 2])
            ->assertJsonMissing(['member_id' => $otherRoot->member_id])
            ->assertJsonMissing(['member_id' => 'ST100008']);
    }

    public function test_member_level_filter_stays_scoped_to_authenticated_downline(): void
    {
        $member = $this->member('ST100009', 'Root Member', 'ST666666');
        $child = $this->member('ST100010', 'Child Member', $member->member_id);
        $this->member('ST100011', 'Grandchild Member', $child->member_id);
        $this->memberSession($member);

        $this->getJson(route('member.genealogy.level-view.members', ['level' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['member_id' => $child->member_id, 'level' => 1]);
    }

    public function test_member_tree_view_allows_searching_authorized_downline_member(): void
    {
        $member = $this->member('ST100012', 'Root Member', 'ST666666');
        $child = $this->member('ST100013', 'Child Member', $member->member_id);
        $otherRoot = $this->member('ST100014', 'Other Root', 'ST666666');
        $this->member('ST100015', 'Other Child', $otherRoot->member_id);
        $this->memberSession($member);

        $this->get(route('member.genealogy.tree-view', ['member_id' => $child->member_id]))
            ->assertOk()
            ->assertSeeText($child->member_id)
            ->assertSeeText($child->member_name)
            ->assertDontSeeText($otherRoot->member_id);
    }

    public function test_tree_children_load_only_the_next_downline_level(): void
    {
        $member = $this->member('ST100020', 'Root Member', 'ST666666');
        $child = $this->member('ST100021', 'Child Member', $member->member_id);
        $grandchild = $this->member('ST100022', 'Grandchild Member', $child->member_id);
        $other = $this->member('ST100023', 'Other Root', 'ST666666');
        $this->memberSession($member);

        $this->get(route('member.genealogy.tree-view'))
            ->assertOk()
            ->assertSeeText($child->member_id)
            ->assertDontSeeText($grandchild->member_id);

        $this->getJson(route('member.genealogy.tree-children', ['member_id' => $child->member_id]))
            ->assertOk()
            ->assertSee($grandchild->member_id, false)
            ->assertDontSee($grandchild->email, false);

        $this->getJson(route('member.genealogy.tree-children', ['member_id' => $other->member_id]))
            ->assertNotFound();
    }

    public function test_member_genealogy_routes_require_member_context(): void
    {
        $this->get(route('member.genealogy.tree-view'))->assertForbidden();
        $this->get(route('member.genealogy.level-view'))->assertForbidden();
        $this->getJson(route('member.genealogy.level-view.members'))->assertForbidden();
    }

    private function member(string $memberId, string $name, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Sponsor',
            'member_name' => $name,
            'mobile_no' => '98765' . substr($memberId, -5),
            'pan_card_no' => 'ABCDE' . substr($memberId, -5),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }
}
