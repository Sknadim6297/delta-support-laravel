@extends('member.layouts.app')
@section('title', 'Withdrawal '.$withdrawal->reference)
@section('content')
<div>
    <header>
        <div>
            <h1>{{ $withdrawal->reference }}</h1>
            <p>Stored withdrawal details. This page does not change the request.</p>
        </div>
        @include('partials.withdrawal-status', ['status' => $withdrawal->status])
    </header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    <p><a href="{{ route('member.withdrawals.history') }}">Back to history</a></p>
</div>
@endsection
