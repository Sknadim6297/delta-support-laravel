@extends('admin.layouts.app')
@section('title', 'Inactive Member List')
@section('page-title', 'Inactive Member List')
@section('content')
<div>
    <x-ui.page-header title="Inactive Member List" :lead="number_format($inactiveMemberCount).' inactive · '.number_format($activeMemberCount).' active'">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.members.export', ['status' => 'inactive']).'?'.http_build_query(request()->only(['member_name', 'member_id']))">Export</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.members.inactive') }}">
        <input type="text" name="member_name" value="{{ $memberName }}" placeholder="Member name, email, or mobile">
        <input type="text" name="member_id" value="{{ $memberId }}" placeholder="Member ID">
        <button type="submit">Search</button>
    </form>

    <div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Sponsor ID</th>
                    <th>Sponsor Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>PAN Card Number</th>
                    <th>Registration</th>
                    <th>Account</th>
                    <th>Package</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member['member_id'] }}</td>
                        <td>{{ $member['name'] }}</td>
                        <td>{{ $member['sponsor_id'] }}</td>
                        <td>{{ $member['sponsor_name'] }}</td>
                        <td>{{ $member['email'] }}</td>
                        <td>{{ $member['mobile'] }}</td>
                        <td>{{ $member['pan_card_no'] }}</td>
                        <td>{{ $member['joining_date'] }}</td>
                        <td><x-ui.badge tone="warning">{{ $member['account_status'] }}</x-ui.badge></td>
                        <td>
                            <x-ui.badge :tone="$member['package_status'] === 'Approved package' ? 'success' : ($member['package_status'] === 'Pending package' ? 'warning' : 'muted')">{{ $member['package_status'] }}</x-ui.badge>
                        </td>
                        <td>
                            <div class="ds-row-actions">
                                <x-ui.button variant="secondary" size="sm" :href="route('admin.members.update', ['member_id' => $member['member_id']])">View / Edit</x-ui.button>
                                <form class="ds-impersonate-form" method="POST" action="{{ route('admin.members.impersonate', $member['id']) }}" target="_blank">
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
                    <tr>
                        <td colspan="11">No inactive members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
