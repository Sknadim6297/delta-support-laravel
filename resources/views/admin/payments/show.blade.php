@extends('admin.layouts.app')

@section('title', 'Payment '.$purchase->reference)
@section('page-title', 'Payment '.$purchase->reference)

@section('content')
@php
    $tone = match ($purchase->status) {
        'approved' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
@endphp
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Payments"
        :title="'Payment '.$purchase->reference"
        lead="Verify this transaction on the configured network before approving it. The transaction ID alone does not activate the package."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.payments.index', ['status' => $purchase->status]) }}" variant="secondary">Back to payments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Payment details">
        <dl class="ds-review-grid">
            <div>
                <dt>Status</dt>
                <dd>
                    <x-ui.badge :tone="$tone">{{ $purchase->paymentStatusLabel() }}</x-ui.badge>
                    <x-ui.badge :tone="$purchase->status === \App\Models\PackagePurchase::APPROVED ? 'success' : 'muted'">{{ $purchase->activationLabel() }}</x-ui.badge>
                </dd>
            </div>
            <div>
                <dt>Member</dt>
                <dd>{{ $purchase->member_id }}@if($purchase->member) · {{ $purchase->member->member_name }}@endif</dd>
            </div>
            <div>
                <dt>Package</dt>
                <dd>{{ $purchase->package_name }} ({{ $purchase->package_code }})</dd>
            </div>
            <div>
                <dt>Saved price</dt>
                <dd>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</dd>
            </div>
            <div>
                <dt>Asset / token</dt>
                <dd>{{ $purchase->currency_label ?: 'Not recorded' }}</dd>
            </div>
            <div>
                <dt>Network</dt>
                <dd>{{ $purchase->network ?: 'Not recorded' }}</dd>
            </div>
            <div class="ds-field-span">
                <dt>Transaction ID</dt>
                <dd class="ds-pay-address">{{ $purchase->payment_reference }}</dd>
            </div>
            <div>
                <dt>Submitted</dt>
                <dd>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</dd>
            </div>
            @if($reviewer)
                <div>
                    <dt>Reviewed by</dt>
                    <dd>{{ $reviewer->email }}@if($purchase->reviewed_at) · {{ $purchase->reviewed_at->timezone('Asia/Kolkata')->format('d M Y H:i') }}@endif</dd>
                </div>
            @endif
        </dl>
        @if($purchase->rejection_reason)
            <x-ui.alert type="error">{{ $purchase->rejection_reason }}</x-ui.alert>
        @endif
        @if($purchase->invoice)
            <p><a href="{{ route('admin.invoices.show', $purchase->invoice) }}">Invoice {{ $purchase->invoice->invoice_number }}</a></p>
        @endif
    </x-ui.card>

    @if($purchase->status === \App\Models\PackagePurchase::PENDING)
        <div class="ds-review-actions">
            <x-ui.card title="Approve" subtitle="Approve only after the transaction is confirmed outside this application. This activates this purchase and does not credit ROI or commission.">
                <form class="ds-pay-form" method="POST" action="{{ route('admin.payments.approve', $purchase) }}">
                    @csrf
                    <button type="submit">Approve payment</button>
                </form>
            </x-ui.card>
            <x-ui.card title="Reject">
                <form class="ds-pay-form" method="POST" action="{{ route('admin.payments.reject', $purchase) }}">
                    @csrf
                    <label><span>Rejection reason</span><textarea name="reason" rows="3" required maxlength="1000">{{ old('reason') }}</textarea></label>
                    @if($errors->any())<div class="validation-message">{{ $errors->first() }}</div>@endif
                    <button type="submit">Reject payment</button>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
@endsection
