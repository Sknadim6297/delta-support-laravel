<?php

namespace Tests\Feature;

use App\Mail\MemberPasswordOtpMail;
use App\Mail\MemberPasswordSetupMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicMemberAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_and_public_sponsor_lookup_work(): void
    {
        $sponsor = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');

        $this->get(route('member.register'))
            ->assertOk()
            ->assertSeeText('Create Your Account')
            ->assertSee('value="ST100000"', false);

        $this->getJson(route('member.register.check-sponsor', ['sponsor_id' => $sponsor->member_id]))
            ->assertOk()
            ->assertJson([
                'exists' => true,
                'sponsor_name' => 'Rahul Das',
            ]);
    }

    public function test_public_registration_uses_shared_table_and_login_password(): void
    {
        Mail::fake();
        $sponsor = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');

        $this->post(route('member.register.store'), [
            'member_name' => 'New Member',
            'sponsor_id' => $sponsor->member_id,
            'mobile_no' => '9876543212',
            'email' => 'new@example.com',
        ])->assertRedirect(route('member.register'));

        $member = Member::where('email', 'new@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^ST\d{6}$/', $member->member_id);
        $this->assertNotSame($sponsor->member_id, $member->member_id);
        $this->assertSame('Rahul Das', $member->sponsor_name);
        $this->assertSame('inactive', $member->status);
        $this->assertNull($member->password);
        $setupUrl = null;
        Mail::assertSent(MemberPasswordSetupMail::class, function (MemberPasswordSetupMail $mail) use (&$setupUrl): bool {
            $setupUrl = $mail->setupUrl;

            return str_contains($mail->setupUrl, '/member/password-setup/');
        });
        $token = basename((string) parse_url((string) $setupUrl, PHP_URL_PATH));
        $this->post(route('member.password-setup.store', ['token' => $token]), [
            'password' => 'chosen-pass',
            'password_confirmation' => 'chosen-pass',
        ])->assertRedirect(route('login'));
        $member->refresh();
        $this->assertTrue(Hash::check('chosen-pass', $member->password));

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.members.inactive'))
            ->assertOk()
            ->assertSeeText($member->member_id)
            ->assertSeeText('New Member')
            ->assertSeeText('Rahul Das')
            ->assertSeeText('new@example.com')
            ->assertSeeText('9876543212');

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INV' . $member->member_id,
            'member_id' => $member->member_id,
            'amount' => 99,
        ])->assertSessionHasErrors('amount');

        $member->refresh();
        $this->assertSame('inactive', $member->status);
        $this->assertDatabaseCount('investments', 0);

        $this->post(route('admin.investments.store'), [
            'investment_id' => 'INV' . $member->member_id,
            'member_id' => $member->member_id,
            'amount' => 100,
        ])->assertRedirect(route('admin.investments.entry'));

        $member->refresh();
        $this->assertSame('active', $member->status);
        $this->assertDatabaseHas('investments', [
            'member_id' => $member->member_id,
            'amount' => 100,
            'status' => 'active',
        ]);

        $this->post(route('login.submit'), [
            'member_id' => $member->member_id,
            'password' => 'chosen-pass',
        ])->assertRedirect(route('member.dashboard'));
    }

    public function test_invalid_sponsor_and_duplicate_email_are_rejected(): void
    {
        $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');

        $this->from(route('member.register'))
            ->post(route('member.register.store'), [
                'member_name' => 'New Member',
                'sponsor_id' => 'ST999999',
                'mobile_no' => '9876543212',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('member.register'))
            ->assertSessionHasErrors(['sponsor_id']);

        $this->from(route('member.register'))
            ->post(route('member.register.store'), [
                'member_name' => 'Another Member',
                'sponsor_id' => 'ST100001',
                'mobile_no' => '9876543213',
                'email' => 'rahul@example.com',
            ])
            ->assertRedirect(route('member.register'))
            ->assertSessionHasErrors(['email']);
    }

    public function test_default_admin_sponsor_id_is_accepted(): void
    {
        Mail::fake();

        $this->post(route('member.register.store'), [
            'member_name' => 'New Member',
            'sponsor_id' => 'ST666666',
            'mobile_no' => '9876543212',
            'email' => 'new@example.com',
        ])->assertRedirect(route('member.register'));

        $this->assertDatabaseHas('members', [
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'email' => 'new@example.com',
            'status' => 'inactive',
        ]);
    }

    public function test_member_can_reset_password_with_an_expiring_single_use_otp(): void
    {
        Mail::fake();
        $member = $this->createMember('ST100001', 'Rahul Das', 'rahul@example.com');

        $this->post(route('member.forgot.send'), ['member_id' => $member->member_id])
            ->assertRedirect(route('member.forgot.verify'));

        Mail::assertSent(MemberPasswordOtpMail::class, function (MemberPasswordOtpMail $mail) use ($member) {
            return $mail->memberId === $member->member_id && strlen($mail->otp) === 6;
        });

        $otp = null;
        Mail::assertSent(MemberPasswordOtpMail::class, function (MemberPasswordOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->post(route('member.forgot.verify.store'), ['otp' => $otp])
            ->assertRedirect(route('member.forgot.reset'));

        $this->post(route('member.forgot.reset.store'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $member->refresh();
        $this->assertTrue(Hash::check('new-password', $member->password));
        $this->assertDatabaseCount('member_password_otps', 0);
    }

    private function createMember(string $memberId, string $name, string $email): Member
    {
        return Member::create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9876543210',
            'email' => $email,
            'password' => bcrypt('old-password'),
            'status' => 'active',
        ]);
    }
}
