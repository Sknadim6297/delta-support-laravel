@extends('member.layouts.app')

@section('title', 'Explore Packages')
@section('page-title', 'Explore Packages')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Packages"
        title="Explore Packages"
        lead="Each price uses the denomination saved on that package. A purchase stays pending until payment is verified."
    />

    @if(!$settings->isReady())
        <x-ui.alert type="warning">Payment Currently Unavailable. You can still review every active package. Purchase stays closed until an administrator enables payment.</x-ui.alert>
    @endif

    @if($packages->isEmpty())
        <x-ui.card>
            <x-ui.empty title="No active packages are available." message="Packages appear here after an administrator publishes them." />
        </x-ui.card>
    @else
        <div class="ds-package-grid">
            @foreach($packages as $package)
                <article class="ds-package-card">
                    @if($package->image_path)
                        <img src="{{ route('member.packages.image', $package) }}" alt="{{ $package->name }}">
                    @else
                        <div class="ds-package-media" aria-hidden="true">{{ $package->typeLabel() }}</div>
                    @endif
                    <div class="ds-package-body">
                        <x-ui.badge>{{ $package->typeLabel() }}</x-ui.badge>
                        <h2>{{ $package->name }}</h2>
                        <p class="ds-package-code">{{ $package->code }}</p>
                        <p class="ds-package-price">{{ \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination) }}</p>
                        <dl class="ds-package-meta">
                            <div>
                                <dt>Destinations</dt>
                                <dd>{{ $package->destinations ?: 'Not set' }}</dd>
                            </div>
                            <div>
                                <dt>Duration</dt>
                                <dd>{{ $package->duration ?: 'Not set' }}</dd>
                            </div>
                        </dl>
                        @if($package->benefits)
                            <p class="ds-package-benefits">{{ $package->benefits }}</p>
                        @endif
                        <x-ui.button href="{{ route('member.packages.show', $package) }}">View package</x-ui.button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
