@extends('admin.layouts.app')
@section('title', 'Wallet Transactions')
@section('page-title', 'Wallet Transactions')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Wallet Management"
        title="Wallet Transactions"
        lead="Ledger rows are append-only. This list does not create a credit, a debit, or a payout."
    />
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Member</th>
                    <th>Wallet</th>
                    <th>Direction</th>
                    <th class="ds-money">Amount</th>
                    <th class="ds-money">Opening</th>
                    <th class="ds-money">Closing</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td>{{ $entry->member_id }}</td>
                    <td>{{ $entry->wallet }}</td>
                    <td>{{ $entry->direction }}</td>
                    <td class="ds-money">{{ $entry->amount }}</td>
                    <td class="ds-money">{{ $entry->opening_balance ?? '—' }}</td>
                    <td class="ds-money">{{ $entry->closing_balance ?? '—' }}</td>
                    <td>{{ $entry->reference }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="8">No ledger entries are stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $entries->links('pagination::bootstrap-5') }}</div>
    <x-ui.card title="Where to review stored records" subtitle="These lists do not create a credit or a debit.">
        <div class="ds-actions">
            <x-ui.button href="{{ route('admin.finance.roi-history') }}" variant="secondary">ROI History</x-ui.button>
            <x-ui.button href="{{ route('admin.finance.level-income') }}" variant="secondary">Level Income History</x-ui.button>
        </div>
    </x-ui.card>
</div>
@endsection
