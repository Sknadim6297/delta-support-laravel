@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Referral Eligibility</h1>
        <p>Qualifying directs are distinct sponsored members with an approved Tour + ROI or Premium package. This page does not pay commission.</p>
    </header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search member ID or name" aria-label="Search members">
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th>Direct referrals</th>
                    <th>Qualifying directs</th>
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
                    <td>{{ $summary['direct_count'] }}</td>
                    <td>{{ $summary['qualifying_direct_count'] }}</td>
                    <td>{{ $summary['unlocked_levels'] }} of 20</td>
                    <td>{{ $summary['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No members match this search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
