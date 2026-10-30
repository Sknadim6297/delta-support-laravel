@extends('admin.layouts.app')

@section('title', 'Financial Automation')
@section('page-title', 'Financial Automation')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="Financial Automation"
        lead="ROI and 20-level commission post on the Asia/Kolkata schedule after automation is armed. This page cannot credit a wallet or send a payout."
    />

    @if (session('status'))
        <x-ui.alert type="warning">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="ds-stat-grid">
        <x-ui.stat label="Automation" :value="$armed ? 'Armed' : 'Not armed'" hint="Arming is a deployment command, not a daily switch" />
        <x-ui.stat label="Emergency stop" :value="$stopped ? 'On' : 'Off'" hint="Stops ROI, commission, and reward evaluation" />
        <x-ui.stat label="Posting" :value="$posting ? 'Will post' : 'Will not post'" hint="{{ $timezone }}" />
        <x-ui.stat label="Next daily run" value="00:10" hint="ROI and commission. Ranks at 00:20" />
        <x-ui.stat label="ROI today" :value="$roiToday" hint="{{ $today }}" />
        <x-ui.stat label="Commission today" :value="$commissionToday" hint="20-level plan. ST666666 is never paid" />
        <x-ui.stat label="Rewards" :value="$rewardRun?->status ?? 'No run'" hint="Evaluation only. No cash payout" />
        <x-ui.stat label="Failed jobs" :value="$failedJobs" hint="Queue failures. Income is not queued" />
        <x-ui.stat label="Negative wallets" :value="$negative" hint="Reconciliation. This page does not change a balance" />
    </div>

    <x-ui.card title="Last income run" subtitle="Stored package income runs. A refused run posted nothing.">
        @if ($last)
            <p>{{ $last->business_date?->toDateString() }} · {{ $last->status }} · ROI {{ $last->credited_roi }} · Commission {{ $last->credited_commissions }}</p>
            <p>{{ $last->message }}</p>
        @else
            <p>No income run has been recorded.</p>
        @endif
        <p>Income eligibility stays on its two-minute preview. Rewards evaluation stays on its two-minute schedule and does not credit a wallet. Withdrawals are {{ $withdrawals ? 'on' : 'off' }}. External payouts are {{ $payouts ? 'on' : 'off' }}.</p>
        <p><a href="{{ route('admin.member-transfers.index') }}">Member transfers</a> still require the member to confirm, a security PIN, and the transfer limits.</p>
    </x-ui.card>

    <form class="ds-settings-form" method="POST" action="{{ route('admin.settings.financial.update') }}">
        @csrf
        @method('PUT')
        <label>
            <span>Emergency stop</span>
            <input type="checkbox" name="financial_emergency_stop" value="1" @checked(old('financial_emergency_stop', $stopped))>
        </label>
        <div class="ds-form-actions">
            <button type="submit">Save emergency stop</button>
        </div>
    </form>
</div>
@endsection
