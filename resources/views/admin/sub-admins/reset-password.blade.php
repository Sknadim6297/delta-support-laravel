@extends('admin.layouts.app')
@section('title', 'Reset Sub-Admin Password')
@section('page-title', 'Reset Sub-Admin Password')
@section('content')
<div class="ds-catalog ds-subadmin">
    <x-ui.page-header
        kicker="Security"
        title="Reset password"
        :lead="'Login ID '.$subAdmin->login_id.' — '.$subAdmin->name"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin.sub-admins.show', $subAdmin)" variant="secondary">Back to account</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card title="New password" subtitle="Existing sessions are signed out after the password is saved.">
        <form class="ds-settings-form ds-password-form" method="POST" action="{{ route('admin.sub-admins.password.update', $subAdmin) }}">
            @csrf
            @method('PUT')
            <div class="ds-field-grid">
                <label>
                    <span>New Password <span>*</span></span>
                    <input type="password" name="password" autocomplete="new-password" required>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>
                <label>
                    <span>Confirm Password <span>*</span></span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                    @error('password_confirmation')<small>{{ $message }}</small>@enderror
                </label>
            </div>
            <p class="ds-pkg-hint">{{ \App\Rules\StrongPassword::MESSAGE }}</p>
            <div class="ds-form-actions">
                <x-ui.button type="submit">Save new password</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection