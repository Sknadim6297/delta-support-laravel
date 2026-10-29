@extends('member.layouts.app')
@section('title', 'Confirm Transfer')
@section('page-title', 'Confirm Transfer')

@section('content')
<x-members.shell kicker="Earnings" lead="Check the summary, then enter your security PIN. The PIN is not stored in this page or in the transfer record.">
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <dialog class="ds-modal" open aria-labelledby="transfer-confirm-title">
        <h2 id="transfer-confirm-title">Confirm transfer</h2>
        <dl class="ds-mm-stats">
            <div><dt>Sender</dt><dd>{{ $member->member_id }}</dd></div>
            <div><dt>Receiver</dt><dd>{{ $preview['receiver_name'] }} · {{ $preview['receiver_member_id'] }}</dd></div>
            <div><dt>Transfer amount</dt><dd>{{ \App\Support\MoneyDisplay::usdt($preview['amount']) }}</dd></div>
            <div><dt>Transfer fee</dt><dd>{{ \App\Support\MoneyDisplay::usdt($preview['fee']) }}</dd></div>
            <div><dt>Total deduction</dt><dd>{{ \App\Support\MoneyDisplay::usdt($preview['total']) }}</dd></div>
            <div><dt>Receiver gets</dt><dd>{{ \App\Support\MoneyDisplay::usdt($preview['amount']) }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('member.transfers.store') }}">
            @csrf
            <input type="hidden" name="receiver_member_id" value="{{ $preview['receiver_member_id'] }}">
            <input type="hidden" name="amount" value="{{ $preview['amount'] }}">
            <input type="hidden" name="idempotency_key" value="{{ $preview['idempotency_key'] }}">
            <x-security.pin-input name="security_pin" label="Security PIN" :autofocus="true" />
            <div class="ds-form-actions">
                <button type="submit">Confirm Transfer</button>
                <a class="ds-btn ds-btn-secondary" href="{{ route('member.transfers.create') }}">Back</a>
            </div>
        </form>
    </dialog>
</x-members.shell>
@endsection
