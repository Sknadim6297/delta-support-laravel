@extends('member.layouts.app')
@section('title', 'Transfer History')
@section('page-title', 'Transfer History')

@section('content')
<x-members.shell kicker="Earnings" lead="Transfers you sent or received. Other members' transfers are not listed.">
    <div class="ds-form-actions">
        <a class="ds-btn ds-btn-secondary" href="{{ route('member.transfers.create') }}">Transfer Funds</a>
    </div>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date and time</th>
                    <th>Direction</th>
                    <th>Counterparty</th>
                    <th class="ds-money">Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($transfers as $transfer)
                @php
                    $sent = $transfer->sender_member_id === $member->member_id;
                @endphp
                <tr>
                    <td>{{ $transfer->transfer_reference }}</td>
                    <td>{{ $transfer->completed_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                    <td>{{ $sent ? 'Sent' : 'Received' }}</td>
                    <td>{{ $sent ? $transfer->receiver_member_id : $transfer->sender_member_id }}</td>
                    <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($transfer->amount) }}</td>
                    <td>{{ ucfirst($transfer->status) }}</td>
                    <td><a href="{{ route('member.transfers.show', $transfer) }}">Receipt</a></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="7">No transfers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $transfers->links('pagination::bootstrap-5') }}</div>
</x-members.shell>
@endsection
