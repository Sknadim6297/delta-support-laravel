@extends('member.layouts.app')

@section('title', 'My Packages')
@section('page-title', 'My Packages')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Packages"
        title="My Packages"
        lead="A package is active only after the payment is approved."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('member.packages.explore') }}" variant="secondary">Browse Packages</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($purchases->isEmpty())
        <x-ui.card>
            <x-ui.empty title="No packages purchased yet." message="Browse the catalog and submit a payment reference. The package stays inactive until an administrator verifies it.">
                <x-ui.button href="{{ route('member.packages.explore') }}">Browse Packages</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="ds-purchase-list ds-purchase-history">
            @foreach($purchases as $purchase)
                <article class="ds-purchase-card">
                    @if($purchase->package?->image_path)
                        <img src="{{ route('member.packages.image', $purchase->package) }}" alt="{{ $purchase->package_name }}">
                    @else
                        <div class="ds-package-media" aria-hidden="true">No image</div>
                    @endif
                    <div class="ds-purchase-body">
                        <div class="ds-purchase-heading">
                            <div>
                                <div class="ds-package-badges">
                                    <x-ui.badge :tone="$purchase->statusTone()">{{ $purchase->paymentStatusLabel() }}</x-ui.badge>
                                    <x-ui.badge :tone="$purchase->status === \App\Models\PackagePurchase::APPROVED ? 'success' : 'muted'">{{ $purchase->activationLabel() }}</x-ui.badge>
                                </div>
                                <h2>{{ $purchase->package_name }}</h2>
                            </div>
                            <p class="ds-purchase-price">{{ \App\Support\MoneyDisplay::format($purchase->price_usd, $purchase->denomination) }}</p>
                        </div>
                        <dl class="ds-purchase-meta">
                            <div>
                                <dt>Payment method</dt>
                                <dd>
                                    @if($purchase->paymentMethodLabel())
                                        {{ $purchase->paymentMethodLabel() }}
                                    @elseif(filled($purchase->currency_label) || filled($purchase->network))
                                        {{ trim(($purchase->currency_label ?: '').' '.($purchase->network ?: '')) }}
                                    @else
                                        Not recorded
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Purchase reference</dt>
                                <dd>{{ $purchase->reference }}</dd>
                            </div>
                            <div>
                                <dt>Request date</dt>
                                <dd>{{ optional($purchase->submitted_at)->timezone('Asia/Kolkata')->format('d M Y H:i') ?: 'Not recorded' }}</dd>
                            </div>
                            <div>
                                <dt>Approval date</dt>
                                <dd>{{ $purchase->status === \App\Models\PackagePurchase::APPROVED && $purchase->reviewed_at ? $purchase->reviewed_at->timezone('Asia/Kolkata')->format('d M Y H:i') : 'Not recorded' }}</dd>
                            </div>
                            @if($purchase->expires_at)
                                <div>
                                    <dt>Expiry date</dt>
                                    <dd>{{ $purchase->expires_at->timezone('Asia/Kolkata')->format('d M Y H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if($purchase->rejection_reason)
                            <p class="ds-purchase-reason">{{ $purchase->rejection_reason }}</p>
                        @endif
                        <div class="ds-package-actions">
                            <x-ui.button href="{{ route('member.payments.show', $purchase) }}" variant="secondary" size="sm">View Details</x-ui.button>
                            @if($purchase->invoice)
                                <x-ui.button href="{{ route('member.invoices.show', $purchase->invoice) }}" size="sm">View Invoice</x-ui.button>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $purchases->links('pagination::bootstrap-5') }}
    @endif
</div>
@endsection
