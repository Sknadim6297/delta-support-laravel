<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MemberChangePasswordController extends Controller
{
    public function index(Request $request)
    {
        return view('member.change-password.index', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => 'Old Password is required.',
            'new_password.required' => 'New Password is required.',
            'new_password.min' => 'New Password must be at least 8 characters.',
            'new_password.confirmed' => 'New Password and Confirm New Password do not match.',
            'new_password_confirmation.required' => 'Confirm New Password is required.',
        ]);

        $member = $this->currentMember($request);

        if (!Hash::check($validated['current_password'], (string) $member->password)) {
            return back()
                ->withErrors([
                    'current_password' => 'The current password is incorrect.',
                ])
                ->withInput();
        }

        $member->password = Hash::make($validated['new_password']);
        $member->save();

        return redirect()
            ->route('member.change-password')
            ->with('success', 'Password changed successfully.');
    }

    private function currentMember(Request $request): Member
    {
        return Member::where(
            'member_id',
            \App\Support\MemberContext::id($request)
        )->firstOrFail();
    }
}