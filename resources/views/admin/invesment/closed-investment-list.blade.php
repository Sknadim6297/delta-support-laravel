@extends('admin.layouts.app')
@section('content')

     <main>

    
           
           

    <div>

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.investments.closed-investments') }}" id="closedInvestmentForm">
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
                <label>From Close Date</label>

                <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                      >
            </div>


            <!-- TO DATE -->
            <div>
                <label>To Close Date</label>

                <input type="date"
                      name="to_date"
                      value="{{ request('to_date') }}"
                      >
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.investments.closed-investments.export') }}?' + new URLSearchParams(new FormData(document.getElementById('closedInvestmentForm'))).toString()">

                <i></i>
                Export to Excel

            </button>


            <button type="button"
                   
                    onclick="document.getElementById('closedInvestmentForm').submit()">

                <i></i>
                Search

            </button>


            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.investments.closed-investments') }}'">

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
        <!-- TABLE -->
        <div>
    <table>
        <thead>
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
                <tr><td colspan="8">No closed investments found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>


        <!-- PAGINATION -->
        <div>

            <nav>

                {{ $investments->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


    

    


       </main>

@endsection