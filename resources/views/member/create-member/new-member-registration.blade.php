@extends('member.layouts.app')
@section('title', 'Register New Member')
@section('page-title', 'Register New Member')

@section('content')
<x-members.shell kicker="My network" lead="You are the sponsor. The new account starts inactive. Registration does not purchase a package or create income.">
    @if ($receipt = session('registration_receipt'))
        <x-members.panel title="Registration successful" subtitle="Share the Member ID. The password is not shown or emailed.">
            <dl class="ds-detail">
                <div><dt>Member ID</dt><dd>{{ $receipt['member_id'] }}</dd></div>
                <div><dt>Member Name</dt><dd>{{ $receipt['member_name'] }}</dd></div>
                <div><dt>Sponsor ID</dt><dd>{{ $receipt['sponsor_id'] }}</dd></div>
                <div><dt>Account status</dt><dd>{{ $receipt['status'] }}</dd></div>
            </dl>
            <p class="ds-field-note">The new member can log in with this Member ID after setting a password. Package benefits stay locked until activation is verified.</p>
            @if(session('mail_notice'))
                <x-ui.alert type="{{ session('mail_accepted') ? 'success' : 'error' }}">{{ session('mail_notice') }}</x-ui.alert>
            @endif
            <form method="POST" action="{{ route('member.registration.password-setup-email') }}">
                @csrf
                <input type="hidden" name="member_id" value="{{ $receipt['member_id'] }}">
                <button type="submit" class="ds-btn">Resend password setup email</button>
            </form>
        </x-members.panel>
    @endif

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('member.registration.store') }}" class="ds-mm-form" id="memberRegistrationForm">
        @csrf
        <x-members.panel title="Account" subtitle="Preview only. The saved Member ID is assigned when you submit.">
            <div class="ds-mm-fields">
                <div>
                    <label for="generatedMemberId">Member ID</label>
                    <input type="text" id="generatedMemberId" value="{{ $generated_member_id ?? '' }}" placeholder="Auto-generated Member ID" readonly aria-readonly="true">
                </div>
                <div>
                    <label for="member_name">Member Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="member_name" name="member_name" placeholder="Enter Member Name" value="{{ old('member_name') }}" maxlength="100" autocomplete="name" required>
                    <div class="validation-message" data-error-for="member_name" hidden>@error('member_name'){{ $message }}@enderror</div>
                </div>
            </div>
        </x-members.panel>

        <x-members.panel title="Sponsor information" subtitle="Your Member ID is locked as the sponsor.">
            <div class="ds-mm-fields">
                <div>
                    <label for="sponsor_id">Sponsor ID <span aria-hidden="true">*</span></label>
                    <input type="text" id="sponsor_id" name="sponsor_id" value="{{ $default_sponsor_id }}" readonly aria-readonly="true" required>
                    <div class="validation-message" data-error-for="sponsor_id" hidden>@error('sponsor_id'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="sponsor_name">Sponsor Name</label>
                    <input type="text" id="sponsor_name" value="{{ $default_sponsor_name }}" readonly aria-readonly="true">
                </div>
            </div>
        </x-members.panel>

        <x-members.panel title="Contact information">
            <div class="ds-mm-fields">
                <div>
                    <label for="email">Email ID <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" placeholder="Enter Email ID" value="{{ old('email') }}" autocomplete="email" required>
                    <div class="validation-message" data-error-for="email" hidden>@error('email'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="mobile_no">Mobile No <span aria-hidden="true">*</span></label>
                    <input type="text" id="mobile_no" name="mobile_no" placeholder="Enter Mobile No" value="{{ old('mobile_no') }}" required maxlength="10" inputmode="numeric" autocomplete="tel">
                    <div class="validation-message" data-error-for="mobile_no" hidden>@error('mobile_no'){{ $message }}@enderror</div>
                </div>
            </div>
        </x-members.panel>

        <x-members.panel title="KYC and wallet">
            <div class="ds-mm-fields">
                <div>
                    <label for="pan_card_no">PAN Card Number <span aria-hidden="true">*</span></label>
                    <input type="text" id="pan_card_no" name="pan_card_no" placeholder="Enter PAN Card Number" value="{{ old('pan_card_no') }}" required maxlength="10" autocapitalize="characters">
                    <div class="validation-message" data-error-for="pan_card_no" hidden>@error('pan_card_no'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="wallet_address">Wallet Address</label>
                    <input type="text" id="wallet_address" name="wallet_address" placeholder="Optional wallet address" value="{{ old('wallet_address') }}" maxlength="255">
                    <div class="validation-message" data-error-for="wallet_address" hidden>@error('wallet_address'){{ $message }}@enderror</div>
                </div>
            </div>
        </x-members.panel>

        <x-members.panel title="Security" subtitle="A one-time password setup link is emailed to the member. The email does not contain a password.">
            <div class="ds-mm-fields">
                <div>
                    <label for="registrationPassword">Password</label>
                    <input type="password" id="registrationPassword" name="password" placeholder="Optional. It is not emailed." autocomplete="new-password">
                    <div class="validation-message" data-error-for="password" hidden>@error('password'){{ $message }}@enderror</div>
                </div>
                <div>
                    <label for="registrationPasswordConfirmation">Confirm Password</label>
                    <input type="password" id="registrationPasswordConfirmation" name="password_confirmation" placeholder="Confirm password" autocomplete="new-password">
                </div>
            </div>
            <div class="ds-mm-actions">
                <button type="submit" class="ds-btn">Register Member</button>
            </div>
        </x-members.panel>
    </form>
</x-members.shell>

<script>
    const lockedSponsorId = @json($default_sponsor_id);
    const memberNameInput = document.querySelector('input[name="member_name"]');
    const sponsorIdInput = document.querySelector('input[name="sponsor_id"]');
    const emailInput = document.querySelector('input[name="email"]');
    const mobileNoInput = document.querySelector('input[name="mobile_no"]');
    const panCardInput = document.querySelector('input[name="pan_card_no"]');
    const passwordInput = document.getElementById('registrationPassword');
    const passwordConfirmationInput = document.getElementById('registrationPasswordConfirmation');
    const registrationForm = document.getElementById('memberRegistrationForm');
    const touchedFields = new Set();
    const memberNamePattern = /^[\p{L}\p{M}][\p{L}\p{M} .'\u2019-]*$/u;

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
            setError('member_name', shouldShowRequired('member_name', force) ? 'Member name is required.' : '');
            return false;
        }

        if (value.length < 3) {
            setError('member_name', 'Member name must contain at least 3 characters.');
            return false;
        }

        if (value.length > 100) {
            setError('member_name', 'Member name must be 100 characters or fewer.');
            return false;
        }

        if (!memberNamePattern.test(value)) {
            setError('member_name', 'Enter a valid member name.');
            return false;
        }

        setError('member_name', '');
        return true;
    }

    function validateEmail(force = false) {
        const value = (emailInput?.value || '').trim();

        if (!value) {
            setError('email', shouldShowRequired('email', force) ? 'Email ID is required.' : '');
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
            setError('mobile_no', shouldShowRequired('mobile_no', force) ? 'Mobile No is required.' : '');
            return false;
        }

        if (!/^[0-9]+$/.test(value) || value.length !== 10) {
            setError('mobile_no', 'Mobile No must be exactly 10 digits.');
            return false;
        }

        setError('mobile_no', '');
        return true;
    }

    function validatePanCard(force = false) {
        const value = (panCardInput?.value || '').trim();

        if (!value) {
            setError('pan_card_no', shouldShowRequired('pan_card_no', force) ? 'PAN Card Number is required.' : '');
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
        const sponsorId = (sponsorIdInput?.value || '').trim().toUpperCase();

        if (!sponsorId) {
            setError('sponsor_id', shouldShowRequired('sponsor_id', force) ? 'Sponsor ID is required.' : '');
            return false;
        }

        if (sponsorId !== String(lockedSponsorId).toUpperCase()) {
            setError('sponsor_id', 'Unauthorized sponsor ID.');
            return false;
        }

        setError('sponsor_id', '');
        return true;
    }

    function validatePassword(force = false) {
        const password = passwordInput?.value || '';
        const confirmation = passwordConfirmationInput?.value || '';

        if (!password && !confirmation) {
            setError('password', '');
            return true;
        }

        if (password.length < 6) {
            setError('password', 'Password must be at least 6 characters.');
            return false;
        }

        if (password !== confirmation) {
            setError('password', 'Password confirmation does not match.');
            return false;
        }

        setError('password', '');
        return true;
    }

    function validateAllFields() {
        return validateMemberName(true)
            && validateSponsorId(true)
            && validateEmail(true)
            && validateMobileNo(true)
            && validatePanCard(true)
            && validatePassword(true);
    }

    memberNameInput?.addEventListener('input', function () {
        this.value = this.value.replace(/^\s+/, '');
        markTouched('member_name');
        validateMemberName();
    });
    memberNameInput?.addEventListener('blur', function () {
        this.value = this.value.trim();
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
        this.value = this.value.toUpperCase().replace(/\s+/g, '').slice(0, 10);
        markTouched('pan_card_no');
        validatePanCard();
    });
    panCardInput?.addEventListener('blur', function () {
        markTouched('pan_card_no');
        validatePanCard(true);
    });

    passwordInput?.addEventListener('input', function () {
        markTouched('password');
        validatePassword();
    });
    passwordConfirmationInput?.addEventListener('input', function () {
        markTouched('password');
        validatePassword();
    });

    registrationForm?.addEventListener('submit', function (event) {
        ['member_name', 'sponsor_id', 'email', 'mobile_no', 'pan_card_no', 'password'].forEach(markTouched);
        if (!validateAllFields()) {
            event.preventDefault();
        }
    });
</script>
@endsection
