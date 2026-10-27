@extends('member.layouts.app')
@section('title', 'Withdrawal '.$withdrawal->reference)
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="ds-page-title">{{ $withdrawal->reference }}</h1>
            <p class="ds-page-copy">Stored withdrawal details. This page does not change the request.</p>
        </div>
        @include('partials.withdrawal-status', ['status' => $withdrawal->status])
    </header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    <p class="mt-4"><a class="font-semibold text-brand-700" href="{{ route('member.withdrawals.history') }}">Back to history</a></p>
</div>
@endsection
