@extends('member.layouts.app')
@section('content')
<div>
    <h1>{{ $purchase->reference }}</h1>
    <p>{{ $purchase->package_name }} · {{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</p>
    <p>{{ $purchase->statusLabel() }}</p>
    <p>Transaction reference: {{ $purchase->payment_reference }}</p>
    <p><span>Destinations:</span> {{ $purchase->destinations ?: 'Not set' }}</p>
    <p><span>Duration:</span> {{ $purchase->duration ?: 'Not set' }}</p>
    @if($purchase->benefits)<p>{{ $purchase->benefits }}</p>@endif
    @if($purchase->rejection_reason)<p>{{ $purchase->rejection_reason }}</p>@endif
    @if($purchase->status === 'pending')
        <p>{{ $settings->instructions }}</p>
    @endif
</div>
@endsection
