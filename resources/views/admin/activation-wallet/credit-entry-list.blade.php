@extends('admin.layouts.app')

@section('content')
   
        <main class="dashboard-content">

    
           
           

    <div class="form-card">

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.activation-wallet.credit-entry.list') }}" id="activationWalletListForm">
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
                <label>From Transfer Date</label>

                <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Transfer Date</label>

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
                    onclick="window.location.href='{{ route('admin.activation-wallet.credit-entry.export') }}?' + new URLSearchParams(new FormData(document.getElementById('activationWalletListForm'))).toString()">

                <i class="bi bi-file-earmark-excel"></i>
                Export to Excel

            </button>


                <button type="submit"
                    class="btn btn-primary"
                    >

                <i class="bi bi-search"></i>
                Search

            </button>


                <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.activation-wallet.credit-entry.list') }}'">

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

    <table class="table table-bordered table-hover member-table">

        <thead>
            <tr>
                <th>Serial No</th>
                <th>To Member ID</th>
                <th>To Member Name</th>
                <th>Amount (USDT)</th>
                <th>Transfer Date</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ $transactions->firstItem() + $loop->index }}</td>
                    <td>{{ $transaction->member_id }}</td>
                    <td>{{ $transaction->member_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($transaction->amount) }}</td>
                    <td>{{ $transaction->created_at?->format('d-M-Y H:i:s') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No activation wallet transfers found.</td>
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