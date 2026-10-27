@extends('member.layouts.app')

@section('title', 'Member Team')

@section('content')
    <main>
        <div>
            <div>
                <div>
                    <h4>Member List</h4>
                    <div>Your member network</div>
                </div>
                <div>
                    <a href="{{ route('member.team.direct') }}">
                        Direct Members ({{ $directMembers->count() }})
                    </a>
                    <a href="{{ route('member.team.whole') }}">
                        Whole Team ({{ $wholeTeam->count() }})
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">
                <div>
                    <label>Member ID</label>
                    <input type="text" name="member_id" value="{{ $memberId }}" placeholder="Enter Member ID" data-member-autocomplete autocomplete="off">
                </div>
                <div>
                    <label>Member Name</label>
                    <input type="text" name="member_name" value="{{ $memberName }}" placeholder="Enter Member Name">
                </div>
                <div>
                    <button type="submit">
                        <i></i> Search
                    </button>
                </div>
                <div>
                    <a href="{{ $view === 'direct' ? route('member.team.direct') : route('member.team.whole') }}">Clear</a>
                </div>
            </form>

            <div>
                <table>
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
                                    <span>{{ ucfirst($teamMember['status']) }}</span>
                                </td>
                                <td>{{ $teamMember['created_at']?->format('d-M-Y') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $view === 'whole' ? 8 : 7 }}">No team members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
