@extends('member.layouts.app')
@section('title', 'My Wallets')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">My Wallets</h1>
        <p class="ds-page-copy">{{ $member->member_id }} · Wallet balances are USDT. ROI and level income are not being credited.</p>
    </header>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <article class="ds-card">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">ROI Wallet</p>
            <p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($member->roi_wallet_amount) }}</p>
            <p class="mt-2 text-sm text-ink-500">{{ $roiCount }} stored ROI {{ $roiCount === 1 ? 'record' : 'records' }}</p>
            <a class="mt-3 inline-block text-sm font-semibold text-brand-700" href="{{ route('member.income.roi') }}">My ROI History</a>
        </article>
        <article class="ds-card">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Working Wallet</p>
            <p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($member->working_wallet_amount) }}</p>
            <p class="mt-2 text-sm text-ink-500">{{ $levelCount }} stored level income {{ $levelCount === 1 ? 'record' : 'records' }}</p>
            <a class="mt-3 inline-block text-sm font-semibold text-brand-700" href="{{ route('member.income.level') }}">My Level Income History</a>
        </article>
        <article class="ds-card">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Activation Wallet</p>
            <p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</p>
            <p class="mt-2 text-sm text-ink-500">Shown for reference. Transfers are not available here.</p>
        </article>
    </div>
</div>
@endsection
