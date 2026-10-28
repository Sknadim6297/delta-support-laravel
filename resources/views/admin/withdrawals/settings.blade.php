@extends('admin.layouts.app')
@section('title', 'Withdrawal Settings')
@section('page-title', 'Withdrawal Settings')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        title="Withdrawal Settings"
        lead="These values are a draft. 20.0000 USDT is only a proposed minimum. Saving this form does not open withdrawal requests, place a hold, debit a wallet, or send a payout."
    />
    @if($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <form class="ds-settings-form" method="POST" action="{{ route('admin.withdrawals.settings.update') }}" onsubmit="return confirm('Save this withdrawal settings draft? Submissions, holds, and payouts stay disabled.');">
        @csrf
        @method('PUT')
        <div class="ds-field-grid">
            <label><span>Denomination</span><input value="USDT" readonly></label>
            <label><span>Submission status</span><input value="Disabled" readonly></label>
            <label>
                <span>Minimum withdrawal (USDT)</span>
                <input name="minimum_amount" value="{{ old('minimum_amount', $settings->minimum_amount) }}" inputmode="decimal" required>
                @error('minimum_amount')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Maximum withdrawal (USDT)</span>
                <input name="maximum_amount" value="{{ old('maximum_amount', $settings->maximum_amount) }}" inputmode="decimal" placeholder="Unset">
                @error('maximum_amount')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Charge type</span>
                <select name="charge_type">
                    <option value="" @selected(old('charge_type', $settings->charge_type) === null || old('charge_type', $settings->charge_type) === '')>Unset</option>
                    <option value="none" @selected(old('charge_type', $settings->charge_type) === 'none')>No charge</option>
                    <option value="flat" @selected(old('charge_type', $settings->charge_type) === 'flat')>Flat USDT amount</option>
                    <option value="percent" @selected(old('charge_type', $settings->charge_type) === 'percent')>Percentage</option>
                </select>
                @error('charge_type')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Charge value</span>
                <input name="charge_value" value="{{ old('charge_value', $settings->charge_value) }}" inputmode="decimal" placeholder="Unset">
                @error('charge_value')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Daily limit (USDT)</span>
                <input name="daily_limit" value="{{ old('daily_limit', $settings->daily_limit) }}" inputmode="decimal" placeholder="Unset">
                @error('daily_limit')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label>
                <span>Monthly limit (USDT)</span>
                <input name="monthly_limit" value="{{ old('monthly_limit', $settings->monthly_limit) }}" inputmode="decimal" placeholder="Unset">
                @error('monthly_limit')<span class="validation-message">{{ $message }}</span>@enderror
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
                <span>Supported networks</span>
                <textarea name="networks" rows="3" maxlength="2000" placeholder="Enter one network per line. None is assumed.">{{ old('networks', implode("\n", $settings->supported_networks ?? [])) }}</textarea>
                @error('networks')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label class="ds-span-all">
                <span>Address validation policy</span>
                <textarea name="address_validation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe how a public receiving address is checked.">{{ old('address_validation_policy', $settings->address_validation_policy) }}</textarea>
                @error('address_validation_policy')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <label class="ds-span-all">
                <span>Member cancellation policy</span>
                <textarea name="member_cancellation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe whether a member can cancel a pending request.">{{ old('member_cancellation_policy', $settings->member_cancellation_policy) }}</textarea>
                @error('member_cancellation_policy')<span class="validation-message">{{ $message }}</span>@enderror
            </label>
            <div class="ds-form-note ds-span-all">
                <p>Submissions stay disabled.</p>
                <ul>
                    @foreach ($settings->enablementErrors() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
            <label class="ds-check ds-span-all">
                <input type="checkbox" name="submissions_enabled" value="1" disabled>
                <span>Enable withdrawal submissions</span>
            </label>
        </div>
        <div class="ds-actions">
            <button type="submit">Save Draft</button>
        </div>
    </form>
</div>
@endsection
