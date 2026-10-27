@extends('member.layouts.app')

@section('title', $package->name)
@section('page-title', $package->name)

@section('content')
<div class="ds-catalog">
    <x-ui.page-header kicker="{{ $package->typeLabel() }}" :title="$package->name" :lead="$package->code">
        <x-slot:actions>
            <x-ui.button href="{{ route('member.packages.explore') }}" variant="secondary">All packages</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-package-layout">
        <div class="ds-package-main">
            <article class="ds-package-card ds-package-hero">
                @if($package->image_path)
                    <img src="{{ route('member.packages.image', $package) }}" alt="{{ $package->name }}">
                @endif
                <div class="ds-package-body">
                    <p class="ds-package-price">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</p>
                    @if($package->description)<p>{{ $package->description }}</p>@endif
                    @if($package->full_description)<p>{{ $package->full_description }}</p>@endif
                </div>
            </article>

            <x-ui.card title="Tour">
                <dl class="ds-package-meta">
                    <div>
                        <dt>Tour</dt>
                        <dd>{{ $package->tour_title ?: 'Tour title not set' }}</dd>
                    </div>
                    <div>
                        <dt>Destinations</dt>
                        <dd>{{ $package->destinations ?: 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ $package->duration ?: 'Not set' }}</dd>
                    </div>
                </dl>
                @if($package->tour_details)
                    <p>{{ $package->tour_details }}</p>
                @endif
            </x-ui.card>

            @if($package->benefits || $package->inclusions || $package->exclusions)
                <x-ui.card title="Included">
                    @if($package->benefits)
                        <h3>Benefits</h3>
                        <p>{{ $package->benefits }}</p>
                    @endif
                    @if($package->inclusions)
                        <h3>Inclusions</h3>
                        <p>{{ $package->inclusions }}</p>
                    @endif
                    @if($package->exclusions)
                        <h3>Exclusions</h3>
                        <p>{{ $package->exclusions }}</p>
                    @endif
                </x-ui.card>
            @endif

            @if($package->reservesFutureIncome())
                <x-ui.alert type="warning">ROI and level commission are reserved for a later review. This package does not credit income.</x-ui.alert>
            @endif
        </div>

        <x-ui.card class="ds-pay-card" title="Payment" subtitle="Send the saved package amount, then submit the transaction ID.">
            @if($settings->isReady())
                <dl class="ds-package-meta">
                    <div>
                        <dt>Accepted token</dt>
                        <dd>{{ $settings->currency_label }}</dd>
                    </div>
                    <div>
                        <dt>Network</dt>
                        <dd>{{ $settings->network }}</dd>
                    </div>
                </dl>
                <p>Send {{ $package->denomination === 'USDT' ? 'USDT' : ($settings->currency_label ?: 'the accepted token') }} only on the exact network shown here. This application does not verify the blockchain.</p>
                <p>{{ $settings->instructions }}</p>
                @if($settings->receiving_address)
                    <p class="ds-pay-address">{{ $settings->receiving_address }}</p>
                @endif
                @if($settings->qr_image_path)
                    <img class="ds-pay-qr" src="{{ route('member.payments.qr') }}" alt="Payment QR">
                @endif
                <form class="ds-pay-form" method="POST" action="{{ route('member.packages.purchase', $package) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                    <label>Transaction ID
                        <input name="payment_reference" value="{{ old('payment_reference') }}" required maxlength="128" autocomplete="off" placeholder="Enter the transaction ID">
                    </label>
                    @if($errors->any())
                        <div class="validation-message">{{ $errors->first() }}</div>
                    @endif
                    <button type="submit">Submit for verification</button>
                </form>
                <p class="ds-package-note">Submitting a reference does not confirm payment or activate the package. Status stays pending until an administrator verifies it. The same package can be requested again with a different reference.</p>
            @else
                <p>Payment Currently Unavailable. This package stays visible, and no payment request is created until payment is enabled.</p>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection
