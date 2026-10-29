<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Rules\NotWeakSecurityPin;
use App\Services\MemberMailDelivery;
use App\Services\MemberSecurityPin;
use App\Support\SecurityPinInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityPinController extends Controller
{
    public function show(Request $request, MemberSecurityPin $pins, MemberMailDelivery $mail): View
    {
        $member = $this->member($request);

        return view('member.security-pin.index', [
            'member' => $member,
            'pinSet' => $pins->hasPin($member),
            'mailUnavailable' => $mail->unavailableReason(),
            'maskedEmail' => $this->maskEmail((string) $member->email),
            'resetReady' => $pins->pendingResetId($request, $member) !== null,
            'otpPending' => $request->session()->get(MemberSecurityPin::SESSION_PENDING) === true,
            'locked' => $member->security_pin_locked_until !== null && $member->security_pin_locked_until->isFuture(),
            'transferReturn' => $request->session()->get(\App\Http\Controllers\Member\MemberTransferController::PIN_RETURN) === true,
        ]);
    }

    public function store(Request $request, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        SecurityPinInput::merge($request, 'pin', 'pin_confirmation');
        $validated = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed', new NotWeakSecurityPin],
        ]);

        $pins->setInitial($member, $validated['pin']);
        $returnToTransfer = $request->session()->pull(\App\Http\Controllers\Member\MemberTransferController::PIN_RETURN) === true;
        $this->refreshSession($request);

        return redirect()
            ->route($returnToTransfer ? 'member.transfers.create' : 'member.security-pin')
            ->with('success', 'Your security PIN is set.');
    }

    public function change(Request $request, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        SecurityPinInput::merge($request, 'current_pin', 'pin', 'pin_confirmation');
        $validated = $request->validate([
            'current_pin' => ['required', 'digits:6'],
            'pin' => ['required', 'digits:6', 'confirmed', new NotWeakSecurityPin],
        ]);

        $pins->change($member, $validated['current_pin'], $validated['pin']);
        $request->session()->forget([
            MemberSecurityPin::SESSION_CHALLENGE,
            MemberSecurityPin::SESSION_PENDING,
        ]);
        $this->refreshSession($request);

        return redirect()
            ->route('member.security-pin')
            ->with('success', 'Your security PIN was changed.');
    }

    public function forgot(Request $request, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        $result = $pins->requestReset($member, (string) $request->ip());
        if ($result->blocker !== null) {
            return back()->withErrors(['otp' => $result->blocker]);
        }

        $request->session()->forget(MemberSecurityPin::SESSION_CHALLENGE);
        $request->session()->put(MemberSecurityPin::SESSION_PENDING, true);

        return redirect()
            ->route('member.security-pin')
            ->with('success', MemberSecurityPin::GENERIC_OTP_MESSAGE);
    }

    public function verify(Request $request, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        SecurityPinInput::merge($request, 'otp');
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $resetId = $pins->verifyOtp($member, $validated['otp']);
        $request->session()->put(MemberSecurityPin::SESSION_CHALLENGE, $resetId);
        $request->session()->forget(MemberSecurityPin::SESSION_PENDING);

        return redirect()
            ->route('member.security-pin')
            ->with('success', 'The code was verified. Choose a new PIN.');
    }

    public function reset(Request $request, MemberSecurityPin $pins): RedirectResponse
    {
        $member = $this->member($request);
        $resetId = $pins->pendingResetId($request, $member);
        if ($resetId === null) {
            throw ValidationException::withMessages([
                'pin' => 'Verify the email code before choosing a new PIN.',
            ]);
        }

        SecurityPinInput::merge($request, 'pin', 'pin_confirmation');
        $validated = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed', new NotWeakSecurityPin],
        ]);

        $pins->completeReset($member, $resetId, $validated['pin']);
        $request->session()->forget([
            MemberSecurityPin::SESSION_CHALLENGE,
            MemberSecurityPin::SESSION_PENDING,
        ]);
        $this->refreshSession($request);

        return redirect()
            ->route('member.security-pin')
            ->with('success', 'Your security PIN was reset.');
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }

    private function refreshSession(Request $request): void
    {
        $request->session()->regenerate();
    }

    private function maskEmail(string $email): ?string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);

        return $visible.str_repeat('*', max(1, strlen($name) - 1)).'@'.$domain;
    }
}
