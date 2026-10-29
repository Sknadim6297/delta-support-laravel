@extends('admin.layouts.app')

@section('title', 'Withdrawn Investments')
@section('page-title', 'Withdrawn Investments')

@section('content')
      <main>

    
          

    <div>

        <!-- PAGE TITLE -->
        <div>
            <h4>Withdrawn Investment List</h4>
        </div>

        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.investments.investment-withdrawal-list') }}" id="investmentWithdrawalListForm">
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
                <label>From Withdraw Date</label>

                <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                      >
            </div>


            <!-- TO DATE -->
            <div>
                <label>To Withdraw Date</label>

                <input type="date"
                      name="to_date"
                      value="{{ request('to_date') }}"
                      >
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>
        <div>
            <button type="submit">
                <i></i>
                Search
            </button>
            <button type="button" onclick="window.location.href='{{ route('admin.investments.investment-withdrawal-list') }}'">
                <i></i>
                Reset
            </button>
        </div>
        </form>
        <div>

                <div>

                    <span>Total Amount (USDT)</span>

                    <strong>{{ \App\Support\MoneyDisplay::usdt($totalAmount) }}</strong>
        <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr>
                    <td>{{ $withdrawals->firstItem() + $loop->index }}</td>
                    <td>{{ $withdrawal->member_id }}</td>
                    <td>{{ $withdrawal->member_name }}</td>
                    <td>{{ $withdrawal->investment_id }}</td>
                    <td>{{ \App\Support\MoneyDisplay::usdt($withdrawal->withdrawal_amount) }}</td>
                    <td>{{ $withdrawal->investment?->created_at?->format('d-m-Y') }}</td>
                    <td>{{ $withdrawal->withdrawn_at?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No investment withdrawals found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>


        <!-- PAGINATION -->
        <div>

            <nav>
                {{ $withdrawals->links('pagination::bootstrap-5') }}
            </nav>

        </div>

    </div>


    


       </main>
@endsection