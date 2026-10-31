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
<div class="ds-finance">
    @if($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <div class="ds-stat-grid">
        @foreach (['roi', 'working', 'activation'] as $wallet)
            <article class="ds-stat">
                <p class="ds-stat-label">{{ $walletLabels[$wallet] }}</p>
                <p class="ds-stat-value">{{ \App\Support\MoneyDisplay::usdt($balances[$wallet]['stored']) }}</p>
                <p class="ds-stat-hint">Available {{ \App\Support\MoneyDisplay::usdt($balances[$wallet]['available']) }}</p>
            </article>
        @endforeach
    </div>
    @if ($requestsEnabled)
        <form class="ds-settings-form" method="POST" action="{{ route('member.withdrawals.store') }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="ds-field-grid">
                <label>
                    <span>Source wallet</span>
                    <select name="wallet" required>
                        @foreach ($wallets as $wallet)
                            <option value="{{ $wallet }}" @selected(old('wallet') === $wallet)>{{ $walletLabels[$wallet] }}</option>
                        @endforeach
                    </select>
                    @error('wallet')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span>Withdrawal amount (USDT)</span>
                    <input name="amount" value="{{ old('amount') }}" inputmode="decimal" required>
                    @error('amount')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span>Network</span>
                    <select name="network" required>
                        @foreach ($networks as $network)
                            <option value="{{ $network }}" @selected(old('network') === $network)>{{ $network }}</option>
                        @endforeach
                    </select>
                    @error('network')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label class="ds-span-all">
                    <span>Public receiving wallet address</span>
                    <input name="address" value="{{ old('address') }}" maxlength="128" autocomplete="off" required>
                    @error('address')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
            </div>
            @if ($member->hasSecurityPin())
                <x-security.pin-input name="security_pin" label="Security PIN" />
                @error('security_pin')<span class="validation-message">{{ $message }}</span>@enderror
                @error('idempotency_key')<span class="validation-message">{{ $message }}</span>@enderror
                <div class="ds-actions">
                    <button type="submit">Submit Withdrawal Request</button>
                </div>
            @else
                <p>Set a security PIN before a withdrawal can be reserved.</p>
                <a href="{{ route('member.security-pin') }}">Set Security PIN</a>
            @endif
        </form>
    @else
        <form class="ds-settings-form">
            <div class="ds-field-grid">
                <label>
                    <span>Source wallet</span>
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
                <label>
                    <span>Withdrawal amount (USDT)</span>
                    <input value="" placeholder="Enter an amount after submissions open" disabled>
                </label>
                <label>
                    <span>Minimum withdrawal</span>
                    <input value="{{ \App\Support\MoneyDisplay::format($settings->minimum_amount, $settings->denomination) }} proposed" readonly>
                </label>
                <label>
                    <span>Network</span>
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
                <label>
                    <span>Public receiving wallet address</span>
                    <input value="" placeholder="Public address only" disabled>
                </label>
                <label>
                    <span>Withdrawal charge</span>
                    <input value="{{ $charge ?? 'Not configured' }}" readonly>
                </label>
            </div>
            <p>Withdrawal submissions are unavailable.</p>
            <div class="ds-actions">
                <button type="button" disabled>Submit Withdrawal Request</button>
            </div>
        </form>
    @endif
</div>
@endsection
