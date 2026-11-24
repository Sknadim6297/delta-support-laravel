@extends('admin.layouts.app')

@section('title', 'Activation Credit')
@section('page-title', 'Activation Credit')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Transfer from the working wallet to a member activation wallet." />

    @include('partials.legacy-financial-lock')

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
    @endif

    <section class="ds-report-block">
        <header class="ds-dash-head">
            <h2>Transfer to Member Activation Wallet</h2>
        </header>
        <div class="ds-report-card">
            <form method="POST" action="{{ route('admin.activation-wallet.store-credit-entry') }}" id="activationWalletForm" class="ds-filter-form">
                @csrf
                <label>Member ID
                    <input type="text" id="memberId" name="member_id" data-error-for="member_id" placeholder="Enter Member ID">
                </label>
                <label>Member Name
                    <input type="text" id="memberName" name="member_name" placeholder="Enter Member Name" readonly>
                </label>
                <label>Activation Wallet Amount (USDT)
                    <input type="text" id="walletAmount" name="wallet_amount" placeholder="Activation Wallet Amount" readonly>
                </label>
                <label>Amount Want to Transfer (USDT)
                    <input type="number" id="transferAmount" name="amount" data-error-for="amount" placeholder="Enter Amount">
                </label>
                <button type="button" id="fetchMemberDetails" class="ds-report-btn ds-report-btn-blue"><i></i> Submit</button>
                <button type="reset" id="resetActivationWalletForm" class="ds-report-btn ds-report-btn-amber"><i></i> Reset</button>
            </form>
        </div>
    </section>
</div>
@endsection

@section('scripts')
    <script>
        const activationWalletForm = document.getElementById('activationWalletForm');
        const memberIdInput = document.getElementById('memberId');
        const memberNameInput = document.getElementById('memberName');
        const walletAmountInput = document.getElementById('walletAmount');
        const transferAmountInput = document.getElementById('transferAmount');
        const fetchMemberDetailsButton = document.getElementById('fetchMemberDetails');
        const resetButton = document.getElementById('resetActivationWalletForm');
        const actionButton = fetchMemberDetailsButton;
        let fetchedMemberId = null;
        let workingWalletBalance = null;
        let memberLookupTimer = null;
        let isSubmitting = false;

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

        function formatAmount(value) {
            return (Number(value) || 0).toFixed(4).replace(/\.?0+$/, '') || '0';
        }

        function clearMemberDetails() {
            fetchedMemberId = null;
            workingWalletBalance = null;
            memberNameInput.value = '';
            walletAmountInput.value = '';
            actionButton.type = 'button';
            actionButton.disabled = false;
            actionButton.innerHTML = '<i></i> Submit';
        }

        function isIncompleteMemberId(value) {
            return /^ST?\d{0,6}$/.test(value) && value.length < 8;
        }

        function validateMemberId(force = false) {
            const value = memberIdInput.value.trim();

            if (!value) {
                setError('member_id', force ? 'Member ID is required.' : '');
                return false;
            }

            if (!force && isIncompleteMemberId(value)) {
                setError('member_id', '');
                return false;
            }

            if (!/^ST\d{6}$/.test(value)) {
                setError('member_id', 'The selected member id is invalid.');
                return false;
            }

            setError('member_id', '');
            return true;
        }

        function isCompleteAmount(value) {
            return /^\d+(\.\d{1,4})?$/.test(value) && Number(value) > 0;
        }

        function validateTransferAmount(force = false) {
            const value = transferAmountInput.value.trim();

            if (!value) {
                setError('amount', force ? 'Transfer amount is required.' : '');
                return false;
            }

            if (!force && /^\d+\.?$/.test(value)) {
                setError('amount', '');
                return false;
            }

            if (!/^\d+(\.\d{1,4})?$/.test(value) || Number(value) <= 0) {
                setError('amount', 'Enter a valid transfer amount greater than 0.');
                return false;
            }

            if (workingWalletBalance !== null && Number(value) > Number(workingWalletBalance)) {
                setError('amount', 'Insufficient Working Wallet balance.');
                return false;
            }

            setError('amount', '');
            return isCompleteAmount(value);
        }

        function lookupMember() {
            const value = memberIdInput.value.trim();
            if (!value || !/^ST\d{6}$/.test(value)) {
                return;
            }

            clearTimeout(memberLookupTimer);
            memberLookupTimer = setTimeout(function () {
                fetch('{{ route('admin.activation-wallet.credit-entry.member-lookup') }}?member_id=' + encodeURIComponent(value), {
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
                    walletAmountInput.value = formatAmount(member.activation_wallet_amount);
                    fetchedMemberId = member.member_id;
                    workingWalletBalance = member.working_wallet_amount;
                    actionButton.type = 'submit';
                    actionButton.innerHTML = '<i></i> Transfer';
                    setError('member_id', '');
                    validateTransferAmount();
                }).catch(function (error) {
                    clearMemberDetails();
                    setError('member_id', error.message || 'The selected member id is invalid.');
                });
            }, 250);
        }

        memberIdInput.addEventListener('input', function () {
            clearMemberDetails();
            validateMemberId();
            lookupMember();
        });

        memberIdInput.addEventListener('blur', function () {
            validateMemberId(true);
        });

        transferAmountInput.addEventListener('input', function () {
            validateTransferAmount();
        });

        transferAmountInput.addEventListener('blur', function () {
            validateTransferAmount(true);
        });

        fetchMemberDetailsButton.addEventListener('click', function (event) {
            if (fetchedMemberId && fetchedMemberId === memberIdInput.value.trim()) {
                return;
            }

            event.preventDefault();
            clearMemberDetails();
            if (!validateMemberId(true)) {
                return;
            }

            lookupMember();
        });

        resetButton.addEventListener('click', function () {
            activationWalletForm.reset();
            clearMemberDetails();
            setError('member_id', '');
            setError('amount', '');
        });

        activationWalletForm.addEventListener('submit', function (event) {
            if (isSubmitting) {
                event.preventDefault();
                return;
            }

            if (!fetchedMemberId || fetchedMemberId !== memberIdInput.value.trim() || !validateTransferAmount(true)) {
                event.preventDefault();
                if (!fetchedMemberId) {
                    validateMemberId(true);
                }
                return;
            }

            if (!validateMemberId(true)) {
                event.preventDefault();
                return;
            }

            isSubmitting = true;
            actionButton.disabled = true;
        });
    </script>
@endsection