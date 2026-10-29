<?php

namespace Tests\Feature;

use App\Mail\MemberSecurityPinOtpMail;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\MemberSecurityPinReset;
use App\Models\WalletLedgerEntry;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalSetting;
use App\Services\MemberSecurityPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberSecurityPinTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_open_security_pin_and_a_member_can_set_one_without_storing_it(): void
    {
        $this->get(route('member.security-pin'))->assertForbidden();

        $member = $this->member('STPIN0001', 'pin1@example.test');
        $member->forceFill(['password' => Hash::make('account-password')])->save();

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.security-pin'))
            ->assertOk()
            ->assertSeeText('Set PIN')
            ->assertSeeText('Not set')
            ->assertDontSee('security_pin_hash', false);

        $response = $this->from(route('member.security-pin'))
            ->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.store'), [
                'pin' => '000000',
                'pin_confirmation' => '000000',
            ]);
        $response->assertSessionHasErrors('pin');
        $old = session()->getOldInput();
        $this->assertTrue($old === [] || $old === null || ! array_key_exists('pin', $old));
        $this->assertTrue($old === [] || $old === null || ! array_key_exists('pin_confirmation', $old));
        $this->assertNull($member->fresh()->security_pin_hash);

        foreach (['111111', '123456', '654321', '012345'] as $weak) {
            $this->withSession(['member_context_id' => $member->member_id])
                ->post(route('member.security-pin.store'), [
                    'pin' => $weak,
                    'pin_confirmation' => $weak,
                ])->assertSessionHasErrors('pin');
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.store'), [
                'pin_digits' => ['4', '8', '2', '9', '1', '3'],
                'pin_confirmation_digits' => ['4', '8', '2', '9', '1', '3'],
            ])->assertRedirect(route('member.security-pin'))
            ->assertSessionHas('success', 'Your security PIN is set.');

        $saved = $member->fresh();
        $this->assertTrue(Hash::check('482913', (string) $saved->security_pin_hash));
        $this->assertNotSame('482913', $saved->security_pin_hash);
        $this->assertArrayNotHasKey('security_pin_hash', $saved->toArray());
        $this->assertTrue(Hash::check('account-password', (string) $saved->password));
        $this->assertFalse(Schema::hasColumn('members', 'security_pin'));
        $this->assertFalse(Schema::hasColumn('member_security_pin_resets', 'otp'));
        $this->assertNull(collect(Schema::getColumnListing('members'))->first(fn (string $column): bool => $column === 'security_pin'));

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.security-pin'))
            ->assertOk()
            ->assertSeeText('Change PIN')
            ->assertSeeText('Forgot PIN')
            ->assertDontSee('482913', false);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.store'), [
                'pin' => '159357',
                'pin_confirmation' => '159357',
            ])->assertSessionHasErrors('pin');
    }

    public function test_change_pin_requires_the_current_pin_and_revokes_reset_challenges(): void
    {
        $member = $this->member('STPIN0002', 'pin2@example.test');
        $this->givePin($member, '482913');
        $reset = MemberSecurityPinReset::query()->create([
            'member_id' => $member->member_id,
            'otp_hash' => Hash::make('135790'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.change'), [
                'current_pin' => 'account-password',
                'pin' => '159357',
                'pin_confirmation' => '159357',
            ])->assertSessionHasErrors('current_pin');
        $this->assertTrue(Hash::check('482913', (string) $member->fresh()->security_pin_hash));
        $this->assertNull($reset->fresh()->invalidated_at);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.change'), [
                'current_pin' => '482913',
                'pin' => '159357',
                'pin_confirmation' => '159357',
            ])->assertRedirect(route('member.security-pin'));

        $this->assertTrue(Hash::check('159357', (string) $member->fresh()->security_pin_hash));
        $this->assertNotNull($reset->fresh()->invalidated_at);
    }

    public function test_forgot_pin_emails_a_hashed_single_use_code_and_hides_it_from_the_response(): void
    {
        Mail::fake();
        $member = $this->member('STPIN0003', 'pin3@example.test');
        $this->givePin($member, '482913');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.forgot'))
            ->assertRedirect(route('member.security-pin'));
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.forgot'))
            ->assertRedirect(route('member.security-pin'))
            ->assertSessionHas('success', MemberSecurityPin::GENERIC_OTP_MESSAGE);

        $otps = [];
        Mail::assertSent(MemberSecurityPinOtpMail::class, function (MemberSecurityPinOtpMail $mail) use (&$otps, $member) {
            $otps[] = $mail->otp;

            return $mail->memberId === $member->member_id && preg_match('/^\d{6}$/', $mail->otp) === 1;
        });
        $this->assertCount(2, $otps);
        $this->assertNotSame($otps[0], $otps[1]);

        $rows = MemberSecurityPinReset::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->invalidated_at);
        $this->assertNull($rows[1]->invalidated_at);
        $this->assertNotSame($otps[1], $rows[1]->otp_hash);
        $this->assertTrue(Hash::check($otps[1], (string) $rows[1]->otp_hash));
        $this->assertFalse(Schema::hasColumn('member_security_pin_resets', 'otp'));

        $response = $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.security-pin'));
        $response->assertOk()->assertSeeText('Enter reset code')->assertDontSee($otps[1], false);
        $this->assertStringNotContainsString($otps[1], json_encode(session()->all()));

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.verify'), ['otp' => $otps[0]])
            ->assertSessionHasErrors('otp');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.verify'), ['otp' => $otps[1]])
            ->assertRedirect(route('member.security-pin'))
            ->assertSessionHas('success', 'The code was verified. Choose a new PIN.');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.verify'), ['otp' => $otps[1]])
            ->assertSessionHasErrors('otp');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.reset'), [
                'pin' => '159357',
                'pin_confirmation' => '159357',
            ])->assertRedirect(route('member.security-pin'));

        $this->assertTrue(Hash::check('159357', (string) $member->fresh()->security_pin_hash));
        $this->assertNotNull($rows[1]->fresh()->consumed_at);
        $this->assertSame(0, MemberSecurityPinReset::query()->whereNull('consumed_at')->whereNull('invalidated_at')->count());
    }

    public function test_reset_code_expires_after_five_minutes(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-29 12:00:00'));
        $member = $this->member('STPIN0004', 'pin4@example.test');
        $this->givePin($member, '482913');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.forgot'))
            ->assertRedirect();
        $otp = $this->sentOtp();

        Carbon::setTestNow(Carbon::parse('2026-10-29 12:06:00'));
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.verify'), ['otp' => $otp])
            ->assertSessionHasErrors('otp');
        $this->assertTrue(Hash::check('482913', (string) $member->fresh()->security_pin_hash));
        $this->assertNotNull(MemberSecurityPinReset::query()->firstOrFail()->invalidated_at);
    }

    public function test_wrong_reset_codes_are_rejected_and_the_fifth_attempt_invalidates_the_code(): void
    {
        Mail::fake();
        $member = $this->member('STPIN0005', 'pin5@example.test');
        $this->givePin($member, '482913');
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.forgot'))
            ->assertRedirect();
        $otp = $this->sentOtp();

        foreach (['111111', '222222', '333333', '444444', '555555'] as $guess) {
            $this->withSession(['member_context_id' => $member->member_id])
                ->post(route('member.security-pin.verify'), ['otp' => $guess])
                ->assertSessionHasErrors('otp');
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.verify'), ['otp' => $otp])
            ->assertSessionHasErrors('otp');
        $this->assertNotNull(MemberSecurityPinReset::query()->firstOrFail()->invalidated_at);
        $this->assertTrue(Hash::check('482913', (string) $member->fresh()->security_pin_hash));
    }

    public function test_reset_requests_are_rate_limited_without_sending_another_code(): void
    {
        Mail::fake();
        $member = $this->member('STPIN0006', 'pin6@example.test');
        $this->givePin($member, '482913');

        foreach (range(1, 3) as $attempt) {
            $this->withSession(['member_context_id' => $member->member_id])
                ->post(route('member.security-pin.forgot'))
                ->assertRedirect();
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.security-pin.forgot'))
            ->assertStatus(429);

        Mail::assertSent(MemberSecurityPinOtpMail::class, 3);
        $this->assertSame(1, MemberSecurityPinReset::query()->whereNull('invalidated_at')->count());
    }

    public function test_log_mailer_does_not_create_or_pretend_to_send_a_reset_code(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $member = $this->member('STPIN0007', 'pin7@example.test');
        $this->givePin($member, '482913');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.security-pin'))
            ->assertOk()
            ->assertSeeText('MAIL_MAILER is log')
            ->assertSeeText('was not written to the log');

        $this->withSession(['member_context_id' => $member->member_id])
            ->from(route('member.security-pin'))
            ->post(route('member.security-pin.forgot'))
            ->assertSessionHasErrors([
                'otp' => 'Email delivery is unavailable. MAIL_MAILER is log, so a reset code was not created and was not written to the log. Configure a delivery mailer before using Forgot PIN.',
            ]);

        Mail::assertNothingSent();
        $this->assertSame(0, MemberSecurityPinReset::query()->count());
    }

    public function test_failed_delivery_invalidates_the_code_and_logs_no_secret(): void
    {
        $member = $this->member('STPIN0008', 'pin8@example.test');
        $this->givePin($member, '482913');
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = ['message' => $event->message, 'context' => $event->context];
        });
        Mail::shouldReceive('to')->once()->andReturn(new class
        {
            public function send(mixed $mail = null): void
            {
                unset($mail);
                throw new \RuntimeException('smtp unavailable');
            }
        });

        $this->withSession(['member_context_id' => $member->member_id])
            ->from(route('member.security-pin'))
            ->post(route('member.security-pin.forgot'))
            ->assertSessionHasErrors('otp');

        $this->assertSame(0, MemberSecurityPinReset::query()->whereNull('invalidated_at')->count());
        $warnings = array_values(array_filter(
            $logged,
            fn (array $row): bool => $row['message'] === 'Security PIN reset email could not be delivered.'
        ));
        $this->assertSame([
            ['message' => 'Security PIN reset email could not be delivered.', 'context' => [
                'member_id' => $member->member_id,
                'exception' => \RuntimeException::class,
            ]],
        ], $warnings);
    }

    public function test_pin_verification_rate_limit_stops_repeated_changes(): void
    {
        $member = $this->member('STPIN0009', 'pin9@example.test');
        $current = '482913';
        $this->givePin($member, $current);

        for ($index = 0; $index < 8; $index++) {
            $next = (string) (159357 + $index);
            $this->withSession(['member_context_id' => $member->member_id])
                ->post(route('member.security-pin.change'), [
                    'current_pin' => $current,
                    'pin' => $next,
                    'pin_confirmation' => $next,
                ])->assertRedirect(route('member.security-pin'));
            $current = $next;
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->from(route('member.security-pin'))
            ->post(route('member.security-pin.change'), [
                'current_pin' => $current,
                'pin' => '246801',
                'pin_confirmation' => '246801',
            ])->assertSessionHasErrors('current_pin');

        $this->assertTrue(Hash::check($current, (string) $member->fresh()->security_pin_hash));
        $this->assertFalse(Hash::check('246801', (string) $member->fresh()->security_pin_hash));
    }

    public function test_withdrawal_requires_the_pin_and_a_password_cannot_bypass_it(): void
    {
        $member = $this->member('STPIN0010', 'pin10@example.test', '50.0000');
        $member->forceFill(['password' => Hash::make('account-password')])->save();
        $this->openWithdrawals();

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->withdrawal('account-password'))
            ->assertSessionHasErrors('security_pin');
        $this->assertUnchanged($member);

        $this->givePin($member, '482913');
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->withdrawal('account-password', ['pin_verified' => '1']))
            ->assertSessionHasErrors('security_pin');
        $this->assertUnchanged($member);

        foreach (['135790', '246801', '369258', '147258'] as $guess) {
            $this->withSession(['member_context_id' => $member->member_id])
                ->post(route('member.withdrawals.store'), $this->withdrawal($guess))
                ->assertSessionHasErrors('security_pin');
        }
        $this->assertUnchanged($member);
        $this->assertTrue($member->fresh()->security_pin_locked_until->isFuture());

        Carbon::setTestNow(now()->addMinutes(2));
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->withdrawal('482913'))
            ->assertSessionHasErrors('security_pin');
        $this->assertUnchanged($member);

        Carbon::setTestNow(now()->addMinutes(16));
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.withdrawals.store'), $this->withdrawal('482913'))
            ->assertRedirect(route('member.withdrawals.history'));

        $this->assertSame(1, WithdrawalRequest::query()->count());
        $this->assertSame(1, WalletLedgerEntry::query()->count());
        $this->assertSame('hold', WalletLedgerEntry::query()->value('direction'));
        $this->assertSame('50.0000', $member->fresh()->roi_wallet_amount);
        $this->assertSame('0.0000', $member->fresh()->working_wallet_amount);
        $saved = FinancialControl::query()->first();
        if ($saved) {
            $this->assertFalse($saved->withdrawal_requests_enabled);
            $this->assertFalse($saved->external_payouts_enabled);
        }
    }

    public function test_disabled_withdrawal_screen_does_not_gain_a_pin_form(): void
    {
        $member = $this->member('STPIN0011', 'pin11@example.test');

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.withdrawals.request'))
            ->assertOk()
            ->assertSeeText('Withdrawal submissions are unavailable.')
            ->assertDontSee('security_pin_digits', false);
    }

    private function member(string $memberId, string $email, string $roi = '0.0000'): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '9000000000',
            'email' => $email,
            'status' => 'active',
            'roi_wallet_amount' => $roi,
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function givePin(Member $member, string $pin): void
    {
        $member->forceFill([
            'security_pin_hash' => Hash::make($pin),
            'security_pin_set_at' => now(),
            'security_pin_failed_attempts' => 0,
            'security_pin_locked_until' => null,
        ])->save();
    }

    private function sentOtp(): string
    {
        $otp = null;
        Mail::assertSent(MemberSecurityPinOtpMail::class, function (MemberSecurityPinOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->assertIsString($otp);

        return $otp;
    }

    private function openWithdrawals(): void
    {
        config(['deltasupport.controls.withdrawal_requests_enabled' => true]);
        WithdrawalSetting::current()->forceFill([
            'eligible_wallets' => ['roi'],
            'supported_networks' => ['TRC20'],
            'charge_type' => 'none',
            'charge_value' => '0.0000',
            'minimum_amount' => '20.0000',
            'maximum_amount' => '40.0000',
            'address_validation_policy' => 'Public address only.',
            'member_cancellation_policy' => 'An administrator can reject a pending request.',
        ])->save();
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function withdrawal(string $pin, array $extra = []): array
    {
        return array_merge([
            'wallet' => 'roi',
            'amount' => '20.0000',
            'network' => 'TRC20',
            'address' => 'TPublicAddressOnly123',
            'idempotency_key' => (string) Str::uuid(),
            'security_pin' => $pin,
        ], $extra);
    }

    private function assertUnchanged(Member $member): void
    {
        $this->assertSame(0, WithdrawalRequest::query()->count());
        $this->assertSame(0, WalletLedgerEntry::query()->count());
        $member->refresh();
        $this->assertSame('50.0000', $member->roi_wallet_amount);
        $this->assertSame('0.0000', $member->working_wallet_amount);
        $this->assertSame('0.0000', $member->activation_wallet_amount);
    }
}
