@extends('member.layouts.app')
@section('title', $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-4">
        <h1 class="ds-page-title">{{ $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet' }}</h1>
        <p class="ds-page-copy">Your stored USDT balance. This page cannot credit or withdraw funds.</p>
    </header>
    <article class="ds-card">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Available balance</p>
        <p class="mt-2 text-2xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($balance) }}</p>
        <p class="mt-3 text-sm text-ink-500">{{ $count }} stored {{ $wallet === 'roi' ? 'ROI' : 'level income' }} {{ $count === 1 ? 'record' : 'records' }}.</p>
        <a class="mt-4 inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" href="{{ $wallet === 'roi' ? route('member.income.roi') : route('member.income.level') }}">View history</a>
    </article>
</div>
@endsection
