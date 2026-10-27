@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">General Settings</h1>
        <p class="ds-page-copy">Manage company information and preferences.</p>
    </header>
    <form method="POST" action="{{ route('admin.settings.general.update') }}" class="ds-card">
        @csrf
        @method('PUT')
        <div class="ds-form ds-form-grid">
            <label class="ds-field ds-span-2">
                <span>Company Name</span>
                <input name="company_name" value="{{ old('company_name', $settings->company_name) }}">
                @error('company_name')<small class="ds-error">{{ $message }}</small>@enderror
            </label>
            <label class="ds-field ds-span-2">
                <span>Company Address</span>
                <textarea name="address" rows="4">{{ old('address', $settings->address) }}</textarea>
                @error('address')<small class="ds-error">{{ $message }}</small>@enderror
            </label>
            <label class="ds-field">
                <span>Email Address</span>
                <input type="email" name="email" value="{{ old('email', $settings->email) }}">
                @error('email')<small class="ds-error">{{ $message }}</small>@enderror
            </label>
            <label class="ds-field">
                <span>Phone Number</span>
                <input name="phone" value="{{ old('phone', $settings->phone) }}">
                @error('phone')<small class="ds-error">{{ $message }}</small>@enderror
            </label>
        </div>
        <div class="ds-actions">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
