@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border bg-white p-6">
    <h1 class="text-2xl font-bold text-navy-900">{{ $package->name }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $package->code }}</p>
    <p class="mt-4 text-2xl font-bold text-brand-700">USD {{ $package->price_usd }}</p>
    <p class="mt-3 text-sm">{{ $package->description }}</p>
    <h2 class="mt-5 font-semibold">Tour benefit</h2>
    <p class="text-sm">{{ $package->tour_title ?: 'Tour details will be added by the administrator.' }}</p>
    <p class="mt-2 text-sm text-slate-600">{{ $package->tour_details }}</p>
    <p class="mt-2 text-sm">Duration: {{ $package->duration ?: 'Not set' }}</p>
    <p class="mt-2 whitespace-pre-line text-sm">{{ $package->benefits }}</p>
    @if($package->image_path)
        <img src="{{ route('member.packages.image', $package) }}" alt="{{ $package->name }}" class="mt-4 max-h-56 rounded-lg border object-contain">
    @endif

    <h2 class="mt-6 font-semibold">Payment</h2>
    @if($settings->isReady())
        <p class="mt-2 text-sm">{{ $settings->currency_label }} on {{ $settings->network }}</p>
        <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $settings->instructions }}</p>
        @if($settings->receiving_address)<p class="mt-2 break-all text-sm font-semibold">{{ $settings->receiving_address }}</p>@endif
        @if($settings->qr_image_path)<img src="{{ route('member.payments.qr') }}" alt="Payment QR" class="mt-3 h-40 w-40 rounded border object-contain">@endif
        <form method="POST" action="{{ route('member.packages.purchase', $package) }}" class="mt-4 grid gap-3">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <label class="grid gap-1 text-sm font-semibold">Transaction reference<input name="payment_reference" value="{{ old('payment_reference') }}" class="rounded-lg border px-3 py-2 font-normal" required></label>
            @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <button class="rounded-lg bg-brand-500 px-4 py-2 font-semibold text-white">Submit for verification</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">Submitting a reference does not confirm payment. Status stays pending until an administrator verifies it.</p>
    @else
        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">This package cannot be purchased until payment settings are configured.</p>
    @endif
</div>
@endsection
