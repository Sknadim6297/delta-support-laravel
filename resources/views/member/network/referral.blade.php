@extends('member.layouts.app')
@section('content')
<div>
    <header>
        <h1>My Referral Link</h1>
        <p>Share this link. New registrations use your member ID as the sponsor.</p>
    </header>
    <div>
        <p>{{ $link }}</p>
        <p>Your member ID: {{ $member->member_id }}</p>
    </div>
</div>
@endsection
