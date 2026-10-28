<?php

namespace App\Http\Controllers;

use App\Mail\MemberPasswordOtpMail;
use App\Mail\MemberWelcomeMail;
use App\Models\AdminActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicMemberAuthController extends Controller
{
    private const RESET_MEMBER = 'member_password_reset_member_id';
    private const RESET_OTP = 'member_password_reset_otp_id';
    private const RESET_VERIFIED = 'member_password_reset_verified';
    private const REFERRAL_SPONSOR = 'public_referral_sponsor_id';

    public function register(Request $request): View
    {
        $prefillSponsorId = '';
        $lockedSponsorName = '';
        $sponsorLocked = false;
        $sponsorError = null;

        if ($request->query->has('sponsor_id')) {
            $requested = strtoupper(trim((string) $request->query('sponsor_id')));
            $resolved = $requested === '' ? null : $this->publicSponsor($requested);

            if ($resolved) {
                $prefillSponsorId = $resolved['id'];
                $lockedSponsorName = $resolved['name'];
                $sponsorLocked = true;
                $request->session()->put(self::REFERRAL_SPONSOR, $resolved['id']);
            } else {
                $prefillSponsorId = $requested;
                $request->session()->forget(self::REFERRAL_SPONSOR);
                $sponsorError = 'This referral link is invalid. Enter a valid Sponsor ID. No other sponsor was assigned.';
            }
        } else {
            $request->session()->forget(self::REFERRAL_SPONSOR);
        }

        return view('public.auth.register', [
            'generatedMemberId' => app(MemberRegistrationService::class)->previewMemberId(),
            'prefillSponsorId' => $prefillSponsorId,
            'lockedSponsorName' => $lockedSponsorName,
            'sponsorLocked' => $sponsorLocked,
            'sponsorError' => $sponsorError,
        ]);
    }

    public function checkSponsor(Request $request): \Illuminate\Http\JsonResponse
    {
        $sponsorId = strtoupper(trim((string) $request->query('sponsor_id', '')));

        if ($sponsorId === 'ST666666') {
            return response()->json([
                'exists' => true,
                'sponsor_name' => 'Admin',
                'message' => 'Sponsor ID is valid.',
            ]);
        }

        $sponsor = $sponsorId === '' ? null : Member::where('member_id', $sponsorId)->first();

        return response()->json([
            'exists' => $sponsor !== null,
            'sponsor_name' => $sponsor?->member_name,
            'message' => $sponsor ? 'Sponsor ID is valid.' : 'Invalid Sponsor ID.',
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $request->merge([
            'member_name' => trim((string) $request->input('member_name')),
            'sponsor_id' => strtoupper(trim((string) $request->input('sponsor_id'))),
            'mobile_no' => trim((string) $request->input('mobile_no')),
            'email' => trim((string) $request->input('email')),
        ]);

        $lockedSponsor = $request->session()->get(self::REFERRAL_SPONSOR);
        if (is_string($lockedSponsor) && $lockedSponsor !== '' && $request->input('sponsor_id') !== $lockedSponsor) {
            return back()
                ->withErrors(['sponsor_id' => 'The sponsor from this referral link cannot be changed.'])
                ->withInput();
        }

        $validated = $request->validate([
            'member_name' => Member::nameRules(),
            'sponsor_id' => ['required', 'string'],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            'email' => ['required', 'email', 'unique:members,email'],
        ]);

        $result = app(MemberRegistrationService::class)->register($validated, [
            'allow_admin_sponsor' => true,
            'generate_password' => true,
        ]);
        $member = $result['member'];
        $temporaryPassword = $result['temporary_password'];

        Mail::to($member->email)->send(new MemberWelcomeMail(
            $member->member_id,
            $member->member_name,
            $temporaryPassword,
        ));

        return redirect()->route('member.register')
            ->with('success', 'Registration successful. Your Member ID is ' . $member->member_id . '. Your password was sent to your email. You can replace it from Forgot Password.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'member_id' => ['required_without:email', 'nullable', 'string'],
            'email' => ['required_without:member_id', 'nullable', 'email'],
            'password' => ['required', 'string'],
        ]);

        $memberInput = trim((string) ($credentials['member_id'] ?? ''));
        $emailInput = trim((string) ($credentials['email'] ?? ''));
        $memberId = strtoupper($memberInput);
        $member = $memberInput !== '' && ! str_contains($memberInput, '@')
            ? Member::query()->where('member_id', $memberId)->first()
            : null;

        if ($member && $this->memberCanAuthenticate($member) && $this->passwordMatches($member, $credentials['password'])) {
            $request->session()->regenerate();
            $request->session()->put('member_context_id', $member->member_id);

            return redirect()->intended(route('member.dashboard'));
        }

        if ($memberId === MemberRegistrationService::ADMIN_SPONSOR_ID) {
            $admin = User::query()->where('is_admin', true)->first();

            if ($admin && Hash::check($credentials['password'], $admin->password)) {
                Auth::login($admin, $request->boolean('remember'));
                $request->session()->regenerate();
                $request->session()->forget('member_context_id');

                return $this->adminHome($request);
            }
        }

        $adminEmail = $emailInput !== '' ? $emailInput : (str_contains($memberInput, '@') ? $memberInput : null);

        if (
            $adminEmail
            && Auth::attempt(
                ['email' => $adminEmail, 'password' => $credentials['password']],
                $request->boolean('remember')
            )
        ) {
            $request->session()->regenerate();
            $request->session()->forget('member_context_id');

            return $this->adminHome($request);
        }

        $errorKey = $request->filled('member_id') ? 'member_id' : 'email';

        return back()
            ->withErrors([$errorKey => 'Invalid Member ID or password.'])
            ->onlyInput($errorKey);
    }

    public function forgot(): View
    {
        return view('auth.forgot-member');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string'],
        ]);

        $member = Member::where('member_id', strtoupper(trim($validated['member_id'])))->first();

        if (! $member || ! $member->email) {
            return back()->with('success', 'If that Member ID exists, an OTP will be sent to its registered email.');
        }

        $this->issueOtp($request, $member);

        return redirect()->route('member.forgot.verify')
            ->with('masked_email', $this->maskEmail($member->email));
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $member = Member::where('member_id', $request->session()->get(self::RESET_MEMBER))->first();

        if (! $member || ! $member->email) {
            return redirect()->route('member.forgot');
        }

        $this->issueOtp($request, $member);

        return redirect()->route('member.forgot.verify')
            ->with('masked_email', $this->maskEmail($member->email));
    }

    public function verifyForm(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has(self::RESET_OTP)) {
            return redirect()->route('member.forgot');
        }

        return view('auth.verify-member-otp', [
            'maskedEmail' => session('masked_email'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $record = DB::table('member_password_otps')
            ->where('id', $request->session()->get(self::RESET_OTP))
            ->where('member_id', $request->session()->get(self::RESET_MEMBER))
            ->first();

        if (! $record || $record->verified_at || now()->greaterThan($record->expires_at) || $record->attempts >= 5) {
            return back()->withErrors(['otp' => 'This OTP is invalid or has expired.']);
        }

        if (! Hash::check($validated['otp'], $record->otp_hash)) {
            DB::table('member_password_otps')->where('id', $record->id)->increment('attempts');

            return back()->withErrors(['otp' => 'This OTP is invalid or has expired.']);
        }

        DB::table('member_password_otps')->where('id', $record->id)->update([
            'verified_at' => now(),
            'updated_at' => now(),
        ]);
        $request->session()->put(self::RESET_VERIFIED, true);

        return redirect()->route('member.forgot.reset');
    }

    public function resetForm(Request $request): RedirectResponse|View
    {
        if (! $request->session()->get(self::RESET_VERIFIED)) {
            return redirect()->route('member.forgot');
        }

        return view('auth.reset-member-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        if (! $request->session()->get(self::RESET_VERIFIED)) {
            return redirect()->route('member.forgot');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $member = Member::where('member_id', $request->session()->get(self::RESET_MEMBER))->first();

        if (! $member) {
            return redirect()->route('member.forgot');
        }

        DB::transaction(function () use ($member, $validated, $request) {
            $member->update(['password' => Hash::make($validated['password'])]);
            DB::table('member_password_otps')
                ->where('id', $request->session()->get(self::RESET_OTP))
                ->delete();
        });

        $request->session()->forget([
            self::RESET_MEMBER,
            self::RESET_OTP,
            self::RESET_VERIFIED,
            'masked_email',
        ]);

        return redirect()->route('login')->with('success', 'Password reset successfully.');
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function publicSponsor(string $sponsorId): ?array
    {
        if ($sponsorId === MemberRegistrationService::ADMIN_SPONSOR_ID) {
            return [
                'id' => MemberRegistrationService::ADMIN_SPONSOR_ID,
                'name' => 'Admin',
            ];
        }

        $sponsor = Member::query()->where('member_id', $sponsorId)->first();

        if (! $sponsor) {
            return null;
        }

        return [
            'id' => $sponsor->member_id,
            'name' => $sponsor->member_name,
        ];
    }

    private function adminHome(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            AdminActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => 'admin_login',
                'subject' => 'admin',
                'format' => null,
                'scope' => 'admin',
                'filters' => [],
                'row_count' => 1,
            ]);
        }

        if ($user?->must_change_password === true) {
            return redirect()->route('admin.change-password.index');
        }

        return redirect()->intended('/dashboard');
    }

    private function memberCanAuthenticate(Member $member): bool
    {
        return in_array(strtolower((string) $member->status), ['active', 'inactive'], true);
    }

    private function passwordMatches(Member $member, string $password): bool
    {
        $hash = $member->getRawOriginal('password');

        return is_string($hash) && $hash !== '' && Hash::check($password, $hash);
    }

    private function issueOtp(Request $request, Member $member): void
    {
        $otp = (string) random_int(100000, 999999);
        $otpRecord = DB::transaction(function () use ($member, $otp) {
            DB::table('member_password_otps')
                ->where('member_id', $member->member_id)
                ->whereNull('verified_at')
                ->delete();

            return DB::table('member_password_otps')->insertGetId([
                'member_id' => $member->member_id,
                'email' => $member->email,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $request->session()->put([
            self::RESET_MEMBER => $member->member_id,
            self::RESET_OTP => $otpRecord,
            self::RESET_VERIFIED => false,
        ]);

        Mail::to($member->email)->send(new MemberPasswordOtpMail($member->member_id, $otp));
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);

        return $visible . str_repeat('*', max(1, strlen($name) - 1)) . '@' . $domain;
    }
}
