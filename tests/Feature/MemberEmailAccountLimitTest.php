<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberEmailAccountPolicy;
use App\Services\MemberRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberEmailAccountLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_email_allows_five_accounts_and_blocks_sixth(): void
    {
        Mail::fake();
        $email = ' Shared@Example.com ';
        $created = [];

        for ($index = 1; $index <= 5; $index++) {
            $result = app(MemberRegistrationService::class)->register([
                'member_name' => 'Shared Member '.$index,
                'sponsor_id' => 'ST666666',
                'mobile_no' => '987654'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'email' => $index % 2 === 0 ? 'SHARED@example.com' : ' shared@example.com ',
            ], [
                'allow_admin_sponsor' => true,
                'blank_sponsor_uses_admin' => true,
            ]);
            $created[] = $result['member'];
        }

        $this->assertSame(5, app(MemberEmailAccountPolicy::class)->count($email));
        $this->assertCount(5, collect($created)->pluck('member_id')->unique());

        try {
            app(MemberRegistrationService::class)->register([
                'member_name' => 'Sixth Shared Member',
                'sponsor_id' => 'ST666666',
                'mobile_no' => '9876554321',
                'email' => 'shared@example.COM',
            ], [
                'allow_admin_sponsor' => true,
            ]);
            $this->fail('The sixth account must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This email address has already reached the maximum limit of 5 member accounts.',
                $exception->errors()['email'][0],
            );
        }

        $this->assertSame(5, app(MemberEmailAccountPolicy::class)->count($email));
    }

    public function test_different_email_has_an_independent_limit(): void
    {
        for ($index = 1; $index <= 5; $index++) {
            app(MemberRegistrationService::class)->register([
                'member_name' => 'Independent Member '.$index,
                'sponsor_id' => 'ST666666',
                'mobile_no' => '988654'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'email' => 'independent@example.com',
            ], ['allow_admin_sponsor' => true]);
        }

        $this->assertSame(5, app(MemberEmailAccountPolicy::class)->count(' independent@EXAMPLE.com '));
    }

    public function test_email_login_is_ambiguous_only_when_multiple_accounts_match(): void
    {
        Mail::fake();
        $first = $this->member('ST500001', 'First Shared', 'shared-login@example.com');
        $second = $this->member('ST500002', 'Second Shared', ' SHARED-login@example.com ');

        $first->forceFill(['password' => Hash::make('shared-password')])->save();
        $second->forceFill(['password' => Hash::make('shared-password')])->save();

        $this->post(route('login.submit'), [
            'email' => 'shared-login@example.com',
            'password' => 'shared-password',
        ])
            ->assertSessionHasErrors([
                'email' => 'Multiple member accounts use this email. Sign in with your Member ID.',
            ]);

        $this->post(route('login.submit'), [
            'member_id' => $second->member_id,
            'password' => 'shared-password',
        ])
            ->assertRedirect(route('member.dashboard'));
        $this->assertSame($second->member_id, session('member_context_id'));
    }

    public function test_password_reset_member_id_only_updates_the_intended_account(): void
    {
        Mail::fake();
        $first = $this->member('ST500011', 'First Reset', 'reset-shared@example.com');
        $second = $this->member('ST500012', 'Second Reset', 'reset-shared@example.com');

        $this->post(route('member.forgot.send'), ['member_id' => $second->member_id])
            ->assertRedirect(route('member.forgot.verify'));

        $otp = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MemberPasswordOtpMail::class, function ($mail) use (&$otp, $second): bool {
            if ($mail->memberId !== $second->member_id) {
                return false;
            }
            $otp = $mail->otp;
            return true;
        });

        $this->assertIsString($otp);
        $this->post(route('member.forgot.verify.store'), ['otp' => $otp])
            ->assertRedirect(route('member.forgot.reset'));
        $this->post(route('member.forgot.reset.store'), [
            'password' => 'second-new-password',
            'password_confirmation' => 'second-new-password',
        ])->assertRedirect(route('login'));

        $this->assertFalse(Hash::check('second-new-password', (string) $first->fresh()->password));
        $this->assertTrue(Hash::check('second-new-password', (string) $second->fresh()->password));
    }

    public function test_admin_registration_uses_the_same_limit_and_duplicate_email_validation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        for ($index = 1; $index <= 5; $index++) {
            $this->post(route('admin.members.store'), [
                'sponsor_id' => 'ST666666',
                'member_name' => 'Admin Shared '.match ($index) {
                    1 => 'One',
                    2 => 'Two',
                    3 => 'Three',
                    4 => 'Four',
                    5 => 'Five',
                },
                'mobile_no' => '989654'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'email' => $index % 2 === 0 ? 'ADMIN-SHARED@example.com' : ' admin-shared@example.com ',
            ])->assertRedirect(route('admin.members.registration'));
        }

        $this->post(route('admin.members.store'), [
            'sponsor_id' => 'ST666666',
            'member_name' => 'Admin Shared Sixth',
            'mobile_no' => '9896554321',
            'email' => 'admin-shared@example.com',
        ])
            ->assertSessionHasErrors([
                'email' => 'This email address has already reached the maximum limit of 5 member accounts.',
            ]);

        $this->assertSame(5, app(MemberEmailAccountPolicy::class)->count('ADMIN-SHARED@example.com'));
    }

    private function member(string $memberId, string $name, string $email): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => $name,
            'mobile_no' => '9000000000',
            'email' => $email,
            'status' => 'active',
        ]);
    }
}
