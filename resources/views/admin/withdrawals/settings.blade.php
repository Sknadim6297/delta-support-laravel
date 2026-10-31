@extends('admin.layouts.app')
@section('title', 'Withdrawal Settings')
@section('page-title', 'Withdrawal Settings')
@section('content')
@php
    $chargeType = old('charge_type', $settings->charge_type);
    $showCharge = in_array($chargeType, ['flat', 'percent'], true);
    $networkValue = old('network', $settings->configuredNetworks()[0] ?? '');
    $enabled = (string) old('submissions_enabled', $settings->submissions_enabled ? '1' : '0') === '1';
@endphp
<div class="ds-finance">
    @if($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <form class="ds-settings-form" method="POST" action="{{ route('admin.withdrawals.settings.update') }}">
        @csrf
        @method('PUT')
        <div class="ds-field-grid">
            <div class="ds-span-all">
                <input type="hidden" name="submissions_enabled" value="0">
                <label class="ds-switch">
                    <input type="checkbox" name="submissions_enabled" value="1" @checked($enabled)>
                    <span class="ds-switch-ui" aria-hidden="true"></span>
                    <span>Enable withdrawals</span>
                </label>
                <p class="ds-switch-note">Members can submit a USDT request only while this is on. The amount stays reserved until you approve or reject it. No blockchain payout is sent automatically.</p>
            </div>
            <label>
                <span>Minimum withdrawal (USDT)</span>
                <input name="minimum_amount" value="{{ old('minimum_amount', $settings->minimum_amount) }}" inputmode="decimal" required>
                @error('minimum_amount')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Maximum withdrawal (USDT)</span>
                <input name="maximum_amount" value="{{ old('maximum_amount', $settings->maximum_amount) }}" inputmode="decimal">
                @error('maximum_amount')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Withdrawal charge type</span>
                <select name="charge_type" data-charge-type>
                    <option value="none" @selected($chargeType === 'none' || $chargeType === null || $chargeType === '')>None</option>
                    <option value="flat" @selected($chargeType === 'flat')>Flat</option>
                    <option value="percent" @selected($chargeType === 'percent')>Percentage</option>
                </select>
                @error('charge_type')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label data-charge-value @if(! $showCharge) hidden @endif>
                <span>Charge value</span>
                <input name="charge_value" value="{{ old('charge_value', $settings->charge_value) }}" inputmode="decimal" placeholder="0.0000">
                @error('charge_value')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <fieldset class="ds-span-all">
                <legend>Eligible source wallets</legend>
                <div class="ds-check-row">
                    @foreach (['roi' => 'ROI Wallet', 'working' => 'Working Wallet', 'activation' => 'Activation Wallet'] as $wallet => $label)
                        <label class="ds-check">
                            <input type="checkbox" name="eligible_wallets[]" value="{{ $wallet }}" @checked(in_array($wallet, old('eligible_wallets', $settings->eligible_wallets ?? []), true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('eligible_wallets')<span class="validation-message">{{ $message }}</span>@enderror
            </fieldset>
            <label class="ds-span-all">
                <span>Supported blockchain network</span>
                <input name="network" value="{{ $networkValue }}" maxlength="80" placeholder="TRC20" autocomplete="off">
                @error('network')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="ds-actions">
            <button type="submit">Save Changes</button>
        </div>
    </form>
</div>
<script>
    document.querySelector('[data-charge-type]')?.addEventListener('change', function (event) {
        const field = document.querySelector('[data-charge-value]');
        if (!field) {
            return;
        }
        const show = event.target.value === 'flat' || event.target.value === 'percent';
        field.hidden = !show;
    });
</script>
@endsection
