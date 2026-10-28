<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('member.profile.show', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('member.profile.show', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $this->currentMember($request);

        $request->merge([
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
        ]);

        $validator = Validator::make($request->all(), [
            'member_name' => Member::nameRules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member->id)],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            'current_password' => ['nullable', 'string'],
        ]);

        $validator->fails();

        if ($request->exists('wallet_address') && trim((string) $request->input('wallet_address')) !== trim((string) $member->wallet_address)) {
            $validator->errors()->add(
                'wallet_address',
                'BEP20 Wallet Address cannot be changed until wallet verification is available.'
            );
        }

        if ($request->exists('pan_card_no') && strtoupper(trim((string) $request->input('pan_card_no'))) !== strtoupper(trim((string) $member->pan_card_no))) {
            $validator->errors()->add(
                'pan_card_no',
                'PAN Card Number cannot be changed until verification is available.'
            );
        }

        if ($validator->errors()->isNotEmpty()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        if (Member::query()
            ->where('mobile_no', $validated['mobile_no'])
            ->whereKeyNot($member->id)
            ->count() >= 3) {
            return back()->withErrors([
                'mobile_no' => 'This mobile number has already been used 3 times.',
            ])->withInput();
        }

        $emailChanged = strcasecmp($validated['email'], (string) $member->email) !== 0;
        $mobileChanged = $validated['mobile_no'] !== (string) $member->mobile_no;

        if ($emailChanged || $mobileChanged) {
            $password = (string) $request->input('current_password', '');
            $hash = $member->getRawOriginal('password');

            if ($password === '' || ! is_string($hash) || $hash === '' || ! Hash::check($password, $hash)) {
                return back()->withErrors([
                    'current_password' => 'Enter your current password to change your email address or mobile number.',
                ])->withInput();
            }
        }

        $member->forceFill([
            'member_name' => $validated['member_name'],
            'email' => $validated['email'],
            'mobile_no' => $validated['mobile_no'],
        ])->save();

        return redirect()
            ->route('member.profile')
            ->with('success', 'Profile updated successfully.');
    }

    private function currentMember(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }
}
