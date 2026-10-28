@extends('admin.layouts.app')

@section('title', 'Admin Profile')
@section('page-title', 'Admin Profile')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="Admin Profile"
        lead="The account currently signed in to the admin panel."
    />

    <div class="ds-account-stack">
        <x-ui.card title="Profile Information" subtitle="Update the name and email for this admin account.">
            <form class="ds-settings-form ds-account-form" method="POST" action="{{ route('admin.settings.profile.update') }}">
                @csrf
                @method('PUT')
                <div class="ds-field-grid">
                    <label>
                        <span>Admin Name</span>
                        <input name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>
                        @error('name')<small>{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Email Address</span>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>
                        @error('email')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="ds-field-span">
                        <span>Current Password</span>
                        <input type="password" name="email_password" autocomplete="current-password" placeholder="Required only when changing the email">
                        @error('email_password')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
                <div class="ds-form-actions">
                    <button type="submit">Save Changes</button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Change Password" subtitle="Use at least 8 characters. The current password is required.">
            <form class="ds-settings-form ds-password-form ds-account-form" method="POST" action="{{ route('admin.settings.profile.password') }}">
                @csrf
                @method('PUT')
                <div class="ds-field-grid">
                    <label>
                        <span>Current Password</span>
                        <input type="password" name="current_password" autocomplete="current-password">
                        @error('current_password')<small>{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>New Password</span>
                        <input type="password" name="password" autocomplete="new-password">
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>
                    <label>
                        <span>Confirm New Password</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password">
                        @error('password_confirmation')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
                <div class="ds-form-actions">
                    <button type="submit">Update Password</button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
