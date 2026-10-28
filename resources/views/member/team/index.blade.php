@extends('member.layouts.app')

@section('title', $view === 'direct' ? 'Direct Members' : 'Team Overview')
@section('page-title', $view === 'direct' ? 'Direct Members' : 'Team Overview')
@section('content-class', 'lh-native')

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
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="lh-card lh-card-1">
            <div class="lh-card-content label-card">
                <div class="title"><div class="growth-numbers"><h4>Direct Members</h4><h5>{{ $directTotal }}</h5></div><span class="icon"><i class="ri-user-shared-line"></i></span></div>
                <p class="card-groth"><span>{{ $directActive }} active · {{ $directInactive }} inactive</span></p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="lh-card lh-card-2">
            <div class="lh-card-content label-card">
                <div class="title"><div class="growth-numbers"><h4>Active Direct Members</h4><h5>{{ $directActive }}</h5></div><span class="icon"><i class="ri-user-follow-line"></i></span></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="lh-card lh-card-3">
            <div class="lh-card-content label-card">
                <div class="title"><div class="growth-numbers"><h4>Inactive Direct Members</h4><h5>{{ $directInactive }}</h5></div><span class="icon"><i class="ri-user-unfollow-line"></i></span></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="lh-card lh-card-4">
            <div class="lh-card-content label-card">
                <div class="title"><div class="growth-numbers"><h4>Whole Team</h4><h5>{{ $teamTotal }}</h5></div><span class="icon"><i class="ri-node-tree"></i></span></div>
                <p class="card-groth"><span>{{ $teamActive }} active · {{ $teamInactive }} inactive</span></p>
            </div>
        </div>
    </div>
</div>

<div class="lh-card">
    <div class="lh-card-header">
        <h4 class="lh-card-title">{{ $view === 'direct' ? 'Direct Members' : 'Team Overview' }}</h4>
        <div class="header-tools">
            <a class="lh-primary-btn" href="{{ route('member.team.direct') }}">Direct Members ({{ $directTotal }})</a>
            <a class="lh-primary-btn" href="{{ route('member.team.whole') }}">Whole Team ({{ $teamTotal }})</a>
        </div>
    </div>
    <div class="lh-card-content">
        <p class="lh-note">A member can appear in this network without an active package. These counts are sponsor relationships, not paid commission levels.</p>

        @if ($view === 'whole' && $levels->isNotEmpty())
            <div class="row">
                @foreach ($levels as $level => $count)
                    <div class="col-md-3 col-6">
                        <p class="lh-note"><strong>Level {{ $level }}</strong> · {{ $count }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <form class="lh-filter" method="GET" action="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">
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
            <button type="submit" class="lh-primary-btn">Search</button>
            <a class="lh-primary-btn" href="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">Clear</a>
        </form>

        <div class="lh-scroll">
            <table class="lh-data">
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
                            <td>{{ $teamMember['member_id'] }}</td>
                            <td>{{ $teamMember['member_name'] }}</td>
                            <td>{{ $teamMember['sponsor_id'] }}</td>
                            <td>{{ $teamMember['email'] }}</td>
                            <td>{{ $teamMember['mobile_no'] }}</td>
                            <td><span class="lh-status {{ $teamMember['status'] === 'active' ? 'is-active' : 'is-muted' }}">{{ ucfirst($teamMember['status']) }}</span></td>
                            <td>{{ ! empty($teamMember['package_active']) ? 'Active package' : 'No active package' }}</td>
                            <td>{{ $teamMember['created_at']?->format('d-M-Y') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $view === 'whole' ? 9 : 8 }}">No team members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($filteredMembers, 'links'))
            <div>{{ $filteredMembers->links() }}</div>
        @endif
    </div>
</div>
@endsection
