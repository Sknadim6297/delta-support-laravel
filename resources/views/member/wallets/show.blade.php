@extends('member.layouts.app')
@section('title', $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet')
@section('page-title', $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet')
@section('content')
@php
    $walletLabel = $wallet === 'roi' ? 'ROI Wallet' : 'Working Wallet';
    $historyRoute = $wallet === 'roi' ? route('member.income.roi') : route('member.income.level');
    $historyLabel = $wallet === 'roi' ? 'ROI' : 'level income';
@endphp
<div class="ds-finance">
    <x-ui.page-header
        kicker="Earnings"
        :title="$walletLabel"
        lead="Your stored USDT balance. This page cannot credit or withdraw funds."
        :heading="true"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('member.wallets.overview') }}" variant="secondary">All wallets</x-ui.button>
            <x-ui.button :href="$historyRoute" variant="secondary">View history</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-wallet-3-line" label="Total balance" :value="\App\Support\MoneyDisplay::usdt($figures['stored'])" hint="Stored wallet column" />
        <x-ui.stat icon="ri-lock-line" tone="amber" label="Reserved balance" :value="\App\Support\MoneyDisplay::usdt($figures['reserved'])" hint="Open withdrawal requests" />
        <x-ui.stat icon="ri-checkbox-circle-line" tone="green" label="Available balance" :value="\App\Support\MoneyDisplay::usdt($figures['available'])" hint="Stored minus reserved" />
        <x-ui.stat icon="ri-money-dollar-circle-line" tone="teal" label="Paid withdrawals" :value="\App\Support\MoneyDisplay::usdt($figures['paid'])" hint="Gross USDT already settled" />
    </div>

    <x-ui.card title="Stored activity" :lead="$count.' stored '.$historyLabel.' '.($count === 1 ? 'record' : 'records').'.'">
        <div class="ds-actions">
            <x-ui.button :href="$historyRoute">View history</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
