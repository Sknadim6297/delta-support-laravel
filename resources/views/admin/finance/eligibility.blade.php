@extends('admin.layouts.app')
@section('title', 'Referral Eligibility')
@section('page-title', 'Referral Eligibility')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Referral Eligibility"
        lead="Qualifying directs are distinct sponsored members with an approved Tour + ROI or Premium package. This page does not pay commission."
    />
    <form class="ds-filter" method="GET">
        <label>
            <span>Member</span>
            <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search members">
        </label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            @if (request()->filled('q'))
                <a class="ds-btn ds-btn-secondary" href="{{ route('admin.finance.eligibility') }}">Reset</a>
            @endif
        </div>
    </form>
    <x-ui.table>
        <table class="ds-table-wide">
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th class="ds-money">Direct referrals</th>
                    <th class="ds-money">Qualifying directs</th>
                    <th>Unlocked levels</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                @php($summary = $summaries[$member->member_id] ?? ['direct_count' => 0, 'qualifying_direct_count' => 0, 'unlocked_levels' => 0, 'status' => 'No levels unlocked'])
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td class="ds-money">{{ $summary['direct_count'] }}</td>
                    <td class="ds-money">{{ $summary['qualifying_direct_count'] }}</td>
                    <td>{{ $summary['unlocked_levels'] }} of 20</td>
                    <td><x-ui.badge :tone="($summary['unlocked_levels'] ?? 0) > 0 ? 'success' : 'muted'">{{ $summary['status'] }}</x-ui.badge></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="6">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
    <div class="ds-pagination">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
