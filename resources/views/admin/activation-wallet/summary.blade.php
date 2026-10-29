@extends('admin.layouts.app')

@section('title', 'Activation Wallet')
@section('page-title', 'Activation Wallet')

@section('content')

        <main>

    
           
           

    <div>

        @if (isset($errors) && $errors->any())
            <div>{{ $errors->first() }}</div>
        @endif

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.activation-wallet.summary') }}" id="activationWalletSummaryForm">
        <div>

            <!-- MEMBER ID -->
            <div>
                <label>Member ID</label>

                <input type="text"
                      name="member_id"
                      value="{{ request('member_id') }}"
                      
                       placeholder="Enter Member ID">
            </div>
          

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.activation-wallet.summary.export') }}?' + new URLSearchParams(new FormData(document.getElementById('activationWalletSummaryForm'))).toString()">

                <i></i>
                Export to Excel

            </button>


            <button type="submit"
                   
                    >

                <i></i>
                Search

            </button>


            <button type="button"
                   
                    onclick="window.location.href='{{ route('admin.activation-wallet.summary') }}'">

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
                    <td colspan="4">No activation wallet balances found.</td>
                </tr>
            @endforelse

        </tbody>

    </table>

</div>


        <!-- PAGINATION -->
        <div>

            <nav>

                {{ $members->links('pagination::bootstrap-5') }}

            </nav>

        </div>

    </div>


    

    


       </main>
@endsection
