@extends('admin.layouts.app')
@section('content')


        <main>



    <div>

        <!-- TITLE -->
        <div>
            <h4>Level Commission Report</h4>
        </div>


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.report.level-income') }}" id="levelIncomeReportForm">
        <div>

            <!-- MEMBER ID -->
            <div>
                <label>Member ID</label>

                <input type="text"
                       name="member_id"
                       value="{{ request('member_id') }}"
                      
                       placeholder="Enter Member ID">
            </div>

            <!-- FROM DATE -->
            <div>
                <label>From Date</label>

                <input type="date"
                       name="from_date"
                       value="{{ request('from_date') }}"
                      >
            </div>


            <!-- TO DATE -->
            <div>
                <label>To Date</label>

                <input type="date"
                       name="to_date"
                       value="{{ request('to_date') }}"
                      >
            </div>


            <!-- LEVEL -->
            <div>
                <label>Level</label>

                <select name="level">
                    <option value="">All Levels</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level }}" @selected((string) request('level') === (string) $level)>Level {{ $level }}</option>
                    @endforeach
                </select>
            </div>

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.report.level-income.export') }}?' + new URLSearchParams(new FormData(document.getElementById('levelIncomeReportForm'))).toString()">

                <i></i>
                Export to Excel

            </button>


            <button type="submit"
                   >

                <i></i>
                Search

            </button>


            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.report.level-income') }}'">

                <i></i>
                Reset

            </button>

        </div>
        </form>

        @isset($levelAccess)
        <div>
            <div>
                <strong>Level Commission Access</strong>
                <span>{{ $rankData['current_rank']?->name ?? 'Unranked' }} · {{ $rankData['unlocked_levels'] ?? 0 }} / 32 levels unlocked</span>
            </div>
            <div>
                @foreach ($levelAccess as $access)
                    <span class="{{ $access['unlocked'] ? 'level-unlocked' : 'level-locked' }}">
                        L{{ $access['level'] }}
                    </span>
                @endforeach
            </div>
            <p>Locked levels are not eligible for Level Commission.</p>
        </div>
        @endisset

          <div>

                <div>

                    <span>Total Amount (USDT)</span>

                    <strong>{{ \App\Support\MoneyDisplay::usdt($totalAmount ?? 0) }}</strong>

                </div>

            </div>
        <!-- TABLE -->
        <div>

            <table>

                <thead>

                    <tr>

                        <th>Serial No</th>
                        <th>Reference</th>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Income Amount (USDT)</th>
                        <th>On Amount (USDT)</th>
                        <th>From Member ID</th>
                        <th>From Level</th>
                        <th>Date</th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaction->reference }}</td>
                            <td>{{ $transaction->member_id }}</td>
                            <td>{{ $transaction->member_name }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->income_amount) }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->on_amount) }}</td>
                            <td>{{ $transaction->from_member_id }}</td>
                            <td>{{ $transaction->level }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No level commission transactions found.</td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->
        <div>

            <nav>

                {{ $transactions->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


       </main>
@endsection
