@extends('admin.layouts.app')

@section('content')
    <main class="member-content">
        <div class="form-card">
            @if(session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.members.store') }}">
                @csrf

                <div class="form-title">
                    <h4>Add Member</h4>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Member ID</label>
                        <input type="text" id="generatedMemberId" class="form-control" value="{{ old('generated_member_id', $generated_member_id ?? '') }}" placeholder="Auto-generated Member ID" readonly>
                        <input type="hidden" name="generated_member_id" value="{{ old('generated_member_id', $generated_member_id ?? '') }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Member Name <span class="text-danger">*</span></label>
                        <input type="text" name="member_name" class="form-control" placeholder="Enter Member Name" value="{{ old('member_name') }}" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="member_name">
                            @error('member_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Sponsor ID <span class="text-danger">*</span></label>
                        <input type="text" name="sponsor_id" class="form-control" placeholder="Enter Sponsor ID" value="{{ old('sponsor_id', $default_sponsor_id ?? 'ST666666') }}" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="sponsor_id">
                            @error('sponsor_id'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Sponsor Name <span class="text-danger">*</span></label>
                        <input type="text" name="sponsor_name" class="form-control" placeholder="Sponsor Name will auto-fill" value="{{ old('sponsor_name', $default_sponsor_name ?? 'Admin') }}" readonly>
                        <div class="text-danger mt-1 small validation-message" data-error-for="sponsor_name">
                            @error('sponsor_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Email ID <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="Enter Email ID" value="{{ old('email') }}" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="email">
                            @error('email'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Mobile No <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_no" class="form-control" placeholder="Enter Mobile No" value="{{ old('mobile_no') }}" required maxlength="10">
                        <div class="text-danger mt-1 small validation-message" data-error-for="mobile_no">
                            @error('mobile_no'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>PAN Card Number <span class="text-danger">*</span></label>
                        <input type="text" name="pan_card_no" class="form-control" placeholder="Enter PAN Card Number" value="{{ old('pan_card_no') }}" required maxlength="10" style="text-transform: uppercase;">
                        <div class="text-danger mt-1 small validation-message" data-error-for="pan_card_no">
                            @error('pan_card_no'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Wallet Address</label>
                        <input type="text" name="wallet_address" class="form-control" placeholder="Optional wallet address" value="{{ old('wallet_address') }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Optional password" autocomplete="new-password">
                        <div class="text-danger mt-1 small validation-message" data-error-for="password">
                            @error('password'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm password" autocomplete="new-password">
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-person-plus"></i>
                        Add Member
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const sponsorIdInput = document.querySelector('input[name="sponsor_id"]');
        const sponsorNameInput = document.querySelector('input[name="sponsor_name"]');
        const memberNameInput = document.querySelector('input[name="member_name"]');
        const emailInput = document.querySelector('input[name="email"]');
        const mobileNoInput = document.querySelector('input[name="mobile_no"]');
        const panCardInput = document.querySelector('input[name="pan_card_no"]');
        const registrationForm = document.querySelector('form');
        let sponsorValidationTimer = null;
        const touchedFields = new Set();
        let sponsorIdValid = (sponsorIdInput?.value || '').trim().toUpperCase() === 'ST666666';

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) {
                return;
            }

            target.textContent = message;
            target.style.display = message ? 'block' : 'none';
        }

        function markTouched(fieldName) {
            touchedFields.add(fieldName);
        }

        function shouldShowRequired(fieldName, force = false) {
            return force || touchedFields.has(fieldName);
        }

        function validateMemberName(force = false) {
            const value = (memberNameInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('member_name', force)) {
                    setError('member_name', 'Member name is required.');
                } else {
                    setError('member_name', '');
                }
                return false;
            }

            if (value.length < 3) {
                setError('member_name', 'Member name must contain at least 3 characters.');
                return false;
            }

            if (!/^[A-Za-z ]+$/.test(value)) {
                setError('member_name', 'Member name can contain only letters and spaces.');
                return false;
            }

            setError('member_name', '');
            return true;
        }

        function validateEmail(force = false) {
            const value = (emailInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('email', force)) {
                    setError('email', 'Email ID is required.');
                } else {
                    setError('email', '');
                }
                return false;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                setError('email', 'Please enter a valid email address.');
                return false;
            }

            setError('email', '');
            return true;
        }

        function validateMobileNo(force = false) {
            const value = (mobileNoInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('mobile_no', force)) {
                    setError('mobile_no', 'Mobile No is required.');
                } else {
                    setError('mobile_no', '');
                }
                return false;
            }

            if (!/^[0-9]+$/.test(value)) {
                setError('mobile_no', 'Please enter a valid mobile number.');
                return false;
            }

            if (value.length !== 10) {
                setError('mobile_no', 'Mobile No must be exactly 10 digits.');
                return false;
            }

            setError('mobile_no', '');
            return true;
        }

        function validatePanCard(force = false) {
            const value = (panCardInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('pan_card_no', force)) {
                    setError('pan_card_no', 'PAN Card Number is required.');
                } else {
                    setError('pan_card_no', '');
                }
                return false;
            }

            if (value.length !== 10) {
                setError('pan_card_no', 'PAN Card Number must be exactly 10 characters.');
                return false;
            }

            setError('pan_card_no', '');
            return true;
        }

        function validateSponsorId(force = false) {
            const sponsorId = (sponsorIdInput?.value || '').trim();

            if (!sponsorId) {
                if (shouldShowRequired('sponsor_id', force)) {
                    setError('sponsor_id', 'Sponsor ID is required.');
                } else {
                    setError('sponsor_id', '');
                }
                if (sponsorNameInput) {
                    sponsorNameInput.value = '';
                }
                sponsorIdValid = false;
                return false;
            }

            if (sponsorId.toUpperCase() === 'ST666666') {
                if (sponsorNameInput) {
                    sponsorNameInput.value = 'Admin';
                }
                setError('sponsor_id', '');
                sponsorIdValid = true;
                return true;
            }

            const currentRequest = Date.now();
            const requestId = currentRequest;
            sponsorValidationTimer = requestId;
            sponsorIdValid = false;

            setError('sponsor_id', '');

            fetch('/admin/members/check-sponsor-id?sponsor_id=' + encodeURIComponent(sponsorId))
                .then(response => response.json())
                .then(data => {
                    if (sponsorValidationTimer !== requestId) {
                        return;
                    }

                    if (data.exists) {
                        if (sponsorNameInput) {
                            sponsorNameInput.value = data.sponsor_name || '';
                        }
                        setError('sponsor_id', '');
                        sponsorIdValid = true;
                    } else {
                        if (sponsorNameInput) {
                            sponsorNameInput.value = '';
                        }
                        setError('sponsor_id', data.message || 'Invalid Sponsor ID.');
                        sponsorIdValid = false;
                    }
                })
                .catch(() => {
                    if (sponsorValidationTimer === requestId) {
                        setError('sponsor_id', 'Invalid Sponsor ID.');
                        sponsorIdValid = false;
                    }
                });

            return false;
        }

        function markAllFieldsTouched() {
            markTouched('member_name');
            markTouched('sponsor_id');
            markTouched('email');
            markTouched('mobile_no');
            markTouched('pan_card_no');
        }

        function validateAllFields() {
            const memberNameOk = validateMemberName(true);
            const sponsorIdOk = validateSponsorId(true) && sponsorIdValid;
            const emailOk = validateEmail(true);
            const mobileNoOk = validateMobileNo(true);
            const panCardOk = validatePanCard(true);

            return memberNameOk && sponsorIdOk && emailOk && mobileNoOk && panCardOk;
        }

        memberNameInput?.addEventListener('input', function () {
            const value = this.value;
            if (value !== value.trim()) {
                this.value = value.trim();
            }
            markTouched('member_name');
            validateMemberName();
        });
        memberNameInput?.addEventListener('blur', function () {
            markTouched('member_name');
            validateMemberName(true);
        });

        emailInput?.addEventListener('input', function () {
            markTouched('email');
            validateEmail();
        });
        emailInput?.addEventListener('blur', function () {
            markTouched('email');
            validateEmail(true);
        });

        mobileNoInput?.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            markTouched('mobile_no');
            validateMobileNo();
        });
        mobileNoInput?.addEventListener('blur', function () {
            markTouched('mobile_no');
            validateMobileNo(true);
        });

        panCardInput?.addEventListener('input', function () {
            this.value = this.value.slice(0, 10);
            markTouched('pan_card_no');
            validatePanCard();
        });
        panCardInput?.addEventListener('blur', function () {
            markTouched('pan_card_no');
            validatePanCard(true);
        });

        sponsorIdInput?.addEventListener('input', function () {
            const value = this.value;
            if (value !== value.trim()) {
                this.value = value.trim();
            }
            markTouched('sponsor_id');
            clearTimeout(sponsorValidationTimer);
            if (!this.value.trim()) {
                validateSponsorId();
                return;
            }
            sponsorValidationTimer = setTimeout(() => validateSponsorId(), 400);
        });
        sponsorIdInput?.addEventListener('blur', function () {
            markTouched('sponsor_id');
            validateSponsorId(true);
        });

        registrationForm?.addEventListener('submit', function (event) {
            markAllFieldsTouched();
            const isValid = validateAllFields();

            if (!isValid) {
                event.preventDefault();
                if (!sponsorIdValid) {
                    setError('sponsor_id', 'Please enter a valid, existing Sponsor ID.');
                }
            }
        });
    </script>
@endsection