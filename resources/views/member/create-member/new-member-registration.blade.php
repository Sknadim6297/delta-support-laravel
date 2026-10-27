@extends('member.layouts.app')

@section('content')
    <main>
        <div>
            @if(session('success'))
                <div>{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('member.registration.store') }}">
                @csrf

                <div>
                    <h4>Register New Member</h4>
                </div>

                <div>
                    <div>
                        <label>Member ID</label>
                        <input type="text" id="generatedMemberId" value="{{ $generated_member_id ?? '' }}" placeholder="Auto-generated Member ID" readonly>
                    </div>

                    <div>
                        <label>Member Name <span>*</span></label>
                        <input type="text" name="member_name" placeholder="Enter Member Name" value="{{ old('member_name') }}" required>
                        <div class="validation-message" data-error-for="member_name" hidden>
                            @error('member_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div>
                        <label>Sponsor ID <span>*</span></label>
                        <input type="text" name="sponsor_id" value="{{ $default_sponsor_id }}" readonly required>
                        <div class="validation-message" data-error-for="sponsor_id" hidden>
                            @error('sponsor_id'){{ $message }}@enderror
                        </div>
                    </div>

                    <div>
                        <label>Sponsor Name</label>
                        <input type="text" name="sponsor_name" value="{{ $default_sponsor_name }}" readonly>
                        <div class="validation-message" data-error-for="sponsor_name" hidden>
                            @error('sponsor_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div>
                        <label>Email ID <span>*</span></label>
                        <input type="email" name="email" placeholder="Enter Email ID" value="{{ old('email') }}" required>
                        <div class="validation-message" data-error-for="email" hidden>
                            @error('email'){{ $message }}@enderror
                        </div>
                    </div>

                    <div>
                        <label>Mobile No <span>*</span></label>
                        <input type="text" name="mobile_no" placeholder="Enter Mobile No" value="{{ old('mobile_no') }}" required maxlength="10">
                        <div class="validation-message" data-error-for="mobile_no" hidden>
                            @error('mobile_no'){{ $message }}@enderror
                        </div>
                    </div>

                </div>

                <div>
                    <button type="submit">
                        <i></i>
                        Register
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
        const registrationForm = document.querySelector('form');
        let sponsorValidationTimer = null;
        const touchedFields = new Set();
        let sponsorIdValid = true;
        let validatedSponsorId = (sponsorIdInput?.value || '').trim().toUpperCase();

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) {
                return;
            }

            target.textContent = message;
            target.hidden = !message;
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
                validatedSponsorId = '';
                return false;
            }

            if (sponsorIdValid && validatedSponsorId === sponsorId.toUpperCase()) {
                return true;
            }

            const currentRequest = Date.now();
            const requestId = currentRequest;
            sponsorValidationTimer = requestId;
            sponsorIdValid = false;

            setError('sponsor_id', '');

            fetch('{{ route('member.registration.check-sponsor') }}?sponsor_id=' + encodeURIComponent(sponsorId), {
                    headers: { 'Accept': 'application/json' }
                })
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
                        validatedSponsorId = sponsorId.toUpperCase();
                    } else {
                        if (sponsorNameInput) {
                            sponsorNameInput.value = '';
                        }
                        setError('sponsor_id', data.message || 'Invalid Sponsor ID.');
                        sponsorIdValid = false;
                        validatedSponsorId = '';
                    }
                })
                .catch(() => {
                    if (sponsorValidationTimer === requestId) {
                        setError('sponsor_id', 'Invalid Sponsor ID.');
                        sponsorIdValid = false;
                        validatedSponsorId = '';
                    }
                });

            return false;
        }

        function markAllFieldsTouched() {
            markTouched('member_name');
            markTouched('sponsor_id');
            markTouched('email');
            markTouched('mobile_no');
        }

        function validateAllFields() {
            const memberNameOk = validateMemberName(true);
            const sponsorId = sponsorIdInput.value.trim().toUpperCase();
            const sponsorIdOk = (sponsorIdValid && validatedSponsorId === sponsorId) || validateSponsorId(true);
            const emailOk = validateEmail(true);
            const mobileNoOk = validateMobileNo(true);

            return memberNameOk && sponsorIdOk && emailOk && mobileNoOk;
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