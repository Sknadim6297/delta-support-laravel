@extends('admin.layouts.app')
@section('title', 'Payout History')
@section('page-title', 'Payout History')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Withdrawals"
        title="Payout History"
        lead="Completed payout records that already store a transaction ID. This page does not send a transfer."
    />
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Withdrawal reference</th>
                    <th>Member</th>
                    <th>Network</th>
                    <th>Payout TxID</th>
                    <th class="ds-money">Amount</th>
                    <th>Completion date</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($payouts as $payout)
                <tr>
                    <td><a href="{{ route('admin.withdrawals.show', $payout) }}">{{ $payout->reference }}</a></td>
                    <td>{{ $payout->member_id }}</td>
                    <td>{{ $payout->payout_network ?: ($payout->network ?: 'Not recorded') }}</td>
                    <td>{{ $payout->payout_txid }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::format($payout->net_amount ?? $payout->amount, $payout->denomination) }}</td>
                    <td>{{ $payout->updated_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="6">No payouts recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $payouts->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
