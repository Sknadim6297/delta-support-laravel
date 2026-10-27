@extends('member.layouts.app')
@section('content')

<main class="dashboard-content">

    
           
           

    <div class="form-card">

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('member.investments.active') }}">
        <div class="row align-items-end">

            <!-- FROM DATE -->
            <div class="col-md-3 mb-3">
                <label>From Investment Date</label>

                  <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Investment Date</label>

                  <input type="date"
                      name="to_date"
                      value="{{ request('to_date') }}"
                       class="form-control">
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div class="d-flex justify-content-end gap-2 mb-4">

                <button type="submit"
                    class="btn btn-primary">

                <i class="bi bi-search"></i>
                Search

            </button>


                <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('member.investments.active') }}'">

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
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Serial No</th>
                <th>Category</th>
                <th>Investment Id</th>
                <th>Amount (USDT)</th>
                <th>Investment Date</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($investments as $investment)
                <tr>
                    <td>{{ $investments->firstItem() + $loop->index }}</td>
                    <td>Active</td>
                    <td>{{ $investment->investment_id }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($investment->amount) }}</td>
                    <td>{{ $investment->created_at?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No Record</td>
                </tr>
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