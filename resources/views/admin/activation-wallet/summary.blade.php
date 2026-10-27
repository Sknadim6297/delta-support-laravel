@extends('admin.layouts.app')

@section('content')

        <main class="dashboard-content">

    
           
           

    <div class="form-card">

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.activation-wallet.summary') }}" id="activationWalletSummaryForm">
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
          

        </div>


        <!-- BUTTONS -->
        <div class="d-flex justify-content-end gap-2 mb-4">

            <button type="button"
                    class="btn btn-primary"
                    onclick="window.location.href='{{ route('admin.activation-wallet.summary.export') }}?' + new URLSearchParams(new FormData(document.getElementById('activationWalletSummaryForm'))).toString()">

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
                    onclick="window.location.href='{{ route('admin.activation-wallet.summary') }}'">

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
                <th>Member ID</th>
                <th>Member Name</th>
                <th>Act. Wallet Balance (USDT)</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($members as $member)
                <tr>
                    <td>{{ $members->firstItem() + $loop->index }}</td>
                    <td>{{ $member->member_id }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($member->activation_wallet_amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No activation wallet balances found.</td>
                </tr>
            @endforelse

        </tbody>

    </table>

</div>


        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

            <nav>

                {{ $members->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


    

    


       </main>
@endsection
