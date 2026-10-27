@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="mb-2 text-2xl font-bold text-navy-900">Explore Packages</h1>
    <p class="mb-4 text-sm text-slate-600">Prices are shown in USD exactly as saved by the administrator.</p>
    @if(!$settings->isReady())
        <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Payment instructions are not available yet. You can review packages, and purchase opens after payment settings are enabled.</p>
    @endif
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($packages as $package)
            <article class="rounded-xl border bg-white p-5">
                <h2 class="text-lg font-bold text-navy-900">{{ $package->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $package->code }}</p>
                <p class="mt-3 text-xl font-bold text-brand-700">USD {{ $package->price_usd }}</p>
                <p class="mt-2 text-sm">{{ $package->tour_title }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ $package->description }}</p>
                <a href="{{ route('member.packages.show', $package) }}" class="mt-4 inline-block rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">View package</a>
            </article>
        @empty
            <p class="text-sm text-slate-500">No active packages are available.</p>
        @endforelse
    </div>
</div>
@endsection
