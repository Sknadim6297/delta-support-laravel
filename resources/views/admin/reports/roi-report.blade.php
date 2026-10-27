@extends('admin.layouts.app')
@section('content')


        <main>

    
           
           

    <div>

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.report.roi-report') }}" id="roiReportForm">
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


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.report.roi-report.export') }}?' + new URLSearchParams(new FormData(document.getElementById('roiReportForm'))).toString()">

                <i></i>
                Export to Excel

            </button>


            <button type="submit"
                   >

                <i></i>
                Search

            </button>


            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.report.roi-report') }}'">

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

            <table
                   >

                <thead>

                    <tr>

                        <th>Serial No</th>
                        <th>Member ID</th>
                        <th>Name</th>
                        <th>Form Investment ID</th>
                        <th>Income Amount (USDT)</th>
                        <th>On Investment Amount (USDT)</th>
                        <th>Date</th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaction->member_id }}</td>
                            <td>{{ $transaction->member_name }}</td>
                            <td>{{ $transaction->investment_id }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->income_amount) }}</td>
                            <td>{{ \App\Support\MoneyDisplay::usdt($transaction->on_amount) }}</td>
                            <td>{{ $transaction->created_at?->format('d-M-Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No ROI transactions found.</td>
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
