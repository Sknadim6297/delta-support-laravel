<?php

namespace App\Http\Controllers;

use App\Services\MemberPasswordSetupMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberPasswordSetupController extends Controller
{
    public function create(string $token, MemberPasswordSetupMailer $setups): View
    {
        return view('auth.member-password-setup', [
            'token' => $token,
            'memberId' => $setups->memberIdFor($token),
        ]);
    }

    public function store(Request $request, string $token, MemberPasswordSetupMailer $setups): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $member = $setups->consume($token, (string) $validated['password']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('login')
            ->with('success', 'Password saved for '.$member->member_id.'. You can sign in with that Member ID.');
    }
}
