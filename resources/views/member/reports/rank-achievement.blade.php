@extends('member.layouts.app')
@section('content')

<main>

    <div>

        <div>
            <h4>Rank Achievement Report</h4>
        </div>

        <form method="GET" action="{{ route('member.reports.rank-achievement') }}" id="rankAchievementReportForm">
            <div>

                <div>
                    <label>Member ID</label>

                    <input type="text"
                           name="member_id"
                           value="{{ request('member_id') }}"
                          
                           placeholder="Enter Member ID">
                </div>

                <div>
                    <label>Rank</label>

                    <select name="rank_id">
                        <option value="">All Rank</option>
                        @foreach ($ranks as $rank)
                            <option value="{{ $rank->id }}" @selected((string) request('rank_id') === (string) $rank->id)>{{ $rank->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>From Date</label>

                    <input type="date"
                           name="from_date"
                           value="{{ request('from_date') }}"
                          >
                </div>

                <div>
                    <label>To Date</label>

                    <input type="date"
                           name="to_date"
                           value="{{ request('to_date') }}"
                          >
                </div>

            </div>

            <div>
                <button type="submit">
                    <i></i>
                    Search
                </button>

                <button type="button"
                       
                        onclick="window.location.href='{{ route('member.reports.rank-achievement') }}'">
                    <i></i>
                    Reset
                </button>
            </div>
        </form>

        <div>
            <div>
                <span>Total Amount (USDT)</span>
                <strong>{{ \App\Support\MoneyDisplay::usdt($totalAmount) }}</strong>
            </div>
        </div>

        <div>
            <table>
                <thead>
                    <tr>
                        <th>Serial No</th>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Achieved Rank</th>
                        <th>Achieving Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($achievements as $achievement)
                        <tr>
                            <td>{{ $achievements->firstItem() + $loop->index }}</td>
                            <td>{{ $achievement->member_id }}</td>
                            <td>{{ $achievement->member_name }}</td>
                            <td>{{ $achievement->rank?->name ?? 'N/A' }}</td>
                            <td>{{ $achievement->achieved_at?->format('d-m-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No rank achievements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            <nav>
                {{ $achievements->links('pagination::bootstrap-5') }}
            </nav>
        </div>

    </div>

</main>

@endsection
