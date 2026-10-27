@extends('member.layouts.app')
@section('title', 'Member Profile')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="ds-page-title">My Profile</h1>
            <p class="ds-page-copy">Your account details.</p>
        </div>
        <a href="{{ route('member.profile.edit') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Edit Profile</a>
    </header>
    <dl class="ds-card grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold text-navy-900">Member Name</dt><dd class="mt-1">{{ $member->member_name }}</dd></div>
        <div><dt class="font-semibold text-navy-900">Email ID</dt><dd class="mt-1">{{ $member->email }}</dd></div>
        <div><dt class="font-semibold text-navy-900">Mobile No</dt><dd class="mt-1">{{ $member->mobile_no }}</dd></div>
        <div><dt class="font-semibold text-navy-900">BEP20 Wallet Address</dt><dd class="mt-1 break-all">{{ $member->wallet_address ?: 'Not Added' }}</dd></div>
    </dl>
</div>
@endsection
