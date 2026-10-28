<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function index()
    {
        return view('admin.change-password.index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', new StrongPassword],
            'new_password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => 'Old Password is required.',
            'new_password.required' => 'New Password is required.',
            'new_password.confirmed' => 'New Password and Confirm New Password do not match.',
            'new_password_confirmation.required' => 'Confirm New Password is required.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
        }

        $user->password = Hash::make($validated['new_password']);
        $user->must_change_password = false;
        $user->save();
        $request->session()->regenerate();
        AdminActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'admin_password_changed',
            'subject' => 'admin',
            'format' => null,
            'scope' => 'admin',
            'filters' => [],
            'row_count' => 1,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }
}
