@extends('admin.layouts.app')

@section('title', 'Investment Entry')
@section('page-title', 'Investment Entry')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Create a stored investment for a member." />

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
            <form method="POST" action="{{ route('admin.investments.store') }}" class="ds-filter-form">
                @csrf
                <label>Investment ID
                    <input type="text" id="investmentId" name="investment_id" value="{{ old('investment_id', $investmentId) }}" readonly placeholder="Enter Investment ID">
                </label>
                <label>Member ID
                    <input type="text" id="memberId" name="member_id" value="{{ old('member_id') }}" data-error-for="member_id" placeholder="Enter Member ID">
                </label>
                <label>Member Name
                    <input type="text" id="memberName" name="member_name" value="{{ old('member_name') }}" placeholder="Enter Member Name" readonly>
                </label>
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
        const memberIdInput = document.getElementById('memberId');
        const memberNameInput = document.getElementById('memberName');
        const investmentAmountInput = document.getElementById('investmentAmount');
        const investmentForm = document.querySelector('form');
        let lookupTimer;

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

        function validateMemberId(force = false) {
            const value = memberIdInput.value.trim();

            if (!value) {
                setError('member_id', force ? 'Member ID is required.' : '');
                return false;
            }

            if (!/^ST\d{6}$/.test(value)) {
                setError('member_id', 'The selected member id is invalid.');
                return false;
            }

            setError('member_id', '');
            return true;
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

        memberIdInput.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            memberNameInput.value = '';
            validateMemberId();

            if (!memberIdInput.value.trim()) {
                return;
            }

            lookupTimer = setTimeout(function () {
                fetch('{{ route('admin.investments.member-lookup') }}?member_id=' + encodeURIComponent(memberIdInput.value.trim()), {
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'The selected member id is invalid.');
                        }
                        return data;
                    });
                }).then(function (member) {
                    memberNameInput.value = member.member_name;
                    setError('member_id', '');
                }).catch(function (error) {
                    memberNameInput.value = '';
                    setError('member_id', error.message || 'The selected member id is invalid.');
                });
            }, 250);
        });

        memberIdInput.addEventListener('blur', function () {
            validateMemberId(true);
        });

        investmentAmountInput.addEventListener('input', function () {
            validateInvestmentAmount();
        });

        investmentAmountInput.addEventListener('blur', function () {
            validateInvestmentAmount(true);
        });

        investmentForm.addEventListener('submit', function (event) {
            const memberOk = validateMemberId(true);
            const amountOk = validateInvestmentAmount(true);

            if (!memberOk || !amountOk) {
                event.preventDefault();
            }
        });
    </script>
@endsection