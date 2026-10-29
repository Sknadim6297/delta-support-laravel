@extends('admin.layouts.app')

@section('title', 'Investment Entry')
@section('page-title', 'Investment Entry')

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

        <form method="POST" action="{{ route('admin.investments.store') }}">
            @csrf
        <div>

            <!-- INVESTMENT ID -->
            <div>
                <label>Investment ID</label>

                <input type="text"
                       id="investmentId"
                      name="investment_id"
                      value="{{ old('investment_id', $investmentId) }}"
                      readonly
                      
                       placeholder="Enter Investment ID">
            </div>


            <!-- MEMBER ID -->
            <div>
                <label>Member ID</label>

                <input type="text"
                      id="memberId"
                      name="member_id"
                      value="{{ old('member_id') }}"
                      data-error-for="member_id"
                      
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div>
                <label>Member Name</label>

                <input type="text"
                      id="memberName"
                      name="member_name"
                      value="{{ old('member_name') }}"
                      
                      placeholder="Enter Member Name"
                      readonly>
            </div>

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