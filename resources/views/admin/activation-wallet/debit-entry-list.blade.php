@extends('admin.layouts.app')

@section('title', 'Activation Debits')
@section('page-title', 'Activation Debits')

@section('content')

<main>

    
           
           

<div>

    @if (isset($errors) && $errors->any())
        <div>{{ $errors->first() }}</div>
    @endif

    <!-- TITLE -->
    


    <!-- SEARCH AREA -->
    <form method="GET" action="{{ route('admin.activation-wallet.debit-entry.list') }}" id="activationWalletDebitListForm">
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

       



        <button type="submit"
               
                >

            <i></i>
            Search

        </button>


        <button type="button"
               
                onclick="window.location.href='{{ route('admin.activation-wallet.debit-entry.list') }}'">

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
            <th>ID</th>
            <th>Name</th>
            <th>Remarks</th>
            <th>Amount (USDT)</th>
            <th>Date</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($transactions as $transaction)
            <tr>
                <td>{{ $transactions->firstItem() + $loop->index }}</td>
                <td>{{ $transaction->member_id }}</td>
                <td>{{ $transaction->member_name }}</td>
                <td>{{ $transaction->remarks }}</td>
                <td>{{ \App\Support\MoneyDisplay::usdt($transaction->amount) }}</td>
                <td>{{ $transaction->created_at?->format('d-M-Y H:i:s') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No activation wallet force debits found.</td>
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
