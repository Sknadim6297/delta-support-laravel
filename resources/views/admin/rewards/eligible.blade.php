@extends('admin.layouts.app')
@section('title', 'Eligible Members')
@section('page-title', 'Eligible Members')
@section('content')
<div class="ds-catalog">
    <x-ui.page-header lead="Figures come from stored qualifications. Recalculate a campaign before treating them as current.">
        <x-slot:actions>
            <x-ui.button :href="route('admin.rewards.reports')" variant="secondary">Reports</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <form class="ds-filter-form" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search member" aria-label="Search member">
        <input name="member_id" value="{{ request('member_id') }}" placeholder="Member ID" aria-label="Member ID">
        <select name="campaign_id" aria-label="Campaign">
            <option value="">All campaigns</option>
            @foreach ($campaigns as $campaign)
                <option value="{{ $campaign->id }}" @selected((string) request('campaign_id') === (string) $campaign->id)>{{ $campaign->title }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="Eligibility status">
            <option value="">Qualified and selected</option>
            <option value="all" @selected(request('status') === 'all')>All stored statuses</option>
            @foreach (['not_eligible' => 'Not eligible', 'in_progress' => 'In progress', 'qualified' => 'Qualified', 'selected' => 'Selected', 'superseded' => 'Superseded', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" aria-label="Qualified from">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="Qualified to">
        <button>Search</button>
    </form>
    <div class="ds-actions">
        @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
            <a href="{{ route('admin.rewards.export', array_merge(request()->query(), ['format' => $format])) }}">{{ $label }}</a>
        @endforeach
    </div>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th>Sponsor</th>
                    <th>Campaign</th>
                    <th>Reward</th>
                    <th>Target</th>
                    <th>Achieved</th>
                    <th>Qualified</th>
                    <th>Eligibility</th>
                    <th>Selection</th>
                    <th>Delivery</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($qualifications as $row)
                <tr>
                    <td>{{ $row->member_id }}</td>
                    <td>{{ $row->member_name }}</td>
                    <td>{{ $row->sponsor_id }}</td>
                    <td>{{ $row->campaign?->title }}</td>
                    <td>{{ $row->assignment?->gift_name ?: $row->campaign?->gift_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($row->target_amount) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($row->achieved_amount) }}</td>
                    <td>{{ $row->qualified_at?->timezone('Asia/Kolkata')->format('d M Y H:i') }}</td>
                    <td><x-ui.badge :tone="$row->statusTone()">{{ $row->statusLabel() }}</x-ui.badge></td>
                    <td>{{ $row->assignment?->statusLabel() ?? '—' }}</td>
                    <td>{{ $row->assignment?->delivery?->statusLabel() ?? '—' }}</td>
                    <td><a href="{{ route('admin.rewards.qualifications.show', $row) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="12">No stored qualifications match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div>{{ $qualifications->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
