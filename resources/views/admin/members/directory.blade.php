@extends('admin.layouts.app')
@section('title', 'All Members')
@section('page-title', 'All Members')
@section('content')
<div>
    <x-ui.page-header title="All Members" :lead="$members->total().' members match this search.'" />
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Member ID, name, email, mobile, or sponsor">
        <select name="status">
            <option value="">All</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button>Search</button>
    </form>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Sponsor ID</th>
                    <th>Sponsor Name</th>
                    <th>Registration Date</th>
                    <th>Account</th>
                    <th>Package</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->mobile_no }}</td>
                    <td>{{ $member->sponsor_id }}</td>
                    <td>{{ $member->sponsor_name }}</td>
                    <td>{{ $member->created_at?->format('d M Y') }}</td>
                    <td>
                        <x-ui.badge :tone="$member->status === 'active' ? 'success' : 'warning'">{{ $member->status === 'active' ? 'Active' : 'Inactive' }}</x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge :tone="$member->has_approved_package ? 'success' : ($member->has_pending_package ? 'warning' : 'muted')">{{ $member->has_approved_package ? 'Approved package' : ($member->has_pending_package ? 'Pending package' : 'Unpaid') }}</x-ui.badge>
                    </td>
                    <td>
                        <div class="ds-row-actions">
                            <x-ui.button variant="secondary" size="sm" :href="route('admin.members.update', ['member_id' => $member->member_id])">View / Edit</x-ui.button>
                            <form class="ds-impersonate-form" method="POST" action="{{ route('admin.members.impersonate', $member) }}" target="_blank">
                                @csrf
                                <button type="submit" class="ds-btn ds-btn-sm">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                                    Login as Member
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">No members yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
