@extends('admin.layouts.app')
@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
<div class="ds-finance">
    <x-ui.page-header kicker="Support" title="Support Tickets" lead="Member conversations with the support team. Ticket totals are counts, not wallet amounts." />
    <div class="ds-stat-grid">
        <x-ui.stat label="All" :value="(string) $stats['all']" />
        <x-ui.stat label="Open" :value="(string) $stats['open']" />
        <x-ui.stat label="Awaiting admin" :value="(string) $stats['awaiting_admin']" />
        <x-ui.stat label="Awaiting member" :value="(string) $stats['awaiting_member']" />
        <x-ui.stat label="Resolved" :value="(string) $stats['resolved']" />
        <x-ui.stat label="Closed" :value="(string) $stats['closed']" />
    </div>
    <form class="ds-filter" method="GET">
        <label><span>Member ID</span><input name="member_id" value="{{ request('member_id') }}"></label>
        <label><span>Reference</span><input name="reference" value="{{ request('reference') }}"></label>
        <label><span>Category</span>
            <select name="category">
                <option value="">All categories</option>
                @foreach (\App\Models\SupportTicket::CATEGORY_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label><span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                @foreach (\App\Models\SupportTicket::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </label>
        <label><span>Priority</span>
            <select name="priority">
                <option value="">All priorities</option>
                @foreach (\App\Models\SupportTicket::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
        </label>
        <label><span>From date</span><input type="date" name="from_date" value="{{ request('from_date') }}"></label>
        <label><span>To date</span><input type="date" name="to_date" value="{{ request('to_date') }}"></label>
        <div class="ds-filter-actions"><button type="submit">Search</button></div>
    </form>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member</th>
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
                    <td><a href="{{ route('admin.support.show', $ticket) }}">{{ $ticket->reference }}</a></td>
                    <td>{{ $ticket->member_id }}</td>
                    <td>{{ $ticket->subject }}</td>
                    <td>{{ $ticket->categoryLabel() }}</td>
                    <td>{{ ucfirst($ticket->priority) }}</td>
                    <td><x-ui.badge :tone="$ticket->statusTone()">{{ $ticket->statusLabel() }}</x-ui.badge></td>
                    <td>{{ $ticket->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="7">No support tickets match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $tickets->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
