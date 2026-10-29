@extends('member.layouts.app')
@section('title', 'Transfer Funds')
@section('page-title', 'Transfer Funds')

@section('content')
<x-members.shell kicker="Earnings" lead="Send USDT from your Working Wallet to another active member. This stays inside Delta Support and does not broadcast a blockchain transaction.">
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    @unless ($enabled)
        <x-ui.alert type="warning">Member transfers are disabled. No funds can be moved until an administrator turns the switch on.</x-ui.alert>
    @endunless

    @unless ($canSend)
        <x-ui.alert type="warning">This account cannot send a transfer.</x-ui.alert>
    @endunless

    <x-members.panel title="Available balance" subtitle="Pending, approved, and processing Working Wallet withdrawals are already excluded.">
        <div class="ds-mm-stats">
            <article class="ds-mm-stat">
                <span>Transferable Working Wallet</span>
                <strong>{{ \App\Support\MoneyDisplay::usdt($available) }}</strong>
                <small>Minimum {{ \App\Support\MoneyDisplay::usdt($settings->minimum_amount) }} · Maximum {{ \App\Support\MoneyDisplay::usdt($settings->maximum_amount) }} · Daily {{ \App\Support\MoneyDisplay::usdt($settings->daily_limit) }}</small>
            </article>
        </div>
    </x-members.panel>

    @if ($enabled && $canSend)
        <form method="POST" action="{{ route('member.transfers.review') }}" class="ds-mm-form">
            @csrf
            <x-members.panel title="Transfer details" subtitle="The receiver name is checked on the server. The next screen confirms the amount before anything moves.">
                <div class="ds-mm-fields">
                    <label>
                        <span>Receiver member ID</span>
                        <input name="receiver_member_id" value="{{ old('receiver_member_id') }}" maxlength="32" autocomplete="off" required data-receiver-id>
                    </label>
                    <p id="receiver-check" role="status">Enter a member ID to verify the receiver name.</p>
                    <label>
                        <span>Transfer amount (USDT)</span>
                        <input name="amount" value="{{ old('amount') }}" inputmode="decimal" autocomplete="off" required>
                    </label>
                </div>
                <div class="ds-form-actions">
                    <button type="submit">Review transfer</button>
                    <a class="ds-btn ds-btn-secondary" href="{{ route('member.transfers.history') }}">Transfer History</a>
                </div>
            </x-members.panel>
        </form>
        <script>
            (function () {
                var input = document.querySelector('[data-receiver-id]');
                var status = document.getElementById('receiver-check');
                if (!input || !status) return;
                var timer = null;
                input.addEventListener('input', function () {
                    window.clearTimeout(timer);
                    var value = input.value.trim();
                    if (value === '') {
                        status.textContent = 'Enter a member ID to verify the receiver name.';
                        return;
                    }
                    timer = window.setTimeout(function () {
                        fetch(@json(route('member.transfers.receiver')) + '?member_id=' + encodeURIComponent(value), {
                            headers: { 'Accept': 'application/json' }
                        }).then(function (response) {
                            return response.json().then(function (body) {
                                return { ok: response.ok, body: body };
                            });
                        }).then(function (result) {
                            status.textContent = result.ok && result.body.member_name
                                ? result.body.member_name + ' · ' + result.body.member_id
                                : (result.body.message || 'No active member matches that ID.');
                        }).catch(function () {
                            status.textContent = 'The receiver name could not be checked. You can still review the transfer.';
                        });
                    }, 300);
                });
            }());
        </script>
    @endif
</x-members.shell>
@endsection
