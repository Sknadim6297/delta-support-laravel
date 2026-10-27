@extends('admin.layouts.app')

@section('content')
        <main class="dashboard-content">

    
           
           

    <div class="form-card">
        @include('partials.legacy-financial-lock')

        @if (session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.investments.investment-withdrawal-store') }}" id="investmentWithdrawalForm">
            @csrf

        <div class="row">

            <div class="col-md-6 mb-3">
                <label>Member ID</label>
                <input type="text" id="withdrawalMemberId" name="member_id" value="{{ old('member_id') }}" class="form-control" placeholder="Enter Member ID" required data-member-autocomplete autocomplete="off">
                <div class="text-danger mt-1 small validation-message" data-error-for="member_id"></div>
            </div>

            <div class="col-md-6 mb-3">
                <label>Member Name</label>
                <input type="text" id="withdrawalMemberName" name="member_name" value="{{ old('member_name') }}" class="form-control" placeholder="Enter Member Name" readonly>
            </div>

            <div class="col-md-6 mb-3">
                <label>Investment ID</label>
                <input type="text" id="withdrawalInvestmentId" name="investment_id" value="{{ old('investment_id') }}" class="form-control" placeholder="Enter Sponsor ID" required>
                <div class="text-danger mt-1 small validation-message" data-error-for="investment_id"></div>
            </div>

          
            <div class="col-md-6 mb-3">
                <label>Investment Amount (USDT)</label>
                <input type="number" id="withdrawalAmount" name="amount" value="{{ old('amount') }}" class="form-control" placeholder="Enter Amount" min="0.0001" step="0.0001" required>
                <div class="text-danger mt-1 small validation-message" data-error-for="amount"></div>
            </div>

        </div>

        <div class="text-end mt-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-circle"></i>
                Submit
            </button>
        </div>
        </form>

    </div>


    

    


       </main>
@endsection

@section('scripts')
    <script>
        const withdrawalMemberId = document.getElementById('withdrawalMemberId');
        const withdrawalMemberName = document.getElementById('withdrawalMemberName');
        const withdrawalInvestmentId = document.getElementById('withdrawalInvestmentId');
        const withdrawalAmount = document.getElementById('withdrawalAmount');
        const withdrawalForm = document.getElementById('investmentWithdrawalForm');
        let lookupTimer = null;
        let lookupRequestId = 0;
        let memberValid = false;
        let investmentValid = false;
        let relationshipValid = false;
        let availableAmount = null;

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) {
                return;
            }

            target.textContent = message;
            target.style.display = message ? 'block' : 'none';
        }

        function clearInvestmentData() {
            withdrawalAmount.value = '';
            withdrawalAmount.removeAttribute('max');
            availableAmount = null;
            investmentValid = false;
            relationshipValid = false;
        }

        function validateAmount(force = false) {
            const value = withdrawalAmount.value.trim();

            if (!value) {
                setError('amount', force ? 'Withdrawal amount is required.' : '');
                return false;
            }

            if (!/^\d+(\.\d{1,4})?$/.test(value) || Number(value) <= 0) {
                setError('amount', 'Withdrawal amount must be greater than 0.');
                return false;
            }

            if (availableAmount !== null && Number(value) > Number(availableAmount)) {
                setError('amount', 'Withdrawal amount exceeds the available amount.');
                return false;
            }

            setError('amount');
            return true;
        }

        function lookupMember() {
            const memberId = withdrawalMemberId.value.trim();
            const requestId = ++lookupRequestId;
            memberValid = false;
            relationshipValid = false;
            withdrawalMemberName.value = '';

            if (!memberId) {
                setError('member_id');
                if (withdrawalInvestmentId.value.trim()) {
                    clearInvestmentData();
                }
                return;
            }

            if (!/^ST\d{6}$/.test(memberId)) {
                setError('member_id', 'The selected member id is invalid.');
                clearInvestmentData();
                return;
            }

            fetch('{{ route('member.lookup-member') }}?member_id=' + encodeURIComponent(memberId), {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'The selected member id is invalid.');
                    }
                    return data;
                });
            }).then(function (member) {
                if (requestId !== lookupRequestId) return;
                withdrawalMemberName.value = member.member_name;
                memberValid = true;
                setError('member_id');
                if (withdrawalInvestmentId.value.trim()) {
                    validateRelationship();
                }
            }).catch(function (error) {
                if (requestId !== lookupRequestId) return;
                withdrawalMemberName.value = '';
                setError('member_id', error.message || 'The selected member id is invalid.');
                clearInvestmentData();
            });
        }

        function lookupInvestment() {
            const investmentId = withdrawalInvestmentId.value.trim();
            const requestId = ++lookupRequestId;
            investmentValid = false;
            relationshipValid = false;
            clearInvestmentData();

            if (!investmentId) {
                setError('investment_id');
                return;
            }

            fetch('{{ route('admin.investments.investment-withdrawal-investment-lookup') }}?investment_id=' + encodeURIComponent(investmentId), {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'The selected investment id is invalid.');
                    }
                    return data;
                });
            }).then(function (investment) {
                if (requestId !== lookupRequestId) return;
                withdrawalAmount.value = investment.investment_amount;
                availableAmount = investment.investment_amount;
                withdrawalAmount.max = availableAmount;
                investmentValid = true;
                setError('investment_id');
                validateAmount();
                if (memberValid) {
                    validateRelationship();
                }
            }).catch(function (error) {
                if (requestId !== lookupRequestId) return;
                clearInvestmentData();
                setError('investment_id', error.message || 'The selected investment id is invalid.');
            });
        }

        function validateRelationship() {
            const memberId = withdrawalMemberId.value.trim();
            const investmentId = withdrawalInvestmentId.value.trim();

            if (!memberValid || !investmentValid || !memberId || !investmentId) {
                return;
            }

            fetch('{{ route('admin.investments.investment-withdrawal-lookup') }}?member_id=' + encodeURIComponent(memberId) + '&investment_id=' + encodeURIComponent(investmentId), {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.eligible) {
                        throw new Error(data.message || 'The investment is not eligible for withdrawal.');
                    }
                    return data;
                });
            }).then(function (data) {
                relationshipValid = true;
                availableAmount = data.available_amount;
                withdrawalAmount.max = availableAmount;
                setError('member_id');
                setError('investment_id');
                validateAmount();
            }).catch(function (error) {
                relationshipValid = false;
                clearInvestmentData();
                setError('investment_id', error.message || 'The investment does not belong to the selected member.');
            });
        }

        withdrawalMemberId.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(lookupMember, 250);
        });
        withdrawalInvestmentId.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(lookupInvestment, 250);
        });
        withdrawalMemberId.addEventListener('blur', lookupMember);
        withdrawalInvestmentId.addEventListener('blur', lookupInvestment);
        withdrawalAmount.addEventListener('input', function () {
            validateAmount();
        });
        withdrawalAmount.addEventListener('blur', function () {
            validateAmount(true);
        });
        withdrawalForm.addEventListener('submit', function (event) {
            const valid = memberValid && investmentValid && relationshipValid && validateAmount(true);
            if (!valid) {
                event.preventDefault();
                if (!memberValid) setError('member_id', 'Please enter a valid Member ID.');
                if (!investmentValid || !relationshipValid) setError('investment_id', 'Please enter an eligible investment belonging to this member.');
            }
        });
    </script>
@endsection