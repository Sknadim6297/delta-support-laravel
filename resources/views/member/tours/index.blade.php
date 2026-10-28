@extends('member.layouts.app')
@section('title', 'My Tour Benefits')
@section('page-title', 'My Tour Benefits')
@section('content')
<div>
    <p class="ds-lead">Booking requests are not open yet. Approved package purchases show the tour benefit that was saved with the purchase.</p>
    @forelse($purchases as $purchase)
        <article>
            <h2>{{ $purchase->tour_title ?: $purchase->package_name }}</h2>
            <p>{{ $purchase->tour_details }}</p>
            <p>Duration: {{ $purchase->duration ?: 'Not set' }}</p>
            <p>{{ $purchase->benefits }}</p>
        </article>
    @empty
        <p>Tour benefits appear after a package payment is approved.</p>
    @endforelse
</div>
@endsection
