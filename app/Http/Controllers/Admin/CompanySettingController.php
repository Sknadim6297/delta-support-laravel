<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Models\CompanySetting;
use App\Support\BrandTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $settings = CompanySetting::current();

        return view('admin.settings.branding', [
            'settings' => $settings,
            'colors' => BrandTokens::palette(),
            'logoUrl' => BrandTokens::logoUrl(),
        ]);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'logo.required' => 'Choose a logo to upload.',
            'logo.mimes' => 'The logo must be a JPG, PNG, or WebP image.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
        ]);

        $file = $request->file('logo');
        $mime = $file?->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return back()->withErrors(['logo' => 'The logo must be a JPG, PNG, or WebP image.']);
        }

        $path = $file->store('branding-logos', 'local');
        if (! BrandTokens::isManagedLogo($path)) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['logo' => 'The logo could not be stored.']);
        }

        $settings = CompanySetting::current();
        $previous = $settings->logo_path;

        try {
            $settings->update(['logo_path' => $path]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($previous !== $path) {
            BrandTokens::deleteManaged($previous);
        }

        return redirect()->route('admin.settings.branding')->with('success', 'Logo saved.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $settings = CompanySetting::current();
        $previous = $settings->logo_path;
        $settings->update(['logo_path' => null]);
        BrandTokens::deleteManaged($previous);

        return redirect()->route('admin.settings.branding')->with('success', 'Custom logo removed. The default logo is restored.');
    }

    public function updateColors(Request $request): RedirectResponse
    {
        $hex = ['required', 'string', 'regex:/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'];
        $data = $request->validate([
            'colors' => ['required', 'array'],
            'colors.primary' => $hex,
            'colors.primary_hover' => $hex,
            'colors.navy' => $hex,
            'colors.canvas' => $hex,
            'colors.border' => $hex,
        ], [
            'colors.*.required' => 'Enter a HEX color.',
            'colors.*.regex' => 'Enter a HEX color like #0878d1.',
        ]);

        CompanySetting::current()->update([
            'primary_color' => BrandTokens::normalize($data['colors']['primary']),
            'primary_hover_color' => BrandTokens::normalize($data['colors']['primary_hover']),
            'navy_color' => BrandTokens::normalize($data['colors']['navy']),
            'canvas_color' => BrandTokens::normalize($data['colors']['canvas']),
            'border_color' => BrandTokens::normalize($data['colors']['border']),
        ]);

        return redirect()->route('admin.settings.branding')->with('success', 'Brand colors saved.');
    }

    public function resetColors(): RedirectResponse
    {
        CompanySetting::current()->update([
            'primary_color' => null,
            'primary_hover_color' => null,
            'navy_color' => null,
            'canvas_color' => null,
            'border_color' => null,
        ]);

        return redirect()->route('admin.settings.branding')->with('success', 'Brand colors reset to the defaults.');
    }

    public function logo(): BinaryFileResponse
    {
        $settings = CompanySetting::query()->first();
        $path = $settings?->logo_path;

        abort_unless(
            BrandTokens::isManagedLogo($path) && Storage::disk('local')->exists($path),
            404
        );

        return response()->file(Storage::disk('local')->path($path));
    }

    public function security(): View
    {
        return view('admin.settings.security');
    }

    public function profile(Request $request): View
    {
        return view('admin.settings.profile', ['user' => $request->user()]);
    }

    public function updateProfile(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $email = $request->string('email')->toString();
        $emailChanged = $email !== $user->email;

        if ($emailChanged && ! Hash::check($request->string('email_password')->toString(), $user->password)) {
            return back()
                ->withErrors(['email_password' => 'The current password is incorrect.'])
                ->withInput($request->except('email_password'));
        }

        $user->name = $request->string('name')->toString();
        if ($emailChanged) {
            $user->email = $email;
        }
        $user->save();

        return redirect()
            ->route('admin.settings.profile')
            ->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->password = Hash::make($request->string('password')->toString());
        $user->save();
        $request->session()->regenerate();

        return redirect()
            ->route('admin.settings.profile')
            ->with('success', 'Password updated.');
    }
}
