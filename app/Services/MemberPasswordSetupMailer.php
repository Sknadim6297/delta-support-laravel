<?php

namespace App\Services;

use App\Mail\MemberPasswordSetupMail;
use App\Models\Member;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberPasswordSetupMailer
{
    public function __construct(
        private readonly SmtpMailTranscript $transcript,
    ) {}

    public function send(Member $member, ?Mailable $mailable = null): MailDeliveryNotice
    {
        $token = $this->issue($member);
        $mailable ??= new MemberPasswordSetupMail(
            $member->member_id,
            $member->member_name,
            route('member.password-setup', ['token' => $token]),
        );

        if ($mailable instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
            return new MailDeliveryNotice(false, 'queued');
        }

        $transport = (string) config('mail.default');

        try {
            $sent = Mail::mailer($transport)->to($member->email)->send($mailable);

            return $this->transcript->interpret($transport, $sent?->getDebug());
        } catch (Throwable $exception) {
            Log::warning('Member password setup email was not accepted.', [
                'member_id' => $member->member_id,
                'exception' => $exception::class,
            ]);

            return $this->transcript->interpret($transport, null, $exception);
        }
    }

    public function memberIdFor(string $token): ?string
    {
        $setup = $this->findUsable($token);

        return $setup === null ? null : (string) $setup->member_id;
    }

    public function consume(string $token, string $password): Member
    {
        return DB::transaction(function () use ($token, $password): Member {
            $setup = DB::table('member_password_setups')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($setup === null || $setup->used_at !== null || now()->greaterThan(\Illuminate\Support\Carbon::parse($setup->expires_at))) {
                throw ValidationException::withMessages([
                    'password' => 'This password setup link is invalid or expired.',
                ]);
            }

            $member = Member::query()->where('member_id', $setup->member_id)->first();
            if ($member === null) {
                throw ValidationException::withMessages([
                    'password' => 'This password setup link is invalid or expired.',
                ]);
            }

            $member->password = Hash::make($password);
            $member->save();

            DB::table('member_password_setups')->where('id', $setup->id)->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

            return $member;
        });
    }

    private function issue(string|Member $member): string
    {
        $memberId = $member instanceof Member ? $member->member_id : $member;
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        DB::table('member_password_setups')
            ->where('member_id', $memberId)
            ->whereNull('used_at')
            ->delete();

        DB::table('member_password_setups')->insert([
            'member_id' => $memberId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    private function findUsable(string $token): ?object
    {
        $setup = DB::table('member_password_setups')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($setup === null || $setup->used_at !== null || now()->greaterThan(\Illuminate\Support\Carbon::parse($setup->expires_at))) {
            return null;
        }

        return $setup;
    }
}
