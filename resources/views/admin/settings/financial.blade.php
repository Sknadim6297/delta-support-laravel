@extends('admin.layouts.app')

@section('title', 'Financial Controls')
@section('page-title', 'Financial Controls')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Settings"
        title="Financial Controls"
        lead="Each switch is enforced on the server. Crediting, withdrawals, and external payouts stay off until an administrator turns that switch on. Saving these switches does not change historical records or send a payment."
    />

    <form class="ds-settings-form" method="POST" action="{{ route('admin.settings.financial.update') }}">
        @csrf
        @method('PUT')
        <div class="ds-field-grid">
            @foreach ([
                'roi_calculation_enabled' => 'ROI Calculation Enabled',
                'roi_wallet_crediting_enabled' => 'ROI Wallet Crediting Enabled',
                'level_calculation_enabled' => 'Level Calculation Enabled',
                'level_wallet_crediting_enabled' => 'Level Wallet Crediting Enabled',
                'rewards_evaluation_enabled' => 'Rewards Evaluation Enabled',
                'withdrawal_requests_enabled' => 'Withdrawal Requests Enabled',
                'withdrawal_approval_enabled' => 'Withdrawal Approval Enabled',
                'external_payouts_enabled' => 'External Payouts Enabled',
                'daily_financial_cycle_enabled' => 'Daily Financial Cycle Enabled',
            ] as $flag => $label)
                <label>
                    <span>{{ $label }}</span>
                    <input type="checkbox" name="{{ $flag }}" value="1" @checked(old($flag, $controls->{$flag}))>
                </label>
            @endforeach
        </div>
        <div class="ds-form-actions">
            <button type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
