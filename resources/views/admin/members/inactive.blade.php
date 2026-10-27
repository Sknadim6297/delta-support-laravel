@extends('admin.layouts.app')

@section('content')
    <div>
        <div>
            <div>
                <h1>Inactive Member List</h1>
                <p>{{ number_format($inactiveMemberCount) }} inactive · {{ number_format($activeMemberCount) }} active</p>
            </div>
            <a href="{{ route('admin.members.export', ['status' => 'inactive']) }}?{{ http_build_query(request()->only(['member_name', 'member_id'])) }}">Export</a>
        </div>

        <form method="GET" action="{{ route('admin.members.inactive') }}">
            <label>
                Member name, email, or mobile
                <input type="text" name="member_name" value="{{ $memberName }}" placeholder="Search">
            </label>
            <label>
                Member ID
                <input type="text" name="member_id" value="{{ $memberId }}" placeholder="ST100000">
            </label>
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
                            <td><span>{{ $member['account_status'] }}</span></td>
                            <td>{{ $member['package_status'] }}</td>
                            <td><a href="{{ route('admin.members.update', ['member_id' => $member['member_id']]) }}">View / Edit</a></td>
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
