<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberAccountReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_name_keeps_internal_spaces_and_accepts_unicode(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sponsor = $this->member('ST100001', 'Rahul Das', 'rahul@example.com');

        $this->actingAs($admin)->post(route('admin.members.store'), [
            'member_name' => '  Md Arif Hossain  ',
            'sponsor_id' => $sponsor->member_id,
            'email' => 'arif@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
        ])->assertRedirect(route('admin.members.registration'));

        $this->assertDatabaseHas('members', [
            'email' => 'arif@example.com',
            'member_name' => 'Md Arif Hossain',
        ]);

        $this->actingAs($admin)->post(route('admin.members.store'), [
            'member_name' => 'José García',
            'sponsor_id' => $sponsor->member_id,
            'email' => 'jose@example.com',
            'mobile_no' => '9876543213',
            'pan_card_no' => 'ABCDE1234G',
        ])->assertRedirect(route('admin.members.registration'));

        $this->assertDatabaseHas('members', [
            'email' => 'jose@example.com',
            'member_name' => 'José García',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.members.registration'))
            ->post(route('admin.members.store'), [
                'member_name' => '<script>alert(1)</script>',
                'sponsor_id' => $sponsor->member_id,
                'email' => 'bad@example.com',
                'mobile_no' => '9876543214',
                'pan_card_no' => 'ABCDE1234H',
            ])
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionHasErrors(['member_name']);

        $registration = file_get_contents(resource_path('views/admin/members/member_registration.blade.php'));
        $memberRegistration = file_get_contents(resource_path('views/member/create-member/new-member-registration.blade.php'));
        $this->assertIsString($registration);
        $this->assertIsString($memberRegistration);
        $this->assertMatchesRegularExpression("/memberNameInput\\?\\.addEventListener\\('input', function \\(\\) \\{\\s*this\\.value = this\\.value\\.replace\\(\\/\\^\\\\s\\+\\/, ''\\);/s", $registration);
        $this->assertMatchesRegularExpression("/memberNameInput\\?\\.addEventListener\\('input', function \\(\\) \\{\\s*this\\.value = this\\.value\\.replace\\(\\/\\^\\\\s\\+\\/, ''\\);/s", $memberRegistration);
    }

    public function test_member_registration_matches_admin_fields_and_locks_the_sponsor(): void
    {
        $sponsor = $this->member('ST100001', 'Sk Nadim', 'rahul@example.com', 'inactive');
        $other = $this->member('ST100002', 'Other Sponsor', 'other@example.com');
        $this->memberSession($sponsor);

        $this->get(route('member.registration'))
            ->assertOk()
            ->assertSeeText('Register New Member')
            ->assertSeeText('Member Name')
            ->assertSeeText('Sponsor ID')
            ->assertSeeText('Sponsor Name')
            ->assertSeeText('Email ID')
            ->assertSeeText('Mobile No')
            ->assertSeeText('PAN Card Number')
            ->assertSeeText('Wallet Address')
            ->assertSeeText('Confirm Password')
            ->assertSee('value="ST100001"', false)
            ->assertSee('value="Sk Nadim"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('type="button">Show Password', false);

        $this->post(route('member.registration.store'), [
            'member_name' => 'Rimaa Das',
            'sponsor_id' => $other->member_id,
            'email' => 'rimaa@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertRedirect(route('member.registration'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->assertDatabaseMissing('members', ['email' => 'rimaa@example.com']);

        $this->post(route('member.registration.store'), [
            'member_name' => 'Rimaa Das',
            'sponsor_id' => $sponsor->member_id,
            'email' => 'rimaa@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
            'wallet_address' => '0xwallet',
            'password' => 'secret1',
            'password_confirmation' => 'different',
        ])->assertRedirect(route('member.registration'))
            ->assertSessionHasErrors(['password']);

        $this->post(route('member.registration.store'), [
            'member_name' => 'Rimaa Das',
            'sponsor_id' => $sponsor->member_id,
            'email' => 'rahul@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertRedirect(route('member.registration'))
            ->assertSessionHasErrors(['email']);

        $this->post(route('member.registration.store'), [
            'member_name' => 'Rimaa Das',
            'sponsor_id' => $sponsor->member_id,
            'email' => 'rimaa@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
            'wallet_address' => '0xwallet',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertRedirect(route('member.registration'))
            ->assertSessionHas('registration_receipt');

        $child = Member::query()->where('email', 'rimaa@example.com')->firstOrFail();
        $this->assertSame('Rimaa Das', $child->member_name);
        $this->assertSame($sponsor->member_id, $child->sponsor_id);
        $this->assertSame('Sk Nadim', $child->sponsor_name);
        $this->assertSame('inactive', $child->status);
        $this->assertSame('ABCDE1234F', $child->pan_card_no);
        $this->assertSame('0xwallet', $child->wallet_address);
        $this->assertTrue(Hash::check('secret1', $child->password));
        $this->assertNotSame($sponsor->member_id, $child->member_id);
        $this->assertMatchesRegularExpression('/^ST\d{6}$/', $child->member_id);
        $this->assertSame(0, DB::table('roi_transactions')->count());
        $this->assertSame(0, DB::table('level_commission_transactions')->count());
        $this->assertSame(0, DB::table('package_purchases')->count());
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('wallet_ledger_entries')->count());

        $this->get(route('member.registration'))
            ->assertOk()
            ->assertSeeText($child->member_id)
            ->assertSeeText('Rimaa Das')
            ->assertSeeText('Inactive')
            ->assertDontSeeText('secret1');

        $this->post(route('member.login.store'), [
            'member_id' => $child->member_id,
            'password' => 'secret1',
        ])->assertRedirect(route('member.dashboard'));
    }

    public function test_profile_updates_are_limited_to_the_authenticated_member(): void
    {
        $member = $this->member('ST100001', 'Rahul Das', 'rahul@example.com');
        $member->update([
            'password' => Hash::make('secret-secret'),
            'pan_card_no' => 'ABCDE1234F',
            'wallet_address' => '0xrahul',
            'activation_wallet_amount' => '4.0000',
            'working_wallet_amount' => '5.0000',
            'roi_wallet_amount' => '6.0000',
        ]);
        $other = $this->member('ST100002', 'Other Member', 'other@example.com');
        $this->memberSession($member);

        $this->get(route('member.profile'))
            ->assertOk()
            ->assertSeeText('Rahul Das')
            ->assertSeeText('ST100001')
            ->assertSeeText('0xrahul')
            ->assertSeeText('ABCDE1234F')
            ->assertDontSeeText('other@example.com');

        $this->from(route('member.profile'))
            ->put(route('member.profile.update'), [
                'member_id' => $other->member_id,
                'member_name' => 'Abdul Rahman',
                'email' => 'taken@example.com',
                'mobile_no' => '9876543212',
            ])
            ->assertRedirect(route('member.profile'))
            ->assertSessionHasErrors(['current_password']);

        $this->assertSame('Rahul Das', $member->fresh()->member_name);
        $this->assertSame('Other Member', $other->fresh()->member_name);

        $this->put(route('member.profile.update'), [
            'member_id' => $other->member_id,
            'member_name' => 'Abdul Rahman',
            'email' => 'rahul@example.com',
            'mobile_no' => '9876543210',
            'wallet_address' => '0xchanged',
            'pan_card_no' => 'ZZZZZ9999Z',
            'status' => 'active',
            'activation_wallet_amount' => '99',
        ])->assertSessionHasErrors(['wallet_address', 'pan_card_no']);

        $member->refresh();
        $this->assertSame('Rahul Das', $member->member_name);
        $this->assertSame('0xrahul', $member->wallet_address);
        $this->assertSame('ABCDE1234F', $member->pan_card_no);
        $this->assertSame('inactive', $member->status);
        $this->assertSame('4.0000', (string) $member->activation_wallet_amount);

        $this->put(route('member.profile.update'), [
            'member_name' => 'Abdul Rahman',
            'email' => 'abdul@example.com',
            'mobile_no' => '9876543299',
            'current_password' => 'secret-secret',
            'pan_card_no' => 'ABCDE1234F',
            'wallet_address' => '0xrahul',
        ])->assertRedirect(route('member.profile'));

        $member->refresh();
        $this->assertSame('Abdul Rahman', $member->member_name);
        $this->assertSame('abdul@example.com', $member->email);
        $this->assertSame('9876543299', $member->mobile_no);
        $this->assertSame('ST100001', $member->member_id);
        $this->assertSame('0xrahul', $member->wallet_address);
        $this->assertSame('inactive', $member->status);
        $this->assertSame('other@example.com', $other->fresh()->email);
    }

    public function test_referral_link_uses_the_member_and_rejects_sponsor_substitution(): void
    {
        Mail::fake();
        $sponsor = $this->member('ST100003', 'Rimaa Das', 'rimaa@example.com');
        $other = $this->member('ST100004', 'Other Sponsor', 'other@example.com');
        $this->memberSession($sponsor);

        $link = route('member.register', ['sponsor_id' => $sponsor->member_id]);

        $this->get(route('member.referral'))
            ->assertOk()
            ->assertSeeText('ST100003')
            ->assertSeeText('Rimaa Das')
            ->assertSee($link, false)
            ->assertSee('Copy Link', false)
            ->assertSee('Share Link', false)
            ->assertSee(route('member.registration'), false);

        $this->get(route('member.register', ['sponsor_id' => $sponsor->member_id]))
            ->assertOk()
            ->assertSee('value="'.$sponsor->member_id.'"', false)
            ->assertSee('value="Rimaa Das"', false)
            ->assertSee('readonly', false);

        $this->get(route('member.register', ['sponsor_id' => 'ST999999']))
            ->assertOk()
            ->assertSee('This referral link is invalid', false)
            ->assertSee('value="ST999999"', false)
            ->assertDontSee('value="ST666666"', false);

        $this->get(route('member.register', ['sponsor_id' => $sponsor->member_id]));

        $this->from(route('member.register', ['sponsor_id' => $sponsor->member_id]))
            ->post(route('member.register.store'), [
                'member_name' => 'Sk Nadim',
                'sponsor_id' => $other->member_id,
                'mobile_no' => '9876543215',
                'email' => 'spoof@example.com',
            ])
            ->assertRedirect(route('member.register', ['sponsor_id' => $sponsor->member_id]))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->assertDatabaseMissing('members', ['email' => 'spoof@example.com']);

        $this->post(route('member.register.store'), [
            'member_name' => 'Sk Nadim',
            'sponsor_id' => $sponsor->member_id,
            'mobile_no' => '9876543215',
            'email' => 'public-referral@example.com',
        ])->assertRedirect(route('member.register'));

        $child = Member::query()->where('email', 'public-referral@example.com')->firstOrFail();
        $this->assertSame($sponsor->member_id, $child->sponsor_id);
        $this->assertSame('Rimaa Das', $child->sponsor_name);
        $this->assertSame('Sk Nadim', $child->member_name);
        $this->assertSame('inactive', $child->status);
    }

    private function memberSession(Member $member): void
    {
        $this->withSession(['member_context_id' => $member->member_id]);
    }

    private function member(string $memberId, string $name, string $email, string $status = 'inactive'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '90000'.substr($memberId, -5),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => $status,
        ]);
    }
}
