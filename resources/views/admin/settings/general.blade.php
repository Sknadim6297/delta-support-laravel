@extends('admin.layouts.app')

@section('title', 'General Settings')
@section('page-title', 'General Settings')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="General Settings"
        lead="Manage company information and preferences."
    />

    <form class="ds-settings-form" method="POST" action="{{ route('admin.settings.general.update') }}">
        @csrf
        @method('PUT')
        <div class="ds-field-grid">
            <label class="ds-field-span">
                <span>Company Name</span>
                <input name="company_name" value="{{ old('company_name', $settings->company_name) }}" maxlength="160" autocomplete="organization">
                @error('company_name')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Email Address</span>
                <input type="email" name="email" value="{{ old('email', $settings->email) }}" maxlength="160" autocomplete="email">
                @error('email')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Phone Number</span>
                <input name="phone" value="{{ old('phone', $settings->phone) }}" maxlength="40" autocomplete="tel">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>
            <label class="ds-field-span">
                <span>Company Address</span>
                <textarea name="address" rows="4" maxlength="2000">{{ old('address', $settings->address) }}</textarea>
                @error('address')<small>{{ $message }}</small>@enderror
            </label>
        </div>
        <div class="ds-form-actions">
            <button type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
