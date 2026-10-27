@extends('member.layouts.app')
@section('title', 'Member Profile')
@section('content')
<div>
    <header>
        <div>
            <h1>My Profile</h1>
            <p>Your account details.</p>
        </div>
        <a href="{{ route('member.profile.edit') }}">Edit Profile</a>
    </header>
    <dl>
        <div><dt>Member Name</dt><dd>{{ $member->member_name }}</dd></div>
        <div><dt>Email ID</dt><dd>{{ $member->email }}</dd></div>
        <div><dt>Mobile No</dt><dd>{{ $member->mobile_no }}</dd></div>
        <div><dt>BEP20 Wallet Address</dt><dd>{{ $member->wallet_address ?: 'Not Added' }}</dd></div>
    </dl>
</div>
@endsection
