@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Payment {{ $purchase->reference }}</h1>
        <p class="ds-page-copy">Verify this transaction on the configured network before approving it. The transaction ID alone does not activate the package.</p>
    </header>
    <div class="ds-card grid gap-3 text-sm">
        <p><span class="font-semibold text-navy-900">Status:</span> {{ $purchase->statusLabel() }}</p>
        <p><span class="font-semibold text-navy-900">Member:</span> {{ $purchase->member_id }} @if($purchase->member) · {{ $purchase->member->member_name }} @endif</p>
        <p><span class="font-semibold text-navy-900">Package:</span> {{ $purchase->package_name }} ({{ $purchase->package_code }})</p>
        <p><span class="font-semibold text-navy-900">Saved USD price:</span> USD {{ $purchase->price_usd }}</p>
        <p><span class="font-semibold text-navy-900">Asset / token:</span> {{ $purchase->currency_label ?: 'Not recorded' }}</p>
        <p><span class="font-semibold text-navy-900">Network:</span> {{ $purchase->network ?: 'Not recorded' }}</p>
        <p class="break-all"><span class="font-semibold text-navy-900">Transaction ID:</span> {{ $purchase->payment_reference }}</p>
        <p><span class="font-semibold text-navy-900">Submitted:</span> {{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</p>
        @if($reviewer)
            <p><span class="font-semibold text-navy-900">Reviewed by:</span> {{ $reviewer->email }} @if($purchase->reviewed_at) · {{ $purchase->reviewed_at->timezone('Asia/Kolkata')->format('d M Y H:i') }} @endif</p>
        @endif
        @if($purchase->rejection_reason)
            <p class="rounded-lg bg-red-50 p-3 text-red-700">{{ $purchase->rejection_reason }}</p>
        @endif
        @if($purchase->invoice)
            <p><a class="font-semibold text-brand-700" href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice {{ $purchase->invoice->invoice_number }}</a></p>
        @endif
    </div>

    @if($purchase->status === \App\Models\PackagePurchase::PENDING)
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}" class="ds-card">
                @csrf
                <p class="mb-3 text-sm text-slate-600">Approve only after the transaction is confirmed outside this application. This activates this purchase and does not credit ROI or commission.</p>
                <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Approve payment</button>
            </form>
            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}" class="ds-card">
                @csrf
                <label class="ds-field"><span>Rejection reason</span><textarea name="reason" rows="3" required maxlength="1000">{{ old('reason') }}</textarea></label>
                @if($errors->any())<div class="ds-error mt-3">{{ $errors->first() }}</div>@endif
                <div class="ds-actions">
                    <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white" type="submit">Reject payment</button>
                </div>
            </form>
        </div>
    @endif
    <p class="mt-4"><a class="text-sm font-semibold text-navy-900" href="{{ route('admin.payments.index', ['status' => $purchase->status]) }}">Back to payments</a></p>
</div>
@endsection
