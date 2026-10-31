@extends('admin.layouts.app')
@section('title', $withdrawal->reference)
@section('page-title', 'Withdrawal Request')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        :title="$withdrawal->reference"
        :lead="$canSettle ? 'Record the USDT payout you already sent. This deducts the reserved amount and does not broadcast a blockchain transfer.' : ($canApprove ? 'Approval records the decision and keeps the reservation. It does not send cryptocurrency or a bank payment.' : ($canReject ? 'Rejecting this request releases the reservation. Approval is closed while submissions are disabled. No payout is sent.' : 'Read-only request record. No review or payout action is available.'))"
    >
        <x-slot:actions>
            @include('partials.withdrawal-status', ['status' => $withdrawal->status])
            <x-ui.button href="{{ route('admin.withdrawals.index') }}" variant="secondary">Back to requests</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @if($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    @include('partials.withdrawal-details', ['withdrawal' => $withdrawal])
    @if ($canApprove)
        <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal) }}">
            @csrf
            <button type="submit">Approve request</button>
        </form>
    @endif
    @if ($canReject)
        <form class="ds-settings-form" method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}">
            @csrf
            <div class="ds-field-grid">
                <label>
                    <span>Rejection reason</span>
                    <input name="reason" value="{{ old('reason') }}" maxlength="1000" required>
                    @error('reason')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="ds-actions">
                <button type="submit">Reject request</button>
            </div>
        </form>
    @endif
    @if ($canSettle)
        <form class="ds-settings-form" method="POST" action="{{ route('admin.withdrawals.complete', $withdrawal) }}">
            @csrf
            <div class="ds-field-grid">
                <label>
                    <span>Payout network</span>
                    <input name="network" value="{{ old('network', $withdrawal->network) }}" maxlength="80" required>
                    @error('network')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span>Payout amount (USDT)</span>
                    <input name="amount" value="{{ old('amount', $withdrawal->net_amount) }}" inputmode="decimal" required>
                    @error('amount')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label class="ds-span-all">
                    <span>Payout transaction hash</span>
                    <input name="txid" value="{{ old('txid') }}" maxlength="128" autocomplete="off" required>
                    @error('txid')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span>Completion time</span>
                    <input type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')) }}" required>
                    @error('paid_at')<span class="validation-message">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="ds-actions">
                <button type="submit">Mark as paid</button>
            </div>
        </form>
    @endif
</div>
@endsection
