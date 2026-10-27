@extends('admin.layouts.app')

@section('content')
    <div class="form-card">
        @include('partials.legacy-financial-lock')

        <!-- TITLE -->
        <div class="form-title">
            <h4>Investment Entry</h4>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.investments.store') }}">
            @csrf
        <div class="row">

            <!-- INVESTMENT ID -->
            <div class="col-md-6 mb-3">
                <label>Investment ID</label>

                <input type="text"
                       id="investmentId"
                      name="investment_id"
                      value="{{ old('investment_id', $investmentId) }}"
                      readonly
                       class="form-control"
                       placeholder="Enter Investment ID">
            </div>


            <!-- MEMBER ID -->
            <div class="col-md-6 mb-3">
                <label>Member ID</label>

                <input type="text"
                      id="memberId"
                      name="member_id"
                      value="{{ old('member_id') }}"
                      data-error-for="member_id"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div class="col-md-6 mb-3">
                <label>Member Name</label>

                <input type="text"
                      id="memberName"
                      name="member_name"
                      value="{{ old('member_name') }}"
                       class="form-control"
                      placeholder="Enter Member Name"
                      readonly>
            </div>

            <!-- INVESTMENT AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>Investment Amount (USDT)</label>

                <input type="number"
                        id="investmentAmount"
                        name="amount"
                        value="{{ old('amount') }}"
                        data-error-for="amount"
                       class="form-control"
                       placeholder="Enter Investment Amount"
                       step="0.0001">
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="text-end mt-2">

            <button type="submit"
                    class="btn btn-primary px-4"
                    >

                <i class="bi bi-check-circle"></i>
                Submit

            </button>

            <button type="reset"
                    class="btn btn-primary px-4 ms-2"
                    >

                <i class="bi bi-arrow-counterclockwise"></i>
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
                error.className = 'text-danger mt-1 small validation-message';
                target.parentElement.appendChild(error);
            }

            error.textContent = message;
            error.style.display = message ? 'block' : 'none';
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