<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function member(): Member
    {
        return Member::create([
            'member_id' => 'ST100001',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Rahul Das',
            'wallet_address' => '0xoriginal',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'password' => bcrypt('old-password'),
            'status' => 'inactive',
        ]);
    }

    public function test_profile_update_page_starts_without_a_member(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->get(route('admin.members.update'))
            ->assertOk()
            ->assertDontSee($member->member_id)
            ->assertDontSee($member->member_name)
            ->assertDontSee($member->email)
            ->assertDontSee('old-password');
    }

    public function test_fetch_returns_requested_member_details_without_password(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->getJson(route('admin.members.fetch-details', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJson([
                'member_id' => $member->member_id,
                'name' => $member->member_name,
                'email' => $member->email,
            ])
            ->assertJsonMissing(['password' => $member->password]);
    }

    public function test_missing_member_returns_member_not_found(): void
    {
        $this->signIn();

        $this->getJson(route('admin.members.fetch-details', ['member_id' => 'ST999999']))
            ->assertNotFound()
            ->assertJson(['message' => 'Member not found.']);
    }

    public function test_blank_password_keeps_existing_password_when_profile_is_updated(): void
    {
        $this->signIn();
        $member = $this->member();
        $originalPassword = $member->password;

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Updated',
            'wallet_address' => '0xupdated',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'password' => '',
        ])->assertRedirect(route('admin.members.update', ['member_id' => $member->member_id]));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Updated',
            'password' => $originalPassword,
        ]);

        $this->getJson(route('admin.members.fetch-details', ['member_id' => $member->member_id]))
            ->assertJsonPath('name', 'Rahul Updated')
            ->assertJsonMissingPath('password');
    }

    public function test_new_password_is_hashed_and_saved(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Das',
            'wallet_address' => '0xoriginal',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
            'password' => 'new-password',
        ])->assertRedirect(route('admin.members.update', ['member_id' => $member->member_id]));

        $member->refresh();

        $this->assertTrue(Hash::check('new-password', $member->password));
        $this->assertNotSame('new-password', $member->password);
    }

    public function test_update_uses_registration_validation_rules(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'member_name' => 'A',
            'mobile_no' => 'invalid',
            'pan_card_no' => 'SHORT',
            'email' => 'invalid-email',
            'password' => 'short',
        ])->assertSessionHasErrors([
            'member_name',
            'mobile_no',
            'email',
            'password',
        ]);

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Das',
            'mobile_no' => $member->mobile_no,
            'pan_card_no' => 'SHORT',
            'email' => $member->email,
        ])->assertSessionHasErrors(['pan_card_no']);

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Das',
        ]);
    }

    public function test_existing_member_values_do_not_trigger_duplicate_errors(): void
    {
        $this->signIn();
        $member = $this->member();

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Das Updated',
            'wallet_address' => '0xupdated',
            'mobile_no' => $member->mobile_no,
            'pan_card_no' => $member->pan_card_no,
            'email' => $member->email,
        ])->assertRedirect(route('admin.members.update', ['member_id' => $member->member_id]));

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Das Updated',
        ]);
    }

    public function test_mobile_and_pan_reuse_limits_are_preserved_on_update(): void
    {
        $this->signIn();
        $member = $this->member();

        foreach (range(1, 3) as $index) {
            Member::create([
                'member_id' => 'ST10000' . (1 + $index),
                'sponsor_id' => 'ST666666',
                'sponsor_name' => 'Admin',
                'member_name' => 'Other Member ' . $index,
                'mobile_no' => '9876543210',
                'pan_card_no' => 'ABCDE1234F',
                'email' => 'other' . $index . '@example.com',
                'status' => 'inactive',
            ]);
        }

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Das',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ABCDE1234F',
            'email' => 'rahul@example.com',
        ])->assertSessionHasErrors(['pan_card_no']);

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Das',
            'mobile_no' => '9876543210',
            'pan_card_no' => 'ZZZZZ9999Z',
            'email' => 'rahul@example.com',
        ])->assertSessionHasErrors(['mobile_no']);

        $this->assertDatabaseHas('members', [
            'member_id' => $member->member_id,
            'member_name' => 'Rahul Das',
        ]);
    }

    public function test_other_members_email_cannot_be_used_on_update(): void
    {
        $this->signIn();
        $member = $this->member();

        Member::create([
            'member_id' => 'ST100002',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Other Member',
            'mobile_no' => '9876543211',
            'pan_card_no' => 'ABCDE1234G',
            'email' => 'other@example.com',
            'status' => 'inactive',
        ]);

        $this->post(route('admin.members.update-member'), [
            'member_id' => $member->member_id,
            'sponsor_id' => 'ST666666',
            'member_name' => 'Rahul Das',
            'mobile_no' => $member->mobile_no,
            'pan_card_no' => $member->pan_card_no,
            'email' => 'other@example.com',
        ])->assertSessionHasErrors(['email']);
    }
}
