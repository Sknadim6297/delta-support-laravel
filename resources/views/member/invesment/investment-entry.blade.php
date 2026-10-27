@extends('member.layouts.app')

@section('content')
    <div>
        @include('partials.legacy-financial-lock')

        <!-- TITLE -->
        <div>
            <h4>Investment Entry</h4>
        </div>

        @if (session('success'))
            <div>{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div>{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('member.investments.store') }}">
            @csrf
        <div>

            <!-- INVESTMENT AMOUNT -->
            <div>
                <label>Investment Amount (USDT)</label>

                <input type="number"
                        id="investmentAmount"
                        name="amount"
                        value="{{ old('amount') }}"
                        data-error-for="amount"
                      
                       placeholder="Enter Investment Amount"
                       step="0.0001">
            </div>

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="submit"
                   
                    >

                <i></i>
                Submit

            </button>

            <button type="reset"
                   
                    >

                <i></i>
                Reset

            </button>

        </div>
        </form>

    </div>
@endsection

@section('scripts')
    <script>
        const investmentAmountInput = document.getElementById('investmentAmount');
        const investmentForm = document.querySelector('form');

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) {
                return;
            }

            let error = target.parentElement.querySelector('.validation-message');
            if (!error) {
                error = document.createElement('div');
                error.className = 'validation-message';
                target.parentElement.appendChild(error);
            }

            error.textContent = message;
            error.hidden = !message;
        }

        function validateInvestmentAmount(force = false) {
            const value = (investmentAmountInput.value || '').trim();

            if (!value) {
                setError('amount', force ? 'Investment Amount is required.' : '');
                return false;
            }

            if (!/^\d+(\.\d{1,4})?$/.test(value) || Number(value) < 100) {
                setError('amount', 'Investment Amount must be at least 100 USDT.');
                return false;
            }

            setError('amount', '');
            return true;
        }

        investmentAmountInput.addEventListener('input', function () {
            validateInvestmentAmount();
        });

        investmentAmountInput.addEventListener('blur', function () {
            validateInvestmentAmount(true);
        });

        investmentForm.addEventListener('submit', function (event) {
            const amountOk = validateInvestmentAmount(true);

            if (!amountOk) {
                event.preventDefault();
            }
        });
    </script>
@endsection