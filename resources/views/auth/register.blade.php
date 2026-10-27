@extends('auth.layout')

@section('title', 'Create Your Account')

@section('content')
    <div class="login-title">Create Your Account</div>
    <div class="login-subtitle">Please enter your account details</div>

    @if ($errors->any())
        <div class="auth-alert auth-alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('success'))
        <div class="auth-alert auth-alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('member.register.store') }}" id="registrationForm">
        @csrf

        <div class="input-group">
            <label>Member ID</label>
            <div class="input-box">
                <span>👤</span>
                <input type="text" value="{{ $generatedMemberId }}" readonly aria-readonly="true">
            </div>
        </div>

        <div class="input-group">
            <label>Member Name</label>
            <div class="input-box">
                <span>👤</span>
                <input type="text" name="member_name" value="{{ old('member_name') }}" placeholder="Enter Member Name" required>
            </div>
            <div class="validation-message" data-error-for="member_name">@error('member_name'){{ $message }}@enderror</div>
        </div>

        <div class="input-group">
            <label>Sponsor ID</label>
            <div class="input-box">
                <span>🔗</span>
                <input type="text" name="sponsor_id" value="{{ old('sponsor_id') }}" placeholder="Enter Sponsor ID" required>
            </div>
            <div class="validation-message" data-error-for="sponsor_id">@error('sponsor_id'){{ $message }}@enderror</div>
        </div>

        <div class="input-group">
            <label>Sponsor Name</label>
            <div class="input-box">
                <span>👥</span>
                <input type="text" value="{{ old('sponsor_name') }}" placeholder="Sponsor Name will auto-fill" readonly aria-readonly="true" id="sponsorName">
            </div>
        </div>

        <div class="input-group">
            <label>Mobile No.</label>
            <div class="input-box">
                <span>📱</span>
                <input type="text" name="mobile_no" value="{{ old('mobile_no') }}" placeholder="Enter Mobile No." maxlength="15" required>
            </div>
            <div class="validation-message" data-error-for="mobile_no">@error('mobile_no'){{ $message }}@enderror</div>
        </div>

        <div class="input-group">
            <label>Email ID</label>
            <div class="input-box">
                <span>✉️</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Enter Email ID" autocomplete="email" required>
            </div>
            <div class="validation-message" data-error-for="email">@error('email'){{ $message }}@enderror</div>
        </div>

        <button type="submit" class="login-btn">Sign Up</button>
    </form>

    <div class="login-footer">Do you already have an account? <a href="{{ route('login') }}">Sign In</a></div>
@endsection

@push('scripts')
<script>
    const sponsorId = document.querySelector('[name="sponsor_id"]');
    const sponsorName = document.getElementById('sponsorName');
    const form = document.getElementById('registrationForm');
    const sponsorError = document.querySelector('[data-error-for="sponsor_id"]');
    let sponsorValid = false;
    let validatedSponsor = '';
    let timer;

    function showSponsorError(message) {
        sponsorError.textContent = message || '';
    }

    async function lookupSponsor() {
        const value = sponsorId.value.trim().toUpperCase();
        sponsorId.value = value;
        sponsorValid = false;
        validatedSponsor = '';
        sponsorName.value = '';

        if (!value) {
            showSponsorError('Sponsor ID is required.');
            return;
        }

        try {
            const response = await fetch('{{ route('member.register.check-sponsor') }}?sponsor_id=' + encodeURIComponent(value), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (data.exists) {
                sponsorName.value = data.sponsor_name || '';
                sponsorValid = true;
                validatedSponsor = value;
                showSponsorError('');
            } else {
                showSponsorError(data.message || 'Invalid Sponsor ID.');
            }
        } catch (error) {
            showSponsorError('Unable to validate Sponsor ID.');
        }
    }

    sponsorId.addEventListener('input', function () {
        clearTimeout(timer);
        sponsorValid = false;
        timer = setTimeout(lookupSponsor, 350);
    });
    sponsorId.addEventListener('blur', lookupSponsor);

    form.addEventListener('submit', function (event) {
        if (!sponsorValid || validatedSponsor !== sponsorId.value.trim().toUpperCase()) {
            event.preventDefault();
            lookupSponsor();
            showSponsorError('Please enter a valid, existing Sponsor ID.');
        }
    });
</script>
@endpush
