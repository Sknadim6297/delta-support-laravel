@extends('member.layouts.app')
@section('title', 'My Wallets')
@section('page-title', 'My Wallets')
@section('content')
@php
    $book = [
        'stored' => bcadd(bcadd($wallets['roi']['stored'], $wallets['working']['stored'], 4), $wallets['activation']['stored'], 4),
        'reserved' => bcadd(bcadd($wallets['roi']['reserved'], $wallets['working']['reserved'], 4), $wallets['activation']['reserved'], 4),
        'available' => bcadd(bcadd($wallets['roi']['available'], $wallets['working']['available'], 4), $wallets['activation']['available'], 4),
        'paid' => bcadd(bcadd($wallets['roi']['paid'], $wallets['working']['paid'], 4), $wallets['activation']['paid'], 4),
    ];
@endphp
<div class="ds-finance">
    <x-ui.page-header
        kicker="Earnings"
        title="My Wallets"
        lead="{{ $member->member_id }} · {{ $incomeStatus }}"
        :heading="true"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('member.income.roi') }}" variant="secondary">ROI History</x-ui.button>
            <x-ui.button href="{{ route('member.income.level') }}" variant="secondary">Level Income</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <a class="ds-stat-link" href="{{ route('member.wallets.show', ['wallet' => 'roi']) }}">
            <x-ui.stat
                icon="ri-line-chart-line"
                tone="teal"
                label="ROI Wallet"
                :value="\App\Support\MoneyDisplay::usdt($member->roi_wallet_amount)"
                :hint="'Available '.\App\Support\MoneyDisplay::usdt($wallets['roi']['available'])"
            />
        </a>
        <a class="ds-stat-link" href="{{ route('member.wallets.show', ['wallet' => 'working']) }}">
            <x-ui.stat
                icon="ri-briefcase-line"
                tone="violet"
                label="Working Wallet"
                :value="\App\Support\MoneyDisplay::usdt($member->working_wallet_amount)"
                :hint="'Available '.\App\Support\MoneyDisplay::usdt($wallets['working']['available'])"
            />
        </a>
        <x-ui.stat
            icon="ri-flashlight-line"
            tone="amber"
            label="Activation Wallet"
            :value="\App\Support\MoneyDisplay::usdt($member->activation_wallet_amount)"
            :hint="'Available '.\App\Support\MoneyDisplay::usdt($wallets['activation']['available'])"
        />
        <x-ui.stat
            icon="ri-database-2-line"
            label="Stored records"
            :value="$roiCount.' ROI'"
            :hint="$levelCount.' level income'"
        />
    </div>

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-wallet-3-line" label="Total balance" :value="\App\Support\MoneyDisplay::usdt($book['stored'])" hint="All wallet columns" />
        <x-ui.stat icon="ri-lock-line" tone="amber" label="Reserved balance" :value="\App\Support\MoneyDisplay::usdt($book['reserved'])" hint="Open withdrawal requests" />
        <x-ui.stat icon="ri-checkbox-circle-line" tone="green" label="Available balance" :value="\App\Support\MoneyDisplay::usdt($book['available'])" hint="Stored minus reserved" />
        <x-ui.stat icon="ri-money-dollar-circle-line" tone="teal" label="Paid withdrawals" :value="\App\Support\MoneyDisplay::usdt($book['paid'])" hint="Gross USDT already settled" />
    </div>

    <x-ui.card title="Wallet notes" lead="Balances are USDT. This page cannot credit or withdraw funds.">
        <div class="pv-grid-3">
            <div>
                <h3 class="pv-card-title">ROI Wallet</h3>
                <p class="ds-lead">Paid withdrawals {{ \App\Support\MoneyDisplay::usdt($wallets['roi']['paid']) }}. {{ $roiCount }} stored ROI {{ $roiCount === 1 ? 'record' : 'records' }}.</p>
                <p><a href="{{ route('member.income.roi') }}">My ROI History</a></p>
            </div>
            <div>
                <h3 class="pv-card-title">Working Wallet</h3>
                <p class="ds-lead">Paid withdrawals {{ \App\Support\MoneyDisplay::usdt($wallets['working']['paid']) }}. {{ $levelCount }} stored level income {{ $levelCount === 1 ? 'record' : 'records' }}.</p>
                <p><a href="{{ route('member.income.level') }}">My Level Income History</a></p>
            </div>
            <div>
                <h3 class="pv-card-title">Activation Wallet</h3>
                <p class="ds-lead">Paid withdrawals {{ \App\Support\MoneyDisplay::usdt($wallets['activation']['paid']) }}. Shown for reference. Transfers are not available here.</p>
            </div>
        </div>
    </x-ui.card>
</div>
@endsection
