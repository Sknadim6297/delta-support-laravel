@extends('member.layouts.app')
@section('title', 'My Support Tickets')
@section('page-title', 'My Support Tickets')

@section('content')
<x-members.shell kicker="Support" lead="Tickets you opened with Delta Support. Other members' tickets are not listed.">
    <div class="ds-form-actions">
        <a class="ds-btn" href="{{ route('member.support.create') }}">New Ticket</a>
    </div>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Opened</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('member.support.show', $ticket) }}">{{ $ticket->reference }}</a></td>
                    <td>{{ $ticket->subject }}</td>
                    <td>{{ $ticket->categoryLabel() }}</td>
                    <td>{{ ucfirst($ticket->priority) }}</td>
                    <td><x-ui.badge :tone="$ticket->statusTone()">{{ $ticket->statusLabel() }}</x-ui.badge></td>
                    <td>{{ $ticket->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="6">You have not opened a ticket yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $tickets->links('pagination::bootstrap-5') }}</div>
</x-members.shell>
@endsection
