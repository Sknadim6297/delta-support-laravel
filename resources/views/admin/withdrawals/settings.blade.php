@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Withdrawal Settings</h1>
        <p>These values are a draft. 20.0000 USDT is only a proposed minimum. Saving this form does not open withdrawal requests, place a hold, debit a wallet, or send a payout.</p>
    </header>
    <form method="POST" action="{{ route('admin.withdrawals.settings.update') }}">
        @csrf
        @method('PUT')
        <div>
            <label><span>Denomination</span><input value="USDT" readonly></label>
            <label><span>Submission status</span><input value="Disabled" readonly></label>
            <label><span>Minimum withdrawal (USDT)</span><input name="minimum_amount" value="{{ old('minimum_amount', $settings->minimum_amount) }}" inputmode="decimal" required></label>
            <label><span>Maximum withdrawal (USDT)</span><input name="maximum_amount" value="{{ old('maximum_amount', $settings->maximum_amount) }}" inputmode="decimal" placeholder="Unset"></label>
            <label><span>Charge type</span>
                <select name="charge_type">
                    <option value="" @selected(old('charge_type', $settings->charge_type) === null || old('charge_type', $settings->charge_type) === '')>Unset</option>
                    <option value="none" @selected(old('charge_type', $settings->charge_type) === 'none')>No charge</option>
                    <option value="flat" @selected(old('charge_type', $settings->charge_type) === 'flat')>Flat USDT amount</option>
                    <option value="percent" @selected(old('charge_type', $settings->charge_type) === 'percent')>Percentage</option>
                </select>
            </label>
            <label><span>Charge value</span><input name="charge_value" value="{{ old('charge_value', $settings->charge_value) }}" inputmode="decimal" placeholder="Unset"></label>
            <label><span>Daily limit (USDT)</span><input name="daily_limit" value="{{ old('daily_limit', $settings->daily_limit) }}" inputmode="decimal" placeholder="Unset"></label>
            <label><span>Monthly limit (USDT)</span><input name="monthly_limit" value="{{ old('monthly_limit', $settings->monthly_limit) }}" inputmode="decimal" placeholder="Unset"></label>
            <fieldset>
                <legend>Eligible source wallets</legend>
                <div>
                    @foreach (['roi' => 'ROI Wallet', 'working' => 'Working Wallet', 'activation' => 'Activation Wallet'] as $wallet => $label)
                        <label>
                            <input type="checkbox" name="eligible_wallets[]" value="{{ $wallet }}" @checked(in_array($wallet, old('eligible_wallets', $settings->eligible_wallets ?? []), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <label><span>Supported networks</span><textarea name="networks" rows="3" maxlength="2000" placeholder="Enter one network per line. None is assumed.">{{ old('networks', implode("\n", $settings->supported_networks ?? [])) }}</textarea></label>
            <label><span>Address validation policy</span><textarea name="address_validation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe how a public receiving address is checked.">{{ old('address_validation_policy', $settings->address_validation_policy) }}</textarea></label>
            <label><span>Member cancellation policy</span><textarea name="member_cancellation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe whether a member can cancel a pending request.">{{ old('member_cancellation_policy', $settings->member_cancellation_policy) }}</textarea></label>
            <div>
                <p>Submissions stay disabled.</p>
                <ul>
                    @foreach ($settings->enablementErrors() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
            <label>
                <input type="checkbox" name="submissions_enabled" value="1" disabled>
                Enable withdrawal submissions
            </label>
            @if($errors->any())<div>{{ $errors->first() }}</div>@endif
        </div>
        <div>
            <button type="submit">Save Draft</button>
        </div>
    </form>
</div>
@endsection
