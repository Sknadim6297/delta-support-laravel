@extends('member.layouts.app')
@section('title', 'Transfer Receipt')
@section('page-title', 'Transfer Receipt')

@section('content')
@php
    $sent = $transfer->sender_member_id === $member->member_id;
@endphp
<x-members.shell kicker="Earnings" lead="{{ $transfer->transfer_reference }}">
    <x-members.panel title="Receipt" subtitle="The security PIN is not stored on this receipt.">
        <dl class="ds-mm-stats">
            <div><dt>Reference</dt><dd>{{ $transfer->transfer_reference }}</dd></div>
            <div><dt>Date and time</dt><dd>{{ $transfer->completed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST</dd></div>
            <div><dt>Direction</dt><dd>{{ $sent ? 'Sent' : 'Received' }}</dd></div>
            <div><dt>Sender</dt><dd>{{ $transfer->sender_member_id }}</dd></div>
            <div><dt>Receiver</dt><dd>{{ $transfer->receiver_member_id }}</dd></div>
            <div><dt>Wallet</dt><dd>Working Wallet</dd></div>
            <div><dt>Amount</dt><dd>{{ \App\Support\MoneyDisplay::usdt($transfer->amount) }}</dd></div>
            <div><dt>Fee</dt><dd>{{ \App\Support\MoneyDisplay::usdt($transfer->fee_amount) }}</dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($transfer->status) }}</dd></div>
        </dl>
        <div class="ds-form-actions">
            <a class="ds-btn ds-btn-secondary" href="{{ route('member.transfers.history') }}">Transfer History</a>
        </div>
    </x-members.panel>
</x-members.shell>
@endsection
