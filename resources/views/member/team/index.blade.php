@extends('member.layouts.app')

@section('title', $view === 'direct' ? 'Direct Members' : 'Team Overview')
@section('page-title', $view === 'direct' ? 'Direct Members' : 'Team Overview')

@php
    $directTotal = $directMembers->count();
    $directActive = $directMembers->where('status', 'active')->count();
    $directInactive = $directTotal - $directActive;
    $teamTotal = $wholeTeam->count();
    $teamActive = $wholeTeam->where('status', 'active')->count();
    $teamInactive = $teamTotal - $teamActive;
    $levels = $wholeTeam->groupBy('level')->map->count()->sortKeys();
@endphp

@section('content')
<x-members.shell kicker="My network" :lead="$view === 'direct' ? 'People who registered with your Member ID.' : 'Sponsor relationships already stored under your account.'">
    <x-slot:actions>
        <x-ui.button variant="{{ $view === 'direct' ? 'primary' : 'secondary' }}" :href="route('member.team.direct')">Direct Members ({{ $directTotal }})</x-ui.button>
        <x-ui.button variant="{{ $view === 'whole' ? 'primary' : 'secondary' }}" :href="route('member.team.whole')">Whole Team ({{ $teamTotal }})</x-ui.button>
    </x-slot:actions>

    <div class="ds-mm-stats">
        <article class="ds-mm-stat">
            <span>Direct members</span>
            <strong>{{ $directTotal }}</strong>
            <small>{{ $directActive }} active · {{ $directInactive }} inactive</small>
        </article>
        <article class="ds-mm-stat">
            <span>Active direct</span>
            <strong>{{ $directActive }}</strong>
            <small>Status is active</small>
        </article>
        <article class="ds-mm-stat">
            <span>Inactive direct</span>
            <strong>{{ $directInactive }}</strong>
            <small>Status is inactive</small>
        </article>
        <article class="ds-mm-stat">
            <span>Whole team</span>
            <strong>{{ $teamTotal }}</strong>
            <small>{{ $teamActive }} active · {{ $teamInactive }} inactive</small>
        </article>
    </div>

    @if ($view === 'whole' && $levels->isNotEmpty())
        <div class="ds-mm-stats">
            @foreach ($levels as $level => $count)
                <article class="ds-mm-stat">
                    <span>Level {{ $level }}</span>
                    <strong>{{ $count }}</strong>
                </article>
            @endforeach
        </div>
    @endif

    <form class="ds-mm-filters" method="GET" action="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">
            <div>
                <label for="team-member-id">Member ID</label>
                <input id="team-member-id" type="text" name="member_id" value="{{ $memberId }}" placeholder="Enter Member ID" data-member-autocomplete autocomplete="off">
            </div>
            <div>
                <label for="team-member-name">Member Name</label>
                <input id="team-member-name" type="text" name="member_name" value="{{ $memberName }}" placeholder="Enter Member Name">
            </div>
            <div>
                <label for="team-status">Status</label>
                <select id="team-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="ds-btn">Search</button>
            <a class="ds-btn ds-btn-secondary" href="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">Clear</a>
        </form>

    <x-members.panel :title="$view === 'direct' ? 'Direct Members' : 'Team Overview'" subtitle="A member can appear here without an active package. These counts are sponsor relationships, not paid commission levels." flush>
        <div>
            <table>
                <thead>
                    <tr>
                        @if ($view === 'whole')<th>Level</th>@endif
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Sponsor ID</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Package</th>
                        <th>Joining Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredMembers as $teamMember)
                        <tr>
                            @if ($view === 'whole')<td>Level {{ $teamMember['level'] }}</td>@endif
                            <td><span class="ds-mm-id">{{ $teamMember['member_id'] }}</span></td>
                            <td>{{ $teamMember['member_name'] }}</td>
                            <td>{{ $teamMember['sponsor_id'] }}</td>
                            <td>{{ $teamMember['email'] }}</td>
                            <td>{{ $teamMember['mobile_no'] }}</td>
                            <td>
                                <x-ui.badge :tone="$teamMember['status'] === 'active' ? 'success' : 'warning'">{{ ucfirst($teamMember['status']) }}</x-ui.badge>
                            </td>
                            <td>{{ ! empty($teamMember['package_active']) ? 'Active package' : 'No active package' }}</td>
                            <td>{{ $teamMember['created_at']?->format('d M Y') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr class="ds-mm-empty">
                            <td colspan="{{ $view === 'whole' ? 9 : 8 }}">No team members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($filteredMembers, 'links'))
            <div class="ds-mm-pages">{{ $filteredMembers->links('pagination::bootstrap-5') }}</div>
        @endif
    </x-members.panel>
</x-members.shell>
@endsection
