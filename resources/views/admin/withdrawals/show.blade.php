@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <div>
            <h1>{{ $withdrawal->reference }}</h1>
            <p>Read-only request record. No review or payout action is available.</p>
        </div>
        @include('partials.withdrawal-status', ['status' => $withdrawal->status])
    </header>
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    <p><a href="{{ route('admin.withdrawals.index') }}">Back to requests</a></p>
</div>
@endsection
