@extends('member.layouts.app')

@section('title', 'Payment '.$purchase->reference)
@section('page-title', 'Payment '.$purchase->reference)

@section('content')
<div class="ds-catalog">
    <x-ui.page-header kicker="Payments" :title="'Payment '.$purchase->reference" lead="This page shows the request that was saved. It does not confirm that the payment was received.">
        <x-slot:actions>
            <x-ui.button href="{{ route('member.payments.index') }}" variant="secondary">My Payments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Payment details">
        <div class="ds-package-badges">
            <x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->statusLabel() }}</x-ui.badge>
            <x-ui.badge :tone="$purchase->status === \App\Models\PackagePurchase::APPROVED ? 'success' : 'muted'">{{ $purchase->activationLabel() }}</x-ui.badge>
        </div>
        <dl class="ds-review-grid">
            <div>
                <dt>Package</dt>
                <dd>{{ $purchase->package_name }}</dd>
            </div>
            <div>
                <dt>Amount</dt>
                <dd>{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</dd>
            </div>
            <div>
                <dt>Token</dt>
                <dd>{{ $purchase->currency_label ?: 'Not recorded' }}</dd>
            </div>
            <div>
                <dt>Network</dt>
                <dd>{{ $purchase->network ?: 'Not recorded' }}</dd>
            </div>
            <div class="ds-field-span">
                <dt>Transaction Reference</dt>
                <dd class="ds-pay-address">{{ $purchase->payment_reference }}</dd>
            </div>
            <div>
                <dt>Submitted</dt>
                <dd>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') }}</dd>
            </div>
            <div>
                <dt>Reviewed</dt>
                <dd>{{ $purchase->reviewed_at ? $purchase->reviewed_at->timezone('Asia/Kolkata')->format('d M Y H:i') : 'Not recorded' }}</dd>
            </div>
        </dl>
        @if($purchase->rejection_reason)
            <x-ui.alert type="error">{{ $purchase->rejection_reason }}</x-ui.alert>
        @endif
        @if($purchase->invoice)
            <x-ui.button href="{{ route('member.invoices.show', $purchase->invoice) }}">View Invoice</x-ui.button>
        @endif
    </x-ui.card>
</div>
@endsection
