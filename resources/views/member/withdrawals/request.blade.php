@extends('member.layouts.app')
@section('title', 'Withdraw USDT')
@section('content')
@php
    $walletLabels = ['roi' => 'ROI Wallet', 'working' => 'Working Wallet', 'activation' => 'Activation Wallet'];
    $charge = match ($settings->charge_type) {
        'none' => 'USDT 0.0000',
        'flat' => $settings->charge_value !== null ? \App\Support\MoneyDisplay::usdt($settings->charge_value) : null,
        'percent' => $settings->charge_value !== null ? \App\Support\MoneyDisplay::amount($settings->charge_value).'%' : null,
        default => null,
    };
@endphp
<div class="mx-auto max-w-5xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Withdraw USDT</h1>
        <p class="ds-page-copy">Balances are stored USDT amounts. This screen does not send a request, reserve funds, or calculate a payout.</p>
    </header>
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        @foreach (['roi' => $member->roi_wallet_amount, 'working' => $member->working_wallet_amount, 'activation' => $member->activation_wallet_amount] as $wallet => $balance)
            <article class="ds-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $walletLabels[$wallet] }}</p>
                <p class="mt-2 text-xl font-semibold text-navy-900">{{ \App\Support\MoneyDisplay::usdt($balance) }}</p>
            </article>
        @endforeach
    </div>
    <div class="ds-card">
        <div class="ds-form ds-form-grid">
            <label class="ds-field"><span>Source wallet</span>
                <select @disabled($wallets === [])>
                    @if ($wallets === [])
                        <option>Not configured</option>
                    @else
                        @foreach ($wallets as $wallet)
                            <option>{{ $walletLabels[$wallet] }}</option>
                        @endforeach
                    @endif
                </select>
            </label>
            <label class="ds-field"><span>Withdrawal amount (USDT)</span><input value="" placeholder="Enter an amount after submissions open" disabled></label>
            <label class="ds-field"><span>Minimum withdrawal</span><input value="{{ \App\Support\MoneyDisplay::format($settings->minimum_amount, $settings->denomination) }} proposed" readonly></label>
            <label class="ds-field"><span>Network</span>
                <select @disabled($networks === [])>
                    @if ($networks === [])
                        <option>Not configured</option>
                    @else
                        @foreach ($networks as $network)
                            <option>{{ $network }}</option>
                        @endforeach
                    @endif
                </select>
            </label>
            <label class="ds-field ds-span-2"><span>Public receiving wallet address</span><input value="" placeholder="Public address only" disabled></label>
            <label class="ds-field"><span>Withdrawal charge</span><input value="{{ $charge ?? 'Not configured' }}" readonly></label>
            <label class="ds-field"><span>Net withdrawal</span><input value="{{ $charge === null ? 'Not configured' : 'Not calculated until a request exists' }}" readonly></label>
            <div class="ds-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-navy-900">Withdrawal submissions are unavailable.</p>
                <p class="mt-1">An administrator has not approved the charge, wallet, network, and policy rules required to open requests. No withdrawal is created from this page.</p>
                @if ($settings->address_validation_policy)
                    <p class="mt-2"><span class="font-semibold text-navy-900">Address policy:</span> {{ $settings->address_validation_policy }}</p>
                @endif
                @if ($settings->member_cancellation_policy)
                    <p class="mt-2"><span class="font-semibold text-navy-900">Cancellation policy:</span> {{ $settings->member_cancellation_policy }}</p>
                @endif
            </div>
            <div class="ds-actions ds-span-2">
                <button class="cursor-not-allowed rounded-lg bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-600" type="button" disabled>Submit Withdrawal Request</button>
            </div>
        </div>
    </div>
</div>
@endsection
