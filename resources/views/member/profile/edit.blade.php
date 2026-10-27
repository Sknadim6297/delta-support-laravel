@extends('member.layouts.app')
@section('title', 'Edit Member Profile')
@section('content')
<div>
    <header>
        <h1>Edit Profile</h1>
        <p>Update the contact details on your account.</p>
    </header>
    <form method="POST" action="{{ route('member.profile.update') }}">
        @csrf
        @method('PUT')
        <div>
            @if($errors->any())<div>{{ $errors->first() }}</div>@endif
            <label><span>Member Name</span><input type="text" name="member_name" value="{{ old('member_name', $member->member_name) }}" required></label>
            <label><span>Email ID</span><input type="email" name="email" value="{{ old('email', $member->email) }}" required></label>
            <label><span>Mobile No</span><input type="text" name="mobile_no" value="{{ old('mobile_no', $member->mobile_no) }}" maxlength="15" required></label>
            <label><span>BEP20 Wallet Address</span><input type="text" name="wallet_address" value="{{ old('wallet_address', $member->wallet_address) }}" maxlength="255" placeholder="Enter your BEP20 wallet address"></label>
        </div>
        <div>
            <button type="submit">Update Profile</button>
            <a href="{{ route('member.profile') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
