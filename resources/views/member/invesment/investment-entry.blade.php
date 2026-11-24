@extends('member.layouts.app')

@section('title', 'Investment Entry')
@section('page-title', 'Investment Entry')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Submit a new investment amount for your account." />

    @include('partials.legacy-financial-lock')

    @if (session('success'))
        <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Investment Entry</h2>
        </header>
        <div class="ds-report-card">
            <form method="POST" action="{{ route('member.investments.store') }}" class="ds-filter-form">
                @csrf
                <label>Investment Amount (USDT)
                    <input type="number" id="investmentAmount" name="amount" value="{{ old('amount') }}" data-error-for="amount" placeholder="Enter Investment Amount" step="0.0001">
                </label>
                <button class="ds-report-btn ds-report-btn-blue" type="submit"><i></i> Submit</button>
                <button class="ds-report-btn ds-report-btn-amber" type="reset"><i></i> Reset</button>
            </form>
        </div>
    </section>
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