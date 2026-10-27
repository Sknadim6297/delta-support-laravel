<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.general', ['settings' => CompanySetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        CompanySetting::current()->update($data);

        return redirect()->route('admin.settings.general')->with('success', 'Company details saved.');
    }

    public function branding(): View
    {
        return view('admin.settings.branding');
    }

    public function security(): View
    {
        return view('admin.settings.security');
    }

    public function profile(Request $request): View
    {
        return view('admin.settings.profile', ['user' => $request->user()]);
    }
}
