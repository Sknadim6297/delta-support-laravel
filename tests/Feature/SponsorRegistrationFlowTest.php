<?php

namespace Tests\Feature;

use App\Mail\MemberPasswordSetupMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SponsorRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registers_a_member_under_a_valid_sponsor_without_financial_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sponsor = $this->member('ST100001', 'Rahul Das', 'rahul@example.com', 'ST666666');
        $sponsor->update([
            'activation_wallet_amount' => '1.5000',
            'working_wallet_amount' => '2.0000',
            'roi_wallet_amount' => '3.0000',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.members.registration'))
            ->assertOk()
            ->assertSeeText('Member Registration')
            ->assertSeeText('A one-time password setup link is emailed to the member.')
            ->assertSee('name="_token"', false);

        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.members.store'), [
                'member_name' => 'New Member',
                'sponsor_id' => $sponsor->member_id,
                'email' => 'new@example.com',
                'mobile_no' => '9876543212',
                'pan_card_no' => 'ABCDE1234F',
                'password' => 'secret1',
                'password_confirmation' => 'secret1',
            ])
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionMissing('success')
            ->assertSessionMissing('mail_notice')
            ->assertSessionHas('registration_notice', function (array $notice): bool {
                return $notice['tone'] === 'warning'
                    && $notice['title'] === 'Registration successful'
                    && $notice['member_id'] !== ''
                    && str_contains($notice['email_status'], 'was not accepted')
                    && ! str_contains($notice['email_status'], 'secret1')
                    && ! str_contains(strtolower($notice['email_status']), 'password was sent');
            });

        Mail::assertSent(MemberPasswordSetupMail::class, function (MemberPasswordSetupMail $mail): bool {
            return $mail->hasTo('new@example.com')
                && str_contains($mail->setupUrl, '/member/password-setup/')
                && ! str_contains($mail->setupUrl, 'secret1');
        });

        $member = Member::where('email', 'new@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ST\d{6}$/', $member->member_id);
        $this->assertNotSame($sponsor->member_id, $member->member_id);
        $this->assertSame($sponsor->member_id, $member->sponsor_id);
        $this->assertSame('Rahul Das', $member->sponsor_name);
        $this->assertSame('inactive', $member->status);
        $this->assertTrue(Hash::check('secret1', $member->password));
        $this->assertNotSame('secret1', $member->password);
        $this->assertLessThanOrEqual(0, (float) $member->activation_wallet_amount);
        $this->assertLessThanOrEqual(0, (float) $member->working_wallet_amount);
        $this->assertLessThanOrEqual(0, (float) $member->roi_wallet_amount);

        $sponsor->refresh();
        $this->assertSame('1.5000', (string) $sponsor->activation_wallet_amount);
        $this->assertSame('2.0000', (string) $sponsor->working_wallet_amount);
        $this->assertSame('3.0000', (string) $sponsor->roi_wallet_amount);
        $this->assertSame(0, DB::table('roi_transactions')->count());
        $this->assertSame(0, DB::table('level_commission_transactions')->count());
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('package_purchases')->count());
        $this->assertSame(0, DB::table('wallet_ledger_entries')->count());

        $shown = $this->actingAs($admin)->get(route('admin.members.registration'));
        $shown->assertOk()
            ->assertSeeText('Registration successful')
            ->assertSeeText('Member ID: '.$member->member_id)
            ->assertSeeText('was not accepted')
            ->assertDontSeeText('Member registration submitted successfully');
        $this->assertSame(1, substr_count($shown->getContent(), 'Registration successful'));
        $this->assertSame(1, substr_count($shown->getContent(), 'was not accepted'));

        $this->actingAs($admin)
            ->get(route('admin.members.registration'))
            ->assertOk()
            ->assertDontSeeText('Registration successful');

        $this->actingAs($admin)
            ->get(route('admin.members.directory', ['q' => '9876543212']))
            ->assertOk()
            ->assertSeeText($member->member_id)
            ->assertSeeText('New Member')
            ->assertSeeText('new@example.com')
            ->assertSeeText('Rahul Das')
            ->assertSeeText('Inactive')
            ->assertSeeText('View / Edit');

        $this->actingAs($admin)
            ->get(route('admin.genealogy.tree-view', ['member_id' => $sponsor->member_id]))
            ->assertOk()
            ->assertSeeText('New Member')
            ->assertSeeText($member->member_id);

        $this->getJson(route('admin.members.fetch-details', ['member_id' => $member->member_id]))
            ->assertOk()
            ->assertJsonPath('account_status', 'inactive')
            ->assertJsonPath('package_status', 'Unpaid')
            ->assertJsonPath('sponsor_name', 'Rahul Das');
    }

    public function test_admin_cannot_register_with_an_invalid_sponsor(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->from(route('admin.members.registration'))
            ->post(route('admin.members.store'), [
                'member_name' => 'New Member',
                'sponsor_id' => 'ST999999',
                'email' => 'new@example.com',
                'mobile_no' => '9876543212',
                'pan_card_no' => 'ABCDE1234F',
            ])
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionHasErrors(['sponsor_id'])
            ->assertSessionMissing('registration_notice')
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('members', ['email' => 'new@example.com']);

        $this->get(route('admin.members.registration'))
            ->assertOk()
            ->assertSeeText('Invalid Sponsor ID. Enter an existing Member ID or leave it blank to use the default sponsor.')
            ->assertDontSeeText('Registration successful');

        $this->get(route('admin.members.registration'))
            ->assertOk()
            ->assertDontSeeText('Enter an existing Member ID or leave it blank to use the default sponsor.');
    }

    public function test_member_registers_a_direct_referral_and_cannot_spoof_the_sponsor(): void
    {
        $sponsor = $this->member('ST100001', 'Rahul Das', 'rahul@example.com', 'ST666666');
        $other = $this->member('ST100002', 'Other Sponsor', 'other@example.com', 'ST666666');

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.registration'))
            ->assertOk()
            ->assertSeeText('Register New Member')
            ->assertSeeText('A one-time password setup link is emailed to the member.')
            ->assertSee('value="ST100001"', false)
            ->assertSee('readonly', false)
            ->assertSee('name="_token"', false);

        Mail::fake();

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->post(route('member.registration.store'), [
                'member_name' => 'Direct Member',
                'sponsor_id' => $sponsor->member_id,
                'email' => 'direct@example.com',
                'mobile_no' => '9876543213',
                'pan_card_no' => 'ABCDE1234F',
            ])
            ->assertRedirect(route('member.registration'))
            ->assertSessionHas('mail_notice', function (string $message): bool {
                return str_contains($message, 'was not accepted')
                    && ! str_contains(strtolower($message), 'password was sent');
            });

        $child = Member::where('email', 'direct@example.com')->firstOrFail();
        $before = Member::query()->count();
        $setupUrl = null;
        Mail::assertSent(MemberPasswordSetupMail::class, function (MemberPasswordSetupMail $mail) use ($child, &$setupUrl): bool {
            $setupUrl = $mail->setupUrl;

            return $mail->hasTo('direct@example.com') && $mail->memberId === $child->member_id;
        });
        $token = basename((string) parse_url((string) $setupUrl, PHP_URL_PATH));
        $this->post(route('member.password-setup.store', ['token' => $token]), [
            'password' => 'chosen-pass',
            'password_confirmation' => 'chosen-pass',
        ])->assertRedirect(route('login'));
        $child->refresh();
        $this->assertTrue(Hash::check('chosen-pass', $child->password));
        $this->post(route('member.password-setup.store', ['token' => $token]), [
            'password' => 'another-pass',
            'password_confirmation' => 'another-pass',
        ])->assertSessionHasErrors('password');
        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->post(route('member.registration.password-setup-email'), ['member_id' => $child->member_id])
            ->assertSessionHas('success', 'No new member was created.');
        $this->assertSame($before, Member::query()->count());
        $this->assertSame($sponsor->member_id, $child->sponsor_id);
        $this->assertSame('inactive', $child->status);
        $this->assertNotSame($sponsor->member_id, $child->member_id);

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->from(route('member.registration'))
            ->post(route('member.registration.store'), [
                'member_name' => 'Spoofed Member',
                'sponsor_id' => $other->member_id,
                'email' => 'spoof@example.com',
                'mobile_no' => '9876543214',
            ])
            ->assertRedirect(route('member.registration'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->assertDatabaseMissing('members', ['email' => 'spoof@example.com']);

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.team.direct'))
            ->assertOk()
            ->assertSeeText('Direct Member');

        $this->withSession(['member_context_id' => $sponsor->member_id])
            ->get(route('member.genealogy.tree-view'))
            ->assertOk()
            ->assertSeeText($child->member_id)
            ->assertSeeText('Direct Member');
    }

    public function test_public_referral_link_registers_and_missing_or_invalid_sponsor_is_rejected(): void
    {
        Mail::fake();
        $sponsor = $this->member('ST100001', 'Rahul Das', 'rahul@example.com', 'ST666666');

        $this->get(route('member.register', ['sponsor_id' => $sponsor->member_id]))
            ->assertOk()
            ->assertSee('value="' . $sponsor->member_id . '"', false)
            ->assertSee('name="_token"', false);

        $this->post(route('member.register.store'), [
            'member_name' => 'Public Member',
            'sponsor_id' => $sponsor->member_id,
            'mobile_no' => '9876543215',
            'email' => 'public@example.com',
        ])->assertRedirect(route('member.register'));

        $member = Member::where('email', 'public@example.com')->firstOrFail();
        $this->assertSame($sponsor->member_id, $member->sponsor_id);
        $this->assertSame('Rahul Das', $member->sponsor_name);
        $this->assertSame('inactive', $member->status);
        $this->assertNull($member->password);
        Mail::assertSent(MemberPasswordSetupMail::class, function (MemberPasswordSetupMail $mail) use ($member): bool {
            return $mail->hasTo('public@example.com') && $mail->memberId === $member->member_id;
        });
        $this->assertMatchesRegularExpression('/^ST\d{6}$/', $member->member_id);

        $this->from(route('member.register'))
            ->post(route('member.register.store'), [
                'member_name' => 'No Sponsor',
                'mobile_no' => '9876543216',
                'email' => 'nosponsor@example.com',
            ])
            ->assertRedirect(route('member.register'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->from(route('member.register'))
            ->post(route('member.register.store'), [
                'member_name' => 'Bad Sponsor',
                'sponsor_id' => 'ST999999',
                'mobile_no' => '9876543217',
                'email' => 'badsponsor@example.com',
            ])
            ->assertRedirect(route('member.register'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->from(route('member.register'))
            ->post(route('member.register.store'), [
                'member_name' => 'Duplicate Email',
                'sponsor_id' => $sponsor->member_id,
                'mobile_no' => '9876543218',
                'email' => 'public@example.com',
            ])
            ->assertRedirect(route('member.register'))
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseMissing('members', ['email' => 'nosponsor@example.com']);
        $this->assertDatabaseMissing('members', ['sponsor_id' => 'ST666666', 'email' => 'nosponsor@example.com']);
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('roi_transactions')->count());
    }

    public function test_registration_rejects_duplicate_mobile_after_three_uses_and_keeps_member_ids_unique(): void
    {
        $sponsor = $this->member('ST100010', 'Rahul Das', 'rahul@example.com', 'ST666666');
        $admin = User::factory()->create(['is_admin' => true]);
        $ids = [];

        foreach (range(1, 3) as $index) {
            $this->actingAs($admin)->post(route('admin.members.store'), [
                'member_name' => 'Reuse Member',
                'sponsor_id' => $sponsor->member_id,
                'email' => 'reuse' . $index . '@example.com',
                'mobile_no' => '9000000000',
                'pan_card_no' => 'ABCDE1234' . $index,
            ])->assertRedirect(route('admin.members.registration'));

            $ids[] = Member::where('email', 'reuse' . $index . '@example.com')->value('member_id');
        }

        $this->assertCount(3, array_unique($ids));

        $this->actingAs($admin)
            ->from(route('admin.members.registration'))
            ->post(route('admin.members.store'), [
                'member_name' => 'Reuse Member',
                'sponsor_id' => $sponsor->member_id,
                'email' => 'reuse4@example.com',
                'mobile_no' => '9000000000',
                'pan_card_no' => 'ABCDE12340',
            ])
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionHasErrors(['mobile_no']);
    }

    public function test_registration_routes_are_protected_by_role(): void
    {
        $this->post(route('admin.members.store'), [
            'member_name' => 'Guest Member',
            'email' => 'guest@example.com',
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
        ])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.members.store'), [
                'member_name' => 'Staff Member',
                'email' => 'staff@example.com',
                'mobile_no' => '9876543212',
                'pan_card_no' => 'ABCDE1234F',
            ])
            ->assertForbidden();

        $this->post(route('member.registration.store'), [
            'member_name' => 'No Session',
            'sponsor_id' => 'ST100001',
            'email' => 'session@example.com',
            'mobile_no' => '9876543212',
        ])->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    private function member(string $memberId, string $name, string $email, string $sponsorId): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '91111' . substr($memberId, -5),
            'pan_card_no' => 'PQRST' . substr($memberId, -5),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
