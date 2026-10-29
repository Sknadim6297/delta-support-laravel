@extends('admin.layouts.app')
@section('title', 'Transfer Details')
@section('page-title', 'Transfer Details')

@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        title="{{ $transfer->transfer_reference }}"
        lead="The original amount, fee, and parties stay as they were posted."
    />
    <x-ui.card title="Transfer">
        <dl>
            <div><dt>Sender</dt><dd>{{ $transfer->sender_member_id }}</dd></div>
            <div><dt>Receiver</dt><dd>{{ $transfer->receiver_member_id }}</dd></div>
            <div><dt>Wallet</dt><dd>Working Wallet to Working Wallet</dd></div>
            <div><dt>Amount</dt><dd>{{ \App\Support\MoneyDisplay::usdt($transfer->amount) }}</dd></div>
            <div><dt>Fee</dt><dd>{{ \App\Support\MoneyDisplay::usdt($transfer->fee_amount) }}</dd></div>
            <div><dt>Currency</dt><dd>{{ $transfer->currency }}</dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($transfer->status) }}</dd></div>
            <div><dt>Business date</dt><dd>{{ $transfer->business_date?->toDateString() }}</dd></div>
            <div><dt>Completed</dt><dd>{{ $transfer->completed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST</dd></div>
        </dl>
    </x-ui.card>

    @if ($transfer->status === \App\Models\MemberTransfer::COMPLETED)
        <x-ui.card title="Audited reversal" subtitle="This posts the opposite Working Wallet entries. It does not change the original amount.">
            @if ($errors->any())
                <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
            @endif
            <form method="POST" action="{{ route('admin.member-transfers.reverse', $transfer) }}">
                @csrf
                <label>
                    <span>Reason</span>
                    <textarea name="reason" rows="3" maxlength="500" required>{{ old('reason') }}</textarea>
                </label>
                <div class="ds-form-actions">
                    <button type="submit">Reverse transfer</button>
                </div>
            </form>
        </x-ui.card>
    @else
        <x-ui.card title="Reversal">
            <p>Reversed {{ $transfer->reversed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST by {{ $transfer->reversal_actor }}.</p>
            <p>{{ $transfer->reversal_reason }}</p>
        </x-ui.card>
    @endif

    <p><a href="{{ route('admin.member-transfers.index') }}">Back to Member Transfers</a></p>
</div>
@endsection
