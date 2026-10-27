@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border bg-white p-6">
    <h1 class="text-2xl font-bold text-navy-900">{{ $purchase->reference }}</h1>
    <p class="mt-2 text-sm">{{ $purchase->package_name }} · USD {{ $purchase->price_usd }}</p>
    <p class="mt-2 text-sm font-semibold">{{ $purchase->status === 'pending' ? 'Pending Verification' : ucfirst($purchase->status) }}</p>
    <p class="mt-2 text-sm">Transaction reference: {{ $purchase->payment_reference }}</p>
    @if($purchase->rejection_reason)<p class="mt-2 text-sm text-red-700">{{ $purchase->rejection_reason }}</p>@endif
    @if($purchase->status === 'pending')
        <p class="mt-4 whitespace-pre-line text-sm text-slate-600">{{ $settings->instructions }}</p>
    @endif
</div>
@endsection
