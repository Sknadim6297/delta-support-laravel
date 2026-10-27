@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Withdrawal Overview</h1>
        <p class="ds-page-copy">Counts come from stored withdrawal requests. Review, holds, and payouts are not available.</p>
    </header>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total requests</p><p class="mt-2 text-2xl font-semibold text-navy-900">{{ $total }}</p></article>
        @foreach ($counts as $status => $count)
            <article class="ds-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ \App\Support\WithdrawalStatus::label($status) }}</p>
                <p class="mt-2 text-2xl font-semibold text-navy-900">{{ $count }}</p>
            </article>
        @endforeach
    </div>
</div>
@endsection
