@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>General Settings</h1>
        <p>Manage company information and preferences.</p>
    </header>
    <form method="POST" action="{{ route('admin.settings.general.update') }}">
        @csrf
        @method('PUT')
        <div>
            <label>
                <span>Company Name</span>
                <input name="company_name" value="{{ old('company_name', $settings->company_name) }}">
                @error('company_name')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Company Address</span>
                <textarea name="address" rows="4">{{ old('address', $settings->address) }}</textarea>
                @error('address')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Email Address</span>
                <input type="email" name="email" value="{{ old('email', $settings->email) }}">
                @error('email')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Phone Number</span>
                <input name="phone" value="{{ old('phone', $settings->phone) }}">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>
        </div>
        <div>
            <button type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
