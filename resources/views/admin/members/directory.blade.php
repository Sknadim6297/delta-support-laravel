@extends('admin.layouts.app')
@section('title', 'All Members')
@section('page-title', 'All Members')
@section('content')
<x-members.shell :lead="$members->total().' members match this search.'">
    <x-slot:actions>
        <x-ui.button :href="route('admin.members.registration')">Register member</x-ui.button>
    </x-slot:actions>

    <x-members.summary
        :total="$totalMembers"
        :active="$activeMemberCount"
        :inactive="$inactiveMemberCount"
        :approved="$approvedPackageCount"
    />

    <form class="ds-mm-filters" method="GET">
        <div>
            <label for="member-search">Search</label>
            <input id="member-search" name="q" value="{{ request('q') }}" placeholder="Member ID, name, email, mobile, or sponsor">
        </div>
        <div>
            <label for="member-status">Account</label>
            <select id="member-status" name="status">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div>
            <label for="member-package">Approved package</label>
            <select id="member-package" name="package">
                <option value="">All packages</option>
                @foreach ($packageOptions as $option)
                    <option value="{{ $option->package_code }}" @selected($packageFilter === $option->package_code)>
                        {{ $option->package_code }} — {{ $option->package_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="ds-btn" type="submit">Search</button>
        @if (request()->filled('q') || request()->filled('status') || request()->filled('package'))
            <a class="ds-btn ds-btn-secondary" href="{{ route('admin.members.directory') }}">Clear</a>
        @endif
    </form>

    <x-members.panel title="Directory" subtitle="Saved member records" flush>
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
                        <th>First Level Business</th>
                        <th>Total Team Business</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($members as $member)
                    <tr>
                        <td><span class="ds-mm-id">{{ $member->member_id }}</span></td>
                        <td>{{ $member->member_name }} @if (str_ends_with(strtolower((string) $member->email), '@local-test.invalid'))<x-ui.badge tone="warning">Local test</x-ui.badge>@endif</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->mobile_no }}</td>
                        <td>{{ $member->sponsor_id }}</td>
                        <td>{{ $member->sponsor_name }}</td>
                        <td>{{ $member->created_at?->format('d M Y') }}</td>
                        <td>
                            <x-ui.badge :tone="$member->status === 'active' ? 'success' : 'warning'">{{ $member->status === 'active' ? 'Active' : 'Inactive' }}</x-ui.badge>
                        </td>
                        <td>
                            @php($packageDisplay = $member->packageDirectoryDisplay())
                            <x-members.package-cell
                                :tone="$packageDisplay['tone']"
                                :badge="$packageDisplay['badge']"
                                :lines="$packageDisplay['lines']"
                            />
                        </td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($businessSummaries[$member->member_id]['first_level_business'] ?? '0.0000') }}</td>
                        <td>{{ \App\Support\MoneyDisplay::usdt($businessSummaries[$member->member_id]['total_team_business'] ?? '0.0000') }}</td>
                        <td>
                            <div class="ds-row-actions">
                                <x-ui.button variant="secondary" size="sm" :href="route('admin.members.update', ['member_id' => $member->member_id])">View / Edit</x-ui.button>
                                <x-members.impersonate :action="route('admin.members.impersonate', $member)" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="ds-mm-empty"><td colspan="12">No members yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ds-mm-pages">{{ $members->links('pagination::bootstrap-5') }}</div>
    </x-members.panel>
</x-members.shell>
@endsection
