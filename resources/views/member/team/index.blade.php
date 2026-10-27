@extends('member.layouts.app')

@section('title', 'Member Team')

@section('content')
    <main class="member-content">
        <div class="form-card">
            <div class="form-title d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h4 class="mb-1">Member List</h4>
                    <div class="text-muted small">Your member network</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('member.team.direct') }}" class="btn {{ $view === 'direct' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Direct Members ({{ $directMembers->count() }})
                    </a>
                    <a href="{{ route('member.team.whole') }}" class="btn {{ $view === 'whole' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Whole Team ({{ $wholeTeam->count() }})
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}" class="row align-items-end mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <label>Member ID</label>
                    <input type="text" name="member_id" value="{{ $memberId }}" class="form-control" placeholder="Enter Member ID" data-member-autocomplete autocomplete="off">
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <label>Member Name</label>
                    <input type="text" name="member_name" value="{{ $memberName }}" class="form-control" placeholder="Enter Member Name">
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}" class="btn btn-outline-secondary w-full">Clear</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table align-middle">
                    <thead>
                        <tr>
                            @if ($view === 'whole')
                                <th>Level</th>
                            @endif
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Sponsor ID</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Joining Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredMembers as $teamMember)
                            <tr>
                                @if ($view === 'whole')
                                    <td>Level {{ $teamMember['level'] }}</td>
                                @endif
                                <td>{{ $teamMember['member_id'] }}</td>
                                <td>{{ $teamMember['member_name'] }}</td>
                                <td>{{ $teamMember['sponsor_id'] }}</td>
                                <td>{{ $teamMember['email'] }}</td>
                                <td>{{ $teamMember['mobile_no'] }}</td>
                                <td>
                                    <span class="badge {{ strtolower($teamMember['status']) === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ ucfirst($teamMember['status']) }}
                                    </span>
                                </td>
                                <td>{{ $teamMember['created_at']?->format('d-M-Y') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $view === 'whole' ? 8 : 7 }}" class="text-center">No team members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
