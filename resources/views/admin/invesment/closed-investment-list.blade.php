@extends('admin.layouts.app')
@section('content')

     <main class="dashboard-content">

    
           
           

    <div class="form-card">

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.investments.closed-investments') }}" id="closedInvestmentForm">
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
                <label>From Close Date</label>

                <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Close Date</label>

                <input type="date"
                      name="to_date"
                      value="{{ request('to_date') }}"
                       class="form-control">
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div class="d-flex justify-content-end gap-2 mb-4">

            <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.investments.closed-investments.export') }}?' + new URLSearchParams(new FormData(document.getElementById('closedInvestmentForm'))).toString()">

                <i class="bi bi-file-earmark-excel"></i>
                Export to Excel

            </button>


            <button type="button"
                    class="btn btn-primary"
                    onclick="document.getElementById('closedInvestmentForm').submit()">

                <i class="bi bi-search"></i>
                Search

            </button>


            <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.investments.closed-investments') }}'">

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>
        </form>

          <div class="col-md-2 mb-3">

                <div class="total-amount-box">

                    <span>Total Amount (USDT)</span>

                    <strong>{{ \App\Support\MoneyDisplay::usdt($totalAmount) }}</strong>

                </div>

            </div>
        <!-- TABLE -->
        <div class="table-responsive">
    <table class="table custom-table align-middle">
        <thead class="table-dark">
            <tr>
                <th>Serial No</th>
                <th>Member ID</th>
                <th>Name</th>
                <th>Investment Id</th>
                <th>Investment Amount (USDT)</th>
                <th>Closing Amount (USDT)</th>
                <th>Investment Date</th>
                <th>Close Date</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($investments as $investment)
                <tr>
                    <td>{{ $investments->firstItem() + $loop->index }}</td>
                    <td>{{ $investment->member_id }}</td>
                    <td>{{ $investment->member_name }}</td>
                    <td>{{ $investment->investment_id }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($investment->amount) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($investment->closing_amount) }}</td>
                    <td>{{ $investment->created_at?->format('d-m-Y') }}</td>
                    <td>{{ $investment->closed_at?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">No closed investments found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>


        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

            <nav>

                {{ $investments->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


    

    


       </main>

@endsection