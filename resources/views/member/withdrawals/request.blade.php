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
    $chargeRate = $settings->charge_type === 'percent' && $settings->charge_value !== null
        ? bcadd((string) $settings->charge_value, '0', 4)
        : '0.0000';
    $chargeType = $settings->charge_type ?: 'none';
@endphp
<div class="ds-finance">
    @if($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <div class="ds-stat-grid">
        @foreach ([
            'roi' => ['ri-line-chart-line', 'teal'],
            'working' => ['ri-briefcase-line', 'violet'],
            'activation' => ['ri-flashlight-line', 'amber'],
        ] as $wallet => [$icon, $tone])
            <x-ui.stat
                :icon="$icon"
                :tone="$tone"
                :label="$walletLabels[$wallet]"
                :value="\App\Support\MoneyDisplay::usdt($balances[$wallet]['stored'])"
                :hint="'Available '.\App\Support\MoneyDisplay::usdt($balances[$wallet]['available'])"
            />
        @endforeach
        <x-ui.stat icon="ri-bank-card-line" label="Charge" :value="$charge ?? 'Not configured'" hint="Applied when a request is stored" />
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
                    <input name="amount" value="{{ old('amount') }}" inputmode="decimal" required data-withdrawal-amount>
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
            <div class="ds-customer-grid" data-withdrawal-preview>
                <div><strong>Admin charge</strong><p data-withdrawal-charge>{{ $charge }}</p></div>
                <div><strong>Net payout</strong><p data-withdrawal-net>Enter an amount</p></div>
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
@if ($requestsEnabled)
<script>
    (function () {
        var amountInput = document.querySelector('[data-withdrawal-amount]');
        var chargeOutput = document.querySelector('[data-withdrawal-charge]');
        var netOutput = document.querySelector('[data-withdrawal-net]');
        if (!amountInput || !chargeOutput || !netOutput) return;
        var chargeType = @json($chargeType);
        var chargeValue = @json($chargeType === 'percent' ? $chargeRate : ($settings->charge_value !== null ? (string) $settings->charge_value : '0'));
            var parseDecimal = function (text) {
                var match = /^(\d+)(?:\.(\d{1,4}))?$/.exec((text || '').trim());
                if (!match) return null;
                var fraction = (match[2] || '').padEnd(4, '0');
                return BigInt(match[1]) * 10000n + BigInt(fraction);
            };
            var formatDecimal = function (micro) {
                var whole = micro / 10000n;
                var fraction = (micro % 10000n).toString().padStart(4, '0');
                return 'USDT ' + whole.toString() + '.' + fraction;
            };
            var roundQuotient = function (numerator, denominator) {
                var quotient = numerator / denominator;
                var remainder = numerator % denominator;
                return remainder * 2n >= denominator ? quotient + 1n : quotient;
        };
        var update = function () {
                var amount = parseDecimal(amountInput.value);
                if (amount === null || amount <= 0n) {
                    var initialCharge = parseDecimal(chargeValue);
                    chargeOutput.textContent = chargeType === 'percent'
                        ? chargeValue + '%'
                        : formatDecimal(initialCharge === null ? 0n : initialCharge);
                netOutput.textContent = 'Enter an amount';
                return;
            }
                var charge;
                if (chargeType === 'percent') {
                    var percent = parseDecimal(chargeValue);
                    charge = percent === null ? 0n : roundQuotient(amount * percent, 1000000n);
                } else if (chargeType === 'flat') {
                    charge = parseDecimal(chargeValue);
                    if (charge === null) charge = 0n;
                } else {
                    charge = 0n;
                }
                var net = amount - charge;
                if (net < 0n) net = 0n;
                netOutput.textContent = formatDecimal(net);
                chargeOutput.textContent = formatDecimal(charge);
        };
        amountInput.addEventListener('input', update);
        update();
    }());
</script>
@endif
@endsection
