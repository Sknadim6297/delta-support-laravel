@extends('member.layouts.app')
@section('title', 'Withdraw USDT')
@section('page-title', 'Withdraw USDT')
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
<div>
    <p class="ds-lead">Balances are stored USDT amounts. This screen does not send a request, reserve funds, or calculate a payout.</p>
    <div>
        @foreach (['roi' => $member->roi_wallet_amount, 'working' => $member->working_wallet_amount, 'activation' => $member->activation_wallet_amount] as $wallet => $balance)
            <article>
                <p>{{ $walletLabels[$wallet] }}</p>
                <p>{{ \App\Support\MoneyDisplay::usdt($balance) }}</p>
            </article>
        @endforeach
    </div>
    <div>
        <div>
            <label><span>Source wallet</span>
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
            <label><span>Withdrawal amount (USDT)</span><input value="" placeholder="Enter an amount after submissions open" disabled></label>
            <label><span>Minimum withdrawal</span><input value="{{ \App\Support\MoneyDisplay::format($settings->minimum_amount, $settings->denomination) }} proposed" readonly></label>
            <label><span>Network</span>
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
            <label><span>Public receiving wallet address</span><input value="" placeholder="Public address only" disabled></label>
            <label><span>Withdrawal charge</span><input value="{{ $charge ?? 'Not configured' }}" readonly></label>
            <label><span>Net withdrawal</span><input value="{{ $charge === null ? 'Not configured' : 'Not calculated until a request exists' }}" readonly></label>
            <div>
                <p>Withdrawal submissions are unavailable.</p>
                <p>An administrator has not approved the charge, wallet, network, and policy rules required to open requests. No withdrawal is created from this page.</p>
                @if ($settings->address_validation_policy)
                    <p><span>Address policy:</span> {{ $settings->address_validation_policy }}</p>
                @endif
                @if ($settings->member_cancellation_policy)
                    <p><span>Cancellation policy:</span> {{ $settings->member_cancellation_policy }}</p>
                @endif
            </div>
            <div>
                <button type="button" disabled>Submit Withdrawal Request</button>
            </div>
        </div>
    </div>
</div>
@endsection
