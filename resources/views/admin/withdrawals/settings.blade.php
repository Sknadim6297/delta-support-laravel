@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Withdrawal Settings</h1>
        <p class="ds-page-copy">These values are a draft. 20.0000 USDT is only a proposed minimum. Saving this form does not open withdrawal requests, place a hold, debit a wallet, or send a payout.</p>
    </header>
    <form method="POST" action="{{ route('admin.withdrawals.settings.update') }}" class="ds-card">
        @csrf
        @method('PUT')
        <div class="ds-form ds-form-grid">
            <label class="ds-field"><span>Denomination</span><input value="USDT" readonly></label>
            <label class="ds-field"><span>Submission status</span><input value="Disabled" readonly></label>
            <label class="ds-field"><span>Minimum withdrawal (USDT)</span><input name="minimum_amount" value="{{ old('minimum_amount', $settings->minimum_amount) }}" inputmode="decimal" required></label>
            <label class="ds-field"><span>Maximum withdrawal (USDT)</span><input name="maximum_amount" value="{{ old('maximum_amount', $settings->maximum_amount) }}" inputmode="decimal" placeholder="Unset"></label>
            <label class="ds-field"><span>Charge type</span>
                <select name="charge_type">
                    <option value="" @selected(old('charge_type', $settings->charge_type) === null || old('charge_type', $settings->charge_type) === '')>Unset</option>
                    <option value="none" @selected(old('charge_type', $settings->charge_type) === 'none')>No charge</option>
                    <option value="flat" @selected(old('charge_type', $settings->charge_type) === 'flat')>Flat USDT amount</option>
                    <option value="percent" @selected(old('charge_type', $settings->charge_type) === 'percent')>Percentage</option>
                </select>
            </label>
            <label class="ds-field"><span>Charge value</span><input name="charge_value" value="{{ old('charge_value', $settings->charge_value) }}" inputmode="decimal" placeholder="Unset"></label>
            <label class="ds-field"><span>Daily limit (USDT)</span><input name="daily_limit" value="{{ old('daily_limit', $settings->daily_limit) }}" inputmode="decimal" placeholder="Unset"></label>
            <label class="ds-field"><span>Monthly limit (USDT)</span><input name="monthly_limit" value="{{ old('monthly_limit', $settings->monthly_limit) }}" inputmode="decimal" placeholder="Unset"></label>
            <fieldset class="ds-field ds-span-2">
                <legend class="mb-2 text-sm font-semibold text-navy-900">Eligible source wallets</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach (['roi' => 'ROI Wallet', 'working' => 'Working Wallet', 'activation' => 'Activation Wallet'] as $wallet => $label)
                        <label class="flex items-center gap-2 text-sm text-navy-900">
                            <input type="checkbox" name="eligible_wallets[]" value="{{ $wallet }}" class="h-4 w-4" @checked(in_array($wallet, old('eligible_wallets', $settings->eligible_wallets ?? []), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <label class="ds-field ds-span-2"><span>Supported networks</span><textarea name="networks" rows="3" maxlength="2000" placeholder="Enter one network per line. None is assumed.">{{ old('networks', implode("\n", $settings->supported_networks ?? [])) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Address validation policy</span><textarea name="address_validation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe how a public receiving address is checked.">{{ old('address_validation_policy', $settings->address_validation_policy) }}</textarea></label>
            <label class="ds-field ds-span-2"><span>Member cancellation policy</span><textarea name="member_cancellation_policy" rows="3" maxlength="5000" placeholder="Unset. Describe whether a member can cancel a pending request.">{{ old('member_cancellation_policy', $settings->member_cancellation_policy) }}</textarea></label>
            <div class="ds-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <p class="font-semibold text-navy-900">Submissions stay disabled.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($settings->enablementErrors() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
            <label class="ds-span-2 flex items-center gap-2 text-sm font-semibold text-navy-900">
                <input type="checkbox" name="submissions_enabled" value="1" class="h-4 w-4" disabled>
                Enable withdrawal submissions
            </label>
            @if($errors->any())<div class="ds-error ds-span-2">{{ $errors->first() }}</div>@endif
        </div>
        <div class="ds-actions">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white" type="submit">Save Draft</button>
        </div>
    </form>
</div>
@endsection
