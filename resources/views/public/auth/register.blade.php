@extends('public.layout')

@section('title', 'Member Registration | Delta Support')

@push('styles')
    @vite(['resources/css/register.css'])
@endpush

@section('content')
    <div class="register-wrapper">
        <div class="register-card">
            <div class="register-logo">
                <a href="{{ route('home') }}">
                    <img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support Logo">
                </a>
            </div>

            <h1 class="register-heading">Create Your Account</h1>
            <p class="register-subtitle">Join Delta Support and create your member account.</p>

            @if (session('success'))
                <div class="form-message mb-3" role="status">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.register.store') }}" id="registrationForm">
                @csrf
                <div class="section-label">PERSONAL INFORMATION</div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="fullName" class="form-label">Full Name *</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="fullName" name="member_name" value="{{ old('member_name') }}" placeholder="Enter your full name" autocomplete="name" required>
                        </div>
                        <div class="form-message text-red-600" data-error-for="member_name">@error('member_name'){{ $message }}@enderror</div>
                    </div>
                    <div>
                        <label for="email" class="form-label">Email Address *</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="Enter email address" autocomplete="email" required>
                        </div>
                        <div class="form-message text-red-600" data-error-for="email">@error('email'){{ $message }}@enderror</div>
                    </div>
                    <div>
                        <label for="mobile" class="form-label">Mobile Number *</label>
                        <div class="input-wrap">
                            <i class="bi bi-phone input-icon"></i>
                            <input type="tel" class="form-control" id="mobile" name="mobile_no" value="{{ old('mobile_no') }}" placeholder="Enter mobile number" autocomplete="tel" required>
                        </div>
                        <div class="form-message text-red-600" data-error-for="mobile_no">@error('mobile_no'){{ $message }}@enderror</div>
                    </div>
                    <div>
                        <label for="sponsorId" class="form-label">Sponsor ID *</label>
                        <div class="input-wrap">
                            <i class="bi bi-diagram-3 input-icon"></i>
                            <input type="text" class="form-control" id="sponsorId" name="sponsor_id" value="{{ old('sponsor_id', request('sponsor_id')) }}" placeholder="Enter sponsor ID" required>
                        </div>
                        <div class="form-message text-red-600" data-error-for="sponsor_id">@error('sponsor_id'){{ $message }}@enderror</div>
                    </div>
                    <div>
                        <label for="sponsorName" class="form-label">Sponsor Name</label>
                        <div class="input-wrap">
                            <i class="bi bi-people input-icon"></i>
                            <input type="text" class="form-control" id="sponsorName" placeholder="Sponsor name will auto-fill" readonly>
                        </div>
                    </div>
                </div>

                <div class="section-label">ACCOUNT</div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="memberId" class="form-label">Member ID</label>
                        <div class="input-wrap">
                            <i class="bi bi-person-badge input-icon"></i>
                            <input type="text" class="form-control" id="memberId" value="{{ $generatedMemberId }}" readonly aria-readonly="true">
                        </div>
                        <small class="text-slate-500">Assigned automatically. A temporary password is issued after registration.</small>
                    </div>
                </div>

                <button type="submit" class="register-btn">
                    Create Account
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="register-footer">
                Already have an account?
                <a href="{{ route('public.login') }}" class="login-link">Sign In</a>
                <br>
                <a href="{{ route('home') }}" class="back-home">
                    <i class="bi bi-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
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
            const response = await fetch('{{ route('member.register.check-sponsor') }}?sponsor_id=' + encodeURIComponent(value), {
                headers: { Accept: 'application/json' }
            });
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
    if (sponsorId.value.trim() !== '') {
        lookupSponsor();
    }

    form.addEventListener('submit', function (event) {
        if (!sponsorValid || validatedSponsor !== sponsorId.value.trim().toUpperCase()) {
            event.preventDefault();
            lookupSponsor();
            showSponsorError('Please enter a valid, existing Sponsor ID.');
        }
    });
</script>
@endpush
