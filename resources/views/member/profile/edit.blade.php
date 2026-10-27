@extends('member.layouts.app')
@section('title', 'Edit Member Profile')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Edit Profile</h1>
        <p class="ds-page-copy">Update the contact details on your account.</p>
    </header>
    <form method="POST" action="{{ route('member.profile.update') }}" class="ds-card">
        @csrf
        @method('PUT')
        <div class="ds-form ds-form-grid">
            @if($errors->any())<div class="ds-error ds-span-2">{{ $errors->first() }}</div>@endif
            <label class="ds-field"><span>Member Name</span><input type="text" name="member_name" value="{{ old('member_name', $member->member_name) }}" required></label>
            <label class="ds-field"><span>Email ID</span><input type="email" name="email" value="{{ old('email', $member->email) }}" required></label>
            <label class="ds-field"><span>Mobile No</span><input type="text" name="mobile_no" value="{{ old('mobile_no', $member->mobile_no) }}" maxlength="15" required></label>
            <label class="ds-field"><span>BEP20 Wallet Address</span><input type="text" name="wallet_address" value="{{ old('wallet_address', $member->wallet_address) }}" maxlength="255" placeholder="Enter your BEP20 wallet address"></label>
        </div>
        <div class="ds-actions">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Update Profile</button>
            <a href="{{ route('member.profile') }}" class="text-sm font-semibold text-navy-900">Cancel</a>
        </div>
    </form>
</div>
@endsection
