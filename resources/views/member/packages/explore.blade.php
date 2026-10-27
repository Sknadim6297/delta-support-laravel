@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Explore Packages</h1>
        <p class="ds-page-copy">Prices are the USD amounts saved by the administrator. A purchase stays pending until payment is verified.</p>
    </header>
    @if(!$settings->isReady())
        <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Payment Currently Unavailable. You can still review every active package. Purchase stays closed until an administrator enables payment.</p>
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        @forelse($packages as $package)
            <article class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
                @if($package->image_path)
                    <img src="{{ route('member.packages.image', $package) }}" alt="{{ $package->name }}" class="h-40 w-full object-cover">
                @else
                    <div class="flex h-40 items-end bg-gradient-to-br from-navy-900 to-brand-500 p-4 text-sm font-semibold text-white">{{ $package->typeLabel() }}</div>
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $package->typeLabel() }}</p>
                    <h2 class="mt-1 text-lg font-bold text-navy-900">{{ $package->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $package->code }}</p>
                    <p class="mt-3 text-xl font-bold text-brand-700">USD {{ $package->price_usd }}</p>
                    <p class="mt-3 text-sm text-slate-700"><span class="font-semibold text-navy-900">Destinations:</span> {{ $package->destinations ?: 'Not set' }}</p>
                    <p class="mt-1 text-sm text-slate-700"><span class="font-semibold text-navy-900">Duration:</span> {{ $package->duration ?: 'Not set' }}</p>
                    @if($package->benefits)
                        <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm text-slate-600">{{ $package->benefits }}</p>
                    @endif
                    <a href="{{ route('member.packages.show', $package) }}" class="mt-4 inline-flex w-fit rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">View package</a>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">No active packages are available.</p>
        @endforelse
    </div>
</div>
@endsection
