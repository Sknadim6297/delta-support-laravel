@extends('admin.layouts.app')
@section('title', 'Inactive Member List')
@section('page-title', 'Inactive Member List')
@section('content')
<x-members.shell :lead="number_format($members->total()).' inactive members match this search.'">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('admin.members.export', ['status' => 'inactive']).'?'.http_build_query(request()->only(['member_name', 'member_id']))">Export</x-ui.button>
    </x-slot:actions>

    <x-members.summary
        :total="$totalMembers"
        :active="$activeMemberCount"
        :inactive="$inactiveMemberCount"
        :approved="$approvedPackageCount"
    />

    <form class="ds-mm-filters" method="GET" action="{{ route('admin.members.inactive') }}">
        <div>
            <label for="inactive-member-name">Name, email, or mobile</label>
            <input id="inactive-member-name" type="text" name="member_name" value="{{ $memberName }}" placeholder="Member name, email, or mobile">
        </div>
        <div>
            <label for="inactive-member-id">Member ID</label>
            <input id="inactive-member-id" type="text" name="member_id" value="{{ $memberId }}" placeholder="Member ID">
        </div>
        <button class="ds-btn" type="submit">Search</button>
        @if ($memberName !== '' || $memberId !== '')
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.members.inactive') }}">Clear</a>
        @endif
    </form>

    <x-members.panel title="Inactive members" subtitle="Accounts whose status is inactive" flush>
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
                            <td><span class="ds-mm-id">{{ $member['member_id'] }}</span></td>
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
                                    <x-members.impersonate :action="route('admin.members.impersonate', $member['id'])" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="ds-mm-empty">
                            <td colspan="11">No inactive members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ds-mm-pages">{{ $members->links('pagination::bootstrap-5') }}</div>
    </x-members.panel>
</x-members.shell>
@endsection
