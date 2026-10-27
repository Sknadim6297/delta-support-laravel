@extends('member.layouts.app')
@section('content')

     <main>

    
           
           

    <div>

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('member.investments.closed') }}" id="closedInvestmentForm">
        <div>

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

                <button type="submit"
                   
                    >

                <i></i>
                Search

            </button>


            <button type="button"
                   
                    onclick="window.location.href='{{ route('member.investments.closed') }}'">

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
                <th>Category</th>
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
                    <td>Closed</td>
                    <td>{{ $investment->investment_id }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($investment->amount) }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($investment->closing_amount) }}</td>
                    <td>{{ $investment->created_at?->format('d-m-Y') }}</td>
                    <td>{{ $investment->closed_at?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No Record</td></tr>
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