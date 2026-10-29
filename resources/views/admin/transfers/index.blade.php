@extends('admin.layouts.app')
@section('title', 'Member Transfers')
@section('page-title', 'Member Transfers')

@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        title="Member Transfers"
        lead="Internal Working Wallet transfers. Volume counts each transfer once. Completed amounts cannot be edited."
    />

    <div class="ds-stat-grid">
        <x-ui.stat label="Completed volume" :value="\App\Support\MoneyDisplay::usdt($volume)" hint="Sum of transfer amounts, not debit plus credit" />
        <x-ui.stat label="Completed transfers" :value="(string) $count" />
        <x-ui.stat label="Transfers" :value="$settings->member_transfers_enabled ? 'On' : 'Off'" hint="Default is off" />
    </div>

    <x-ui.card title="Transfer controls" subtitle="Saving this form does not enable ROI, level commission, withdrawals, external payouts, or the daily financial cycle.">
        <form class="ds-settings-form" method="POST" action="{{ route('admin.member-transfers.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="ds-field-grid">
                <label>
                    <span>Enable member transfers</span>
                    <input type="checkbox" name="member_transfers_enabled" value="1" @checked(old('member_transfers_enabled', $settings->member_transfers_enabled))>
                </label>
                <label>
                    <span>Minimum (USDT)</span>
                    <input name="minimum_amount" value="{{ old('minimum_amount', $settings->minimum_amount) }}" inputmode="decimal" required>
                </label>
                <label>
                    <span>Maximum (USDT)</span>
                    <input name="maximum_amount" value="{{ old('maximum_amount', $settings->maximum_amount) }}" inputmode="decimal" required>
                </label>
                <label>
                    <span>Daily limit (USDT)</span>
                    <input name="daily_limit" value="{{ old('daily_limit', $settings->daily_limit) }}" inputmode="decimal" required>
                </label>
                <label>
                    <span>Fee (USDT)</span>
                    <input name="fee_amount" value="{{ old('fee_amount', $settings->fee_amount) }}" inputmode="decimal" required>
                </label>
            </div>
            @if ($errors->any())
                <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
            @endif
            <div class="ds-form-actions">
                <button type="submit">Save transfer settings</button>
            </div>
        </form>
    </x-ui.card>

    <form class="ds-filter" method="GET">
        <label><span>Sender</span><input name="sender_member_id" value="{{ request('sender_member_id') }}"></label>
        <label><span>Receiver</span><input name="receiver_member_id" value="{{ request('receiver_member_id') }}"></label>
        <label><span>Reference</span><input name="reference" value="{{ request('reference') }}"></label>
        <label><span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
        <label><span>From date</span><input type="date" name="from_date" value="{{ request('from_date') }}"></label>
        <label><span>To date</span><input type="date" name="to_date" value="{{ request('to_date') }}"></label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.member-transfers.export', request()->query()) }}">Export CSV</a>
        </div>
    </form>

    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Business date</th>
                    <th>Sender</th>
                    <th>Receiver</th>
                    <th class="ds-money">Amount</th>
                    <th class="ds-money">Fee</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td>{{ $transfer->transfer_reference }}</td>
                    <td>{{ $transfer->business_date?->toDateString() }}</td>
                    <td>{{ $transfer->sender_member_id }}</td>
                    <td>{{ $transfer->receiver_member_id }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($transfer->amount) }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($transfer->fee_amount) }}</td>
                    <td>{{ ucfirst($transfer->status) }}</td>
                    <td><a href="{{ route('admin.member-transfers.show', $transfer) }}">Details</a></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="8">No member transfers are stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $transfers->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
