@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $package->typeLabel() }}</p>
    <h1 class="ds-page-title mt-1">{{ $package->name }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $package->code }}</p>
    <p class="mt-4 text-2xl font-bold text-brand-700">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</p>
    @if($package->image_path)
        <img src="{{ route('member.packages.image', $package) }}" alt="{{ $package->name }}" class="mt-4 max-h-64 w-full rounded-lg border object-contain">
    @endif
    @if($package->description)<p class="mt-4 text-sm text-slate-700">{{ $package->description }}</p>@endif
    @if($package->full_description)<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $package->full_description }}</p>@endif

    <h2 class="mt-6 font-semibold text-navy-900">Tour</h2>
    <p class="mt-2 text-sm">{{ $package->tour_title ?: 'Tour title not set' }}</p>
    <p class="mt-2 text-sm"><span class="font-semibold">Destinations:</span> {{ $package->destinations ?: 'Not set' }}</p>
    <p class="mt-2 text-sm"><span class="font-semibold">Duration:</span> {{ $package->duration ?: 'Not set' }}</p>
    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $package->tour_details }}</p>
    @if($package->benefits)
        <h2 class="mt-5 font-semibold text-navy-900">Benefits</h2>
        <p class="mt-2 whitespace-pre-line text-sm">{{ $package->benefits }}</p>
    @endif
    @if($package->inclusions)
        <h2 class="mt-5 font-semibold text-navy-900">Inclusions</h2>
        <p class="mt-2 whitespace-pre-line text-sm">{{ $package->inclusions }}</p>
    @endif
    @if($package->exclusions)
        <h2 class="mt-5 font-semibold text-navy-900">Exclusions</h2>
        <p class="mt-2 whitespace-pre-line text-sm">{{ $package->exclusions }}</p>
    @endif
    @if($package->reservesFutureIncome())
        <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">ROI and level commission are reserved for a later review. This package does not credit income.</p>
    @endif

    <h2 class="mt-6 font-semibold text-navy-900">Payment</h2>
    @if($settings->isReady())
        <p class="mt-2 text-sm">Accepted token {{ $settings->currency_label }} on {{ $settings->network }}</p>
        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Send {{ $package->denomination === 'USDT' ? 'USDT' : ($settings->currency_label ?: 'the accepted token') }} only on the exact network shown here. This application does not verify the blockchain.</p>
        <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $settings->instructions }}</p>
        @if($settings->receiving_address)<p class="mt-2 break-all text-sm font-semibold">{{ $settings->receiving_address }}</p>@endif
        @if($settings->qr_image_path)<img src="{{ route('member.payments.qr') }}" alt="Payment QR" class="mt-3 h-40 w-40 rounded border object-contain">@endif
        <form method="POST" action="{{ route('member.packages.purchase', $package) }}" class="mt-4 grid gap-3">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <label class="grid gap-1 text-sm font-semibold">Transaction ID<input name="payment_reference" value="{{ old('payment_reference') }}" class="rounded-lg border px-3 py-2 font-normal" required maxlength="128" autocomplete="off"></label>
            @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <button class="w-fit rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Submit for verification</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">Submitting a reference does not confirm payment or activate the package. Status stays pending until an administrator verifies it. The same package can be requested again with a different reference.</p>
    @else
        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Payment Currently Unavailable. This package stays visible, and no payment request is created until payment is enabled.</p>
    @endif
</div>
@endsection
