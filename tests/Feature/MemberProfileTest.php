<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_only_their_own_profile(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com', '0xrahul');
        $otherMember = $this->createMember('ST100002', 'Other Member', 'other@example.com', '0xother');
        $this->memberSession($member);

        $this->get(route('member.profile', ['member_id' => $otherMember->member_id]))
            ->assertOk()
            ->assertSeeText('Rahul Das')
            ->assertSeeText('rahul@example.com')
            ->assertSeeText('0xrahul')
            ->assertDontSeeText('other@example.com')
            ->assertDontSeeText('0xother');
    }

    public function test_member_can_update_allowed_profile_fields(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $this->memberSession($member);

        $member->update(['password' => Hash::make('secret-secret')]);

        $this->get(route('member.profile.edit'))
            ->assertOk()
            ->assertSee('BEP20 Wallet Address', false)
            ->assertSee('until wallet verification is available', false);

        $this->put(route('member.profile.update'), [
            'member_name' => 'Rahul Updated',
            'email' => 'updated@example.com',
            'mobile_no' => '9876543212',
            'current_password' => 'secret-secret',
            'pan_card_no' => $member->pan_card_no,
            'wallet_address' => $member->wallet_address,
        ])->assertRedirect(route('member.profile'));

        $this->assertDatabaseHas('members', [
            'member_id' => 'ST100001',
            'member_name' => 'Rahul Updated',
            'email' => 'updated@example.com',
            'mobile_no' => '9876543212',
            'wallet_address' => null,
        ]);
    }

    public function test_member_cannot_change_member_id_sponsor_or_status(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $this->memberSession($member);

        $this->put(route('member.profile.update'), [
            'member_id' => 'ST999999',
            'member_name' => 'Rahul Updated',
            'email' => 'rahul@example.com',
            'mobile_no' => '9876543210',
            'sponsor_id' => 'ST999998',
            'sponsor_name' => 'Forged Sponsor',
            'status' => 'active',
        ])->assertRedirect(route('member.profile'));

        $this->assertDatabaseHas('members', [
            'member_id' => 'ST100001',
            'member_name' => 'Rahul Updated',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseMissing('members', ['member_id' => 'ST999999']);
    }

    public function test_member_profile_validation_matches_existing_rules(): void
    {
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');
        $this->memberSession($member);

        $this->put(route('member.profile.update'), [
            'member_name' => 'A',
            'email' => 'invalid-email',
            'mobile_no' => 'invalid',
            'wallet_address' => str_repeat('x', 256),
        ])->assertSessionHasErrors(['member_name', 'email', 'mobile_no', 'wallet_address']);
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }

    private function createMember(string $memberId, string $name, string $email, ?string $wallet = null): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'wallet_address' => $wallet,
            'mobile_no' => '9876543210',
            'email' => $email,
            'status' => 'inactive',
        ]);
    }
}
