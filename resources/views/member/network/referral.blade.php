@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">My Referral Link</h1>
        <p class="ds-page-copy">Share this link. New registrations use your member ID as the sponsor.</p>
    </header>
    <div class="ds-card">
        <p class="break-all rounded-lg bg-slate-50 p-3 text-sm font-semibold text-navy-900">{{ $link }}</p>
        <p class="mt-3 text-sm">Your member ID: {{ $member->member_id }}</p>
    </div>
</div>
@endsection
