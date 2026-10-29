<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberSecurityPinReset;
use App\Mail\MemberSecurityPinOtpMail;
use App\Rules\NotWeakSecurityPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberSecurityPin
{
    public const SESSION_CHALLENGE = 'security_pin_reset_challenge';

    public const SESSION_PENDING = 'security_pin_otp_pending';

    public const VERIFY_RATE_MAX = 8;

    public const VERIFY_RATE_DECAY_SECONDS = 60;

    public const OTP_RATE_MAX = 3;

    public const OTP_RATE_DECAY_SECONDS = 900;

    public const OTP_IP_RATE_MAX = 20;

    public const MAX_PIN_FAILURES = 5;

    public const PIN_LOCK_MINUTES = 15;

    public const OTP_MAX_ATTEMPTS = 5;

    public const OTP_TTL_MINUTES = 5;

    public const GENERIC_OTP_MESSAGE = 'If a registered email can receive mail, a reset code was sent. It expires in 5 minutes and can be used once.';

    private ?string $timingHash = null;

    public function __construct(private readonly MemberMailDelivery $mail)
    {
    }

    public function hasPin(Member $member): bool
    {
        return is_string($member->security_pin_hash) && $member->security_pin_hash !== '';
    }

    public function setInitial(Member $member, string $pin): void
    {
        $this->assertChoosable($pin);

        DB::transaction(function () use ($member, $pin): void {
            $locked = $this->lockMember($member);
            if ($this->hasPin($locked)) {
                throw ValidationException::withMessages([
                    'pin' => 'A security PIN is already set. Use Change PIN.',
                ]);
            }

            $this->storePin($locked, $pin);
        });
    }

    public function change(Member $member, string $currentPin, string $nextPin): void
    {
        $this->authorizeTransaction($member, $currentPin, 'current_pin');
        $this->assertChoosable($nextPin, 'pin');

        DB::transaction(function () use ($member, $currentPin, $nextPin): void {
            $locked = $this->lockMember($member);
            $this->assertCurrent($locked, $currentPin, 'current_pin');
            if (Hash::check($nextPin, (string) $locked->security_pin_hash)) {
                throw ValidationException::withMessages([
                    'pin' => 'Choose a different PIN from the current one.',
                ]);
            }

            $this->storePin($locked, $nextPin);
            $this->revokeOutstanding($locked);
        });
    }

    public function requestReset(Member $member, string $ip): SecurityPinOtpResult
    {
        if (! $this->hasPin($member)) {
            throw ValidationException::withMessages([
                'otp' => 'Set a security PIN before requesting a reset code.',
            ]);
        }

        $blocker = $this->mail->unavailableReason();
        if ($blocker !== null) {
            return new SecurityPinOtpResult(false, $blocker);
        }

        $memberKey = $this->otpMemberKey($member);
        $ipKey = $this->otpIpKey($ip);
        if (RateLimiter::tooManyAttempts($memberKey, self::OTP_RATE_MAX) || RateLimiter::tooManyAttempts($ipKey, self::OTP_IP_RATE_MAX)) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait before requesting another reset code.',
            ]);
        }

        RateLimiter::hit($memberKey, self::OTP_RATE_DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::OTP_RATE_DECAY_SECONDS);

        $email = trim((string) $member->email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return new SecurityPinOtpResult(false);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $resetId = DB::transaction(function () use ($member, $otp): int {
            $this->revokeOutstanding($this->lockMember($member));

            return (int) MemberSecurityPinReset::query()->create([
                'member_id' => $member->member_id,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0,
            ])->id;
        });

        try {
            Mail::to($email)->send(new MemberSecurityPinOtpMail($member->member_id, $otp));
        } catch (Throwable $exception) {
            MemberSecurityPinReset::query()->whereKey($resetId)->update([
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);
            Log::warning('Security PIN reset email could not be delivered.', [
                'member_id' => $member->member_id,
                'exception' => $exception::class,
            ]);

            return new SecurityPinOtpResult(false, 'The reset code could not be delivered. No active code remains. Email delivery failed.');
        }

        return new SecurityPinOtpResult(true);
    }

    public function verifyOtp(Member $member, string $otp): int
    {
        $otp = trim($otp);
        $resetId = DB::transaction(function () use ($member, $otp): ?int {
            $reset = MemberSecurityPinReset::query()
                ->where('member_id', $member->member_id)
                ->whereNull('invalidated_at')
                ->whereNull('consumed_at')
                ->whereNull('verified_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $reset || $reset->expires_at->lte(now()) || $reset->attempts >= self::OTP_MAX_ATTEMPTS) {
                if ($reset && ($reset->expires_at->lte(now()) || $reset->attempts >= self::OTP_MAX_ATTEMPTS)) {
                    $reset->forceFill(['invalidated_at' => now()])->save();
                }

                return null;
            }

            if (! Hash::check($otp, (string) $reset->otp_hash)) {
                $attempts = $reset->attempts + 1;
                $reset->forceFill([
                    'attempts' => $attempts,
                    'invalidated_at' => $attempts >= self::OTP_MAX_ATTEMPTS ? now() : null,
                ])->save();

                return null;
            }

            $reset->forceFill(['verified_at' => now()])->save();

            return (int) $reset->id;
        });

        if ($resetId === null) {
            throw ValidationException::withMessages([
                'otp' => 'This code is invalid or has expired.',
            ]);
        }

        return $resetId;
    }

    public function completeReset(Member $member, int $resetId, string $pin): void
    {
        $this->assertChoosable($pin);

        DB::transaction(function () use ($member, $resetId, $pin): void {
            $reset = MemberSecurityPinReset::query()->whereKey($resetId)->lockForUpdate()->first();
            $locked = $this->lockMember($member);
            if (! $this->challengeIsOpen($reset, $locked)) {
                throw ValidationException::withMessages([
                    'pin' => 'Verify the email code before choosing a new PIN.',
                ]);
            }
            if ($this->hasPin($locked) && Hash::check($pin, (string) $locked->security_pin_hash)) {
                throw ValidationException::withMessages([
                    'pin' => 'Choose a different PIN from the current one.',
                ]);
            }

            $this->storePin($locked, $pin);
            $reset->forceFill(['consumed_at' => now()])->save();
            $this->revokeOutstanding($locked);
        });
    }

    public function pendingResetId(Request $request, Member $member): ?int
    {
        $resetId = $request->session()->get(self::SESSION_CHALLENGE);
        if (! is_int($resetId) && ! (is_string($resetId) && ctype_digit($resetId))) {
            return null;
        }

        $reset = MemberSecurityPinReset::query()->find((int) $resetId);
        if (! $this->challengeIsOpen($reset, $member)) {
            return null;
        }

        return (int) $reset->id;
    }

    public function authorizeTransaction(Member $member, string $pin, string $errorKey = 'security_pin'): void
    {
        $rateKey = $this->verifyRateKey($member);
        if (RateLimiter::tooManyAttempts($rateKey, self::VERIFY_RATE_MAX)) {
            throw ValidationException::withMessages([
                $errorKey => 'Too many attempts. Try again later.',
            ]);
        }
        RateLimiter::hit($rateKey, self::VERIFY_RATE_DECAY_SECONDS);

        $outcome = DB::transaction(function () use ($member, $pin): string {
            $locked = $this->lockMember($member);
            if ($locked->security_pin_locked_until && $locked->security_pin_locked_until->isFuture()) {
                return 'locked';
            }

            if ($locked->security_pin_locked_until) {
                $locked->forceFill([
                    'security_pin_locked_until' => null,
                    'security_pin_failed_attempts' => 0,
                ])->save();
            }

            $hash = is_string($locked->security_pin_hash) ? $locked->security_pin_hash : '';
            $matches = Hash::check($pin, $hash !== '' ? $hash : $this->timingHash());
            if ($hash === '') {
                return 'missing';
            }
            if (! $matches) {
                $attempts = (int) $locked->security_pin_failed_attempts + 1;
                $locked->forceFill([
                    'security_pin_failed_attempts' => $attempts >= self::MAX_PIN_FAILURES ? 0 : $attempts,
                    'security_pin_locked_until' => $attempts >= self::MAX_PIN_FAILURES ? now()->addMinutes(self::PIN_LOCK_MINUTES) : null,
                ])->save();

                return 'invalid';
            }

            if ((int) $locked->security_pin_failed_attempts !== 0) {
                $locked->forceFill(['security_pin_failed_attempts' => 0])->save();
            }

            return 'ok';
        });

        if ($outcome === 'ok') {
            return;
        }

        $message = match ($outcome) {
            'missing' => 'Set a security PIN before this request. No funds were reserved.',
            'locked' => 'Too many attempts. Try again later.',
            default => 'The security PIN is incorrect.',
        };

        throw ValidationException::withMessages([$errorKey => $message]);
    }

    public function assertCurrent(Member $member, string $pin, string $errorKey = 'security_pin'): void
    {
        if ($member->security_pin_locked_until && $member->security_pin_locked_until->isFuture()) {
            throw ValidationException::withMessages([
                $errorKey => 'Too many attempts. Try again later.',
            ]);
        }

        $hash = is_string($member->security_pin_hash) ? $member->security_pin_hash : '';
        if ($hash === '' || ! Hash::check($pin, $hash)) {
            throw ValidationException::withMessages([
                $errorKey => $hash === ''
                    ? 'Set a security PIN before this request. No funds were reserved.'
                    : 'The security PIN is incorrect.',
            ]);
        }
    }

    private function assertChoosable(string $pin, string $key = 'pin'): void
    {
        Validator::make([$key => $pin], [
            $key => ['required', 'digits:6', new NotWeakSecurityPin],
        ])->validate();
    }

    private function storePin(Member $member, string $pin): void
    {
        $member->forceFill([
            'security_pin_hash' => Hash::make($pin),
            'security_pin_set_at' => now(),
            'security_pin_failed_attempts' => 0,
            'security_pin_locked_until' => null,
        ])->save();
    }

    private function revokeOutstanding(Member $member): void
    {
        MemberSecurityPinReset::query()
            ->where('member_id', $member->member_id)
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function lockMember(Member $member): Member
    {
        return Member::query()->where('member_id', $member->member_id)->lockForUpdate()->firstOrFail();
    }

    private function challengeIsOpen(?MemberSecurityPinReset $reset, Member $member): bool
    {
        return $reset !== null
            && $reset->member_id === $member->member_id
            && $reset->verified_at !== null
            && $reset->consumed_at === null
            && $reset->invalidated_at === null
            && $reset->expires_at->isFuture();
    }

    private function verifyRateKey(Member $member): string
    {
        return 'member-security-pin:'.$member->member_id;
    }

    private function otpMemberKey(Member $member): string
    {
        return 'member-security-pin-otp:'.$member->member_id;
    }

    private function otpIpKey(string $ip): string
    {
        return 'member-security-pin-otp-ip:'.$ip;
    }

    private function timingHash(): string
    {
        return $this->timingHash ??= Hash::make('timing-only-pin');
    }
}
