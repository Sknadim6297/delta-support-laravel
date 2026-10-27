@extends('admin.layouts.app')
@section('content')


        <main class="dashboard-content">



    <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Level Commission Report</h4>
        </div>


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.report.level-income') }}" id="levelIncomeReportForm">
        <div class="row align-items-end">

            <!-- MEMBER ID -->
            <div class="col-md-4 mb-3">
                <label>Member ID</label>

                <input type="text"
                       name="member_id"
                       value="{{ request('member_id') }}"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>

            <!-- FROM DATE -->
            <div class="col-md-3 mb-3">
                <label>From Date</label>

                <input type="date"
                       name="from_date"
                       value="{{ request('from_date') }}"
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Date</label>

                <input type="date"
                       name="to_date"
                       value="{{ request('to_date') }}"
                       class="form-control">
            </div>


            <!-- LEVEL -->
            <div class="col-md-2 mb-3">
                <label>Level</label>

                <select name="level" class="form-control">
                    <option value="">All Levels</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level }}" @selected((string) request('level') === (string) $level)>Level {{ $level }}</option>
                    @endforeach
                </select>
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="d-flex justify-content-end gap-2 mb-4">

            <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.report.level-income.export') }}?' + new URLSearchParams(new FormData(document.getElementById('levelIncomeReportForm'))).toString()">

                <i class="bi bi-file-earmark-excel"></i>
                Export to Excel

            </button>


            <button type="submit"
                    class="btn btn-primary">

                <i class="bi bi-search"></i>
                Search

            </button>


            <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.report.level-income') }}'">

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>
        </form>

        @isset($levelAccess)
        <div class="rank-access-panel mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <strong>Level Commission Access</strong>
                <span class="small text-muted">{{ $rankData['current_rank']?->name ?? 'Unranked' }} · {{ $rankData['unlocked_levels'] ?? 0 }} / 32 levels unlocked</span>
            </div>
            <div class="rank-level-grid">
                @foreach ($levelAccess as $access)
                    <span class="rank-level-badge {{ $access['unlocked'] ? 'level-unlocked' : 'level-locked' }}">
                        <i class="bi {{ $access['unlocked'] ? 'bi-check-lg' : 'bi-lock' }}"></i>
                        L{{ $access['level'] }}
                    </span>
                @endforeach
            </div>
            <p class="small text-muted mb-0 mt-2">Locked levels are not eligible for Level Commission.</p>
        </div>
        @endisset

          <div class="col-md-2 mb-3">

                <div class="total-amount-box">

                    <span>Total Amount (USDT)</span>

                    <strong>{{ isset($totalAmount) ? (rtrim(rtrim(number_format((float) $totalAmount, 4, '.', ''), '0'), '.') ?: '0') : '0' }}</strong>

                </div>

            </div>
        <!-- TABLE -->
        <div class="table-responsive">

            <table class="table table-bordered table-hover member-table">

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
                            <td>{{ rtrim(rtrim(number_format((float) $transaction->income_amount, 4, '.', ''), '0'), '.') ?: '0' }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $transaction->on_amount, 4, '.', ''), '0'), '.') ?: '0' }}</td>
                            <td>{{ $transaction->from_member_id }}</td>
                            <td>{{ $transaction->level }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No level commission transactions found.</td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

            <nav>

                {{ $transactions->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


       </main>
@endsection
