@extends('member.layouts.app')
@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-2 text-2xl font-bold text-navy-900">My Tour Benefits</h1>
    <p class="mb-4 text-sm text-slate-600">Booking requests are not open yet. Approved package purchases show the tour benefit that was saved with the purchase.</p>
    @forelse($purchases as $purchase)
        <article class="mb-3 rounded-xl border bg-white p-5">
            <h2 class="font-bold">{{ $purchase->tour_title ?: $purchase->package_name }}</h2>
            <p class="mt-2 text-sm">{{ $purchase->tour_details }}</p>
            <p class="mt-2 text-sm">Duration: {{ $purchase->duration ?: 'Not set' }}</p>
            <p class="mt-2 whitespace-pre-line text-sm">{{ $purchase->benefits }}</p>
        </article>
    @empty
        <p class="rounded-xl border bg-white p-6 text-sm text-slate-500">Tour benefits appear after a package payment is approved.</p>
    @endforelse
</div>
@endsection
