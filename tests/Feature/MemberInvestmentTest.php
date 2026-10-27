<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberInvestmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_only_an_investment_for_the_member_context(): void
    {
        $member = $this->member('ST100001', 'Rahul Das');
        $otherMember = $this->member('ST100002', 'Other Member');
        $this->memberSession($member);

        $this->post(route('member.investments.store'), [
            'member_id' => $otherMember->member_id,
            'investment_id' => 'FORGED-ID',
            'amount' => 2405,
        ])->assertRedirect(route('member.investments.entry'));

        $this->assertDatabaseHas('investments', [
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => '2405.0000',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('investments', ['member_id' => $otherMember->member_id]);
        $this->assertDatabaseMissing('investments', ['investment_id' => 'FORGED-ID']);
    }

    public function test_member_active_list_is_scoped_even_when_a_member_id_is_supplied(): void
    {
        $member = $this->member('ST100003', 'Rahul Das');
        $otherMember = $this->member('ST100004', 'Other Member');
        $this->investment($member, 'INV-OWN', '100.0000', 'active');
        $this->investment($otherMember, 'INV-OTHER', '200.0000', 'active');
        $this->memberSession($member);

        $this->get(route('member.investments.active', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText('INV-OWN')
            ->assertDontSeeText('INV-OTHER');
    }

    public function test_member_closed_list_is_scoped_to_the_member(): void
    {
        $member = $this->member('ST100005', 'Rahul Das');
        $otherMember = $this->member('ST100006', 'Other Member');
        $ownInvestment = $this->investment($member, 'INV-CLOSED-OWN', '100.0000', 'expired');
        $otherInvestment = $this->investment($otherMember, 'INV-CLOSED-OTHER', '200.0000', 'expired');
        $ownInvestment->forceFill(['closed_at' => now()])->save();
        $otherInvestment->forceFill(['closed_at' => now()])->save();
        $this->memberSession($member);

        $this->get(route('member.investments.closed', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText('INV-CLOSED-OWN')
            ->assertDontSeeText('INV-CLOSED-OTHER');
    }

    public function test_closed_list_displays_stored_closing_amount_and_active_investments_have_none(): void
    {
        $member = $this->member('ST100008', 'Rahul Das');
        $closed = $this->investment($member, 'INV-CLOSED-AMOUNT', '100.0000', 'expired');
        $closed->update([
            'closing_amount' => '300.0000',
            'closed_at' => now(),
        ]);
        $this->investment($member, 'INV-ACTIVE-AMOUNT', '200.0000', 'active');
        $this->memberSession($member);

        $this->get(route('member.investments.closed'))
            ->assertOk()
            ->assertSeeText('Closing Amount (USDT)')
            ->assertSeeText('USDT 300.0000')
            ->assertSeeText('INV-CLOSED-AMOUNT')
            ->assertDontSeeText('INV-ACTIVE-AMOUNT');

        $this->assertDatabaseHas('investments', [
            'investment_id' => 'INV-ACTIVE-AMOUNT',
            'closing_amount' => null,
        ]);
    }

    public function test_admin_can_see_an_investment_created_by_a_member(): void
    {
        $member = $this->member('ST100007', 'Rahul Das');
        $this->memberSession($member);

        $this->post(route('member.investments.store'), ['amount' => 100])->assertRedirect();
        $investmentId = Investment::query()->value('investment_id');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.investments.active-investments'))
            ->assertOk()
            ->assertSeeText($investmentId);
    }

    private function member(string $memberId, string $name): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '98765' . substr($memberId, -5),
            'pan_card_no' => 'ABCDE' . substr($memberId, -5),
            'email' => strtolower($memberId) . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);
    }

    private function investment(Member $member, string $investmentId, string $amount, string $status): Investment
    {
        $investment = Investment::create([
            'investment_id' => $investmentId,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'amount' => $amount,
            'status' => $status,
        ]);

        return $investment->forceFill([
            'created_at' => CarbonImmutable::parse('2026-08-25'),
            'updated_at' => CarbonImmutable::parse('2026-08-25'),
        ])->save() ? $investment->fresh() : $investment;
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }
}