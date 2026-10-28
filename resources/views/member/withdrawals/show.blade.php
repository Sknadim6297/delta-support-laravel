@extends('member.layouts.app')
@section('title', 'Withdrawal '.$withdrawal->reference)
@section('page-title', 'Withdrawal '.$withdrawal->reference)
@section('content')
<div>
    <header class="ds-page-head">
        <p class="ds-lead">Stored withdrawal details. This page does not change the request.</p>
        @include('partials.withdrawal-status', ['status' => $withdrawal->status])
    </header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    <p><a href="{{ route('member.withdrawals.history') }}">Back to history</a></p>
</div>
@endsection
