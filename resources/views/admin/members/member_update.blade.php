@extends('admin.layouts.app')

@section('content')
        <main class="dashboard-content">

        

        <div class="form-card">

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div id="memberSummary" class="row mb-4" hidden>
                <div class="col-md-3 mb-2"><label>Account status</label><div id="summaryStatus" class="fw-semibold"></div></div>
                <div class="col-md-3 mb-2"><label>Registration date</label><div id="summaryRegistered" class="fw-semibold"></div></div>
                <div class="col-md-3 mb-2"><label>Package status</label><div id="summaryPackage" class="fw-semibold"></div></div>
                <div class="col-md-3 mb-2"><label>Direct referrals</label><div id="summaryReferrals" class="fw-semibold"></div></div>
            </div>

            <!-- MEMBER ID SEARCH -->
            <div class="row align-items-end mb-4">

                <div class="col-md-6">
                    <label>Member ID</label>
                    <input type="text"
                        id="memberId"
                        name="member_id"
                        form="memberUpdateForm"
                        value="{{ old('member_id', request('member_id')) }}"
                        class="form-control"
                        placeholder="Enter Member ID">
                </div>

                <div class="col-md-auto mt-3 mt-md-0">
                    <button type="button" id="fetchMemberDetails" class="btn btn-primary px-4">
                        <i class="bi bi-search"></i>
                        Submit
                    </button>
                </div>

            </div>


            <!-- PERSONAL DETAILS -->
            <div class="form-title">
                <h4>Personal Details</h4>
            </div>

            <form method="POST" action="{{ route('admin.members.update-member') }}" id="memberUpdateForm">
                @csrf

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label>Sponsor ID <span class="text-danger">*</span></label>
                    <input type="text"
                        id="sponsorId"
                        name="sponsor_id"
                        value="{{ old('sponsor_id') }}"
                        class="form-control"
                        placeholder="Enter Sponsor ID">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Sponsor Name <span class="text-danger">*</span></label>
                    <input type="text"
                        id="sponsorName"
                        name="sponsor_name"
                        value="{{ old('sponsor_name') }}"
                        class="form-control"
                        placeholder="Enter Sponsor Name">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Member Name <span class="text-danger">*</span></label>
                    <input type="text"
                        id="memberName"
                        name="member_name"
                        value="{{ old('member_name') }}"
                        class="form-control"
                        placeholder="Enter Member Name">
                </div>

                <div class="col-md-6 mb-3">
                    <label>BEP20 Wallet Address</label>
                    <input type="text"
                        id="walletAddress"
                        name="wallet_address"
                        value="{{ old('wallet_address') }}"
                        class="form-control"
                        placeholder="Enter BEP20 Wallet Address">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Mobile No <span class="text-danger">*</span></label>
                    <input type="text"
                        id="mobileNo"
                        name="mobile_no"
                        value="{{ old('mobile_no') }}"
                        class="form-control"
                        placeholder="Enter Mobile No"
                        required
                        maxlength="10">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Email ID <span class="text-danger">*</span></label>
                    <input type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control"
                        placeholder="Enter Email ID">
                </div>

                <div class="col-md-6 mb-3">
                    <label>PAN Card Number <span class="text-danger">*</span></label>
                    <input type="text"
                        id="panCardNo"
                        name="pan_card_no"
                        value="{{ old('pan_card_no') }}"
                        class="form-control"
                        placeholder="Enter PAN Card Number"
                        maxlength="10">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Password</label>
                    <input type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter Password">
                </div>

            </div>


            <!-- UPDATE BUTTON -->
            <div class="text-end mt-3">

                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-circle"></i>
                    Update
                </button>

            </div>

            </form>

        </div>


    </main>

@endsection

@section('scripts')
    <script>
        const memberIdInput = document.getElementById('memberId');
        const memberUpdateForm = document.getElementById('memberUpdateForm');
        const fetchMemberDetailsButton = document.getElementById('fetchMemberDetails');
        const sponsorIdInput = document.getElementById('sponsorId');
        const sponsorNameInput = document.getElementById('sponsorName');
        const memberNameInput = document.getElementById('memberName');
        const walletAddressInput = document.getElementById('walletAddress');
        const mobileNoInput = document.getElementById('mobileNo');
        const emailInput = document.getElementById('email');
        const panCardInput = document.getElementById('panCardNo');
        const passwordInput = document.getElementById('password');
        const fields = {
            sponsor_id: sponsorIdInput,
            sponsor_name: sponsorNameInput,
            name: memberNameInput,
            wallet: walletAddressInput,
            mobile: mobileNoInput,
            email: emailInput,
            pan_card_no: panCardInput,
            password: passwordInput
        };
        const touchedFields = new Set();
        let sponsorValidationTimer = null;
        let fetchedMemberId = null;
        let sponsorIdValid = false;
        let validatedSponsorId = '';

        function setError(fieldName, message = '') {
            let error = document.querySelector('[data-error-for="' + fieldName + '"]');

            if (!error) {
                error = document.createElement('div');
                error.className = 'text-danger mt-1 small validation-message';
                error.dataset.errorFor = fieldName;
                const field = {
                    member_name: memberNameInput,
                    sponsor_id: sponsorIdInput,
                    sponsor_name: sponsorNameInput,
                    mobile_no: mobileNoInput,
                    email: emailInput,
                    pan_card_no: panCardInput,
                    password: passwordInput
                }[fieldName];
                field?.parentElement.appendChild(error);
            }

            error.textContent = message;
            error.style.display = message ? 'block' : 'none';
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

            if (!/^[A-Za-z ]+$/.test(value)) {
                setError('member_name', 'Member name can contain only letters and spaces.');
                return false;
            }

            setError('member_name');
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

            setError('email');
            return true;
        }

        function validateMobileNo(force = false) {
            const value = (mobileNoInput?.value || '').trim();

            if (!value) {
                setError('mobile_no', shouldShowRequired('mobile_no', force) ? 'Mobile No is required.' : '');
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

            setError('mobile_no');
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

            setError('pan_card_no');
            return true;
        }

        function validatePassword() {
            const value = (passwordInput?.value || '').trim();

            if (value && value.length < 6) {
                setError('password', 'Password must be at least 6 characters.');
                return false;
            }

            setError('password');
            return true;
        }

        function validateSponsorId(force = false) {
            const sponsorId = (sponsorIdInput?.value || '').trim();

            if (!sponsorId) {
                setError('sponsor_id', shouldShowRequired('sponsor_id', force) ? 'Sponsor ID is required.' : '');
                if (sponsorNameInput) {
                    sponsorNameInput.value = '';
                }
                sponsorIdValid = false;
                validatedSponsorId = '';
                return false;
            }

            if (sponsorId.toUpperCase() === 'ST666666') {
                sponsorNameInput.value = 'Admin';
                setError('sponsor_id');
                sponsorIdValid = true;
                validatedSponsorId = sponsorId.toUpperCase();
                return true;
            }

            if (sponsorIdValid && validatedSponsorId === sponsorId.toUpperCase()) {
                setError('sponsor_id');
                return true;
            }

            const requestId = Date.now();
            sponsorValidationTimer = requestId;
            sponsorIdValid = false;
            validatedSponsorId = '';
            setError('sponsor_id');

            fetch('{{ route('admin.members.check-sponsor-id') }}?sponsor_id=' + encodeURIComponent(sponsorId), {
                headers: { 'Accept': 'application/json' }
            })
                .then(response => response.json())
                .then(data => {
                    if (sponsorValidationTimer !== requestId) {
                        return;
                    }

                    if (data.exists) {
                        sponsorNameInput.value = data.sponsor_name || '';
                        setError('sponsor_id');
                        sponsorIdValid = true;
                        validatedSponsorId = sponsorId.toUpperCase();
                    } else {
                        sponsorNameInput.value = '';
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

        function clearMemberFields() {
            Object.values(fields).forEach(function (field) {
                if (field) {
                    field.value = '';
                }
            });
            setError('sponsor_id');
            setError('sponsor_name');
            setError('member_name');
            setError('mobile_no');
            setError('email');
            setError('pan_card_no');
            setError('password');
            const summary = document.getElementById('memberSummary');
            if (summary) {
                summary.hidden = true;
            }
        }

        function showMemberError(message) {
            let error = document.getElementById('memberIdError');

            if (!error) {
                error = document.createElement('div');
                error.id = 'memberIdError';
                error.className = 'text-danger mt-1 small';
                memberIdInput.parentElement.appendChild(error);
            }

            error.textContent = message || '';
        }

        memberIdInput.addEventListener('input', function () {
            fetchedMemberId = null;
            clearMemberFields();
            showMemberError('');
        });

        mobileNoInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            markTouched('mobile_no');
            validateMobileNo();
        });

        mobileNoInput.addEventListener('blur', function () {
            markTouched('mobile_no');
            validateMobileNo(true);
        });

        memberNameInput.addEventListener('input', function () {
            this.value = this.value.trimStart();
            markTouched('member_name');
            validateMemberName();
        });

        memberNameInput.addEventListener('blur', function () {
            markTouched('member_name');
            validateMemberName(true);
        });

        emailInput.addEventListener('input', function () {
            markTouched('email');
            validateEmail();
        });

        emailInput.addEventListener('blur', function () {
            markTouched('email');
            validateEmail(true);
        });

        panCardInput.addEventListener('input', function () {
            this.value = this.value.slice(0, 10);
            markTouched('pan_card_no');
            validatePanCard();
        });

        panCardInput.addEventListener('blur', function () {
            markTouched('pan_card_no');
            validatePanCard(true);
        });

        passwordInput.addEventListener('input', validatePassword);
        passwordInput.addEventListener('blur', validatePassword);

        sponsorIdInput.addEventListener('input', function () {
            this.value = this.value.trim();
            markTouched('sponsor_id');
            clearTimeout(sponsorValidationTimer);
            if (!this.value) {
                validateSponsorId();
                return;
            }
            sponsorValidationTimer = setTimeout(function () {
                validateSponsorId();
            }, 400);
        });

        sponsorIdInput.addEventListener('blur', function () {
            markTouched('sponsor_id');
            validateSponsorId(true);
        });

        fetchMemberDetailsButton.addEventListener('click', function (event) {
            event.preventDefault();
            const memberId = memberIdInput.value.trim();

            fetchedMemberId = null;
            clearMemberFields();
            showMemberError('');

            if (!memberId) {
                showMemberError('Member ID is required.');
                return;
            }

            fetch('{{ route('admin.members.fetch-details') }}?member_id=' + encodeURIComponent(memberId), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Member not found.');
                        }

                        return data;
                    });
                })
                .then(function (member) {
                    memberIdInput.value = member.member_id;
                    fields.sponsor_id.value = member.sponsor_id || '';
                    fields.sponsor_name.value = member.sponsor_name || '';
                    fields.name.value = member.name || '';
                    fields.wallet.value = member.wallet || '';
                    fields.mobile.value = member.mobile || '';
                    fields.email.value = member.email || '';
                    fields.pan_card_no.value = member.pan_card_no || '';
                    fields.password.value = '';
                    fetchedMemberId = member.member_id;
                    sponsorIdValid = true;
                    const summary = document.getElementById('memberSummary');
                    if (summary) {
                        summary.hidden = false;
                        document.getElementById('summaryStatus').textContent = member.account_status || '';
                        document.getElementById('summaryRegistered').textContent = member.registered_at || '';
                        document.getElementById('summaryPackage').textContent = member.package_status || '';
                        document.getElementById('summaryReferrals').textContent = String(member.direct_referrals ?? 0);
                    }
                })
                .catch(function (error) {
                    fetchedMemberId = null;
                    clearMemberFields();
                    showMemberError(error.message || 'Member not found.');
                });
        });

        memberUpdateForm.addEventListener('submit', function (event) {
            if (!fetchedMemberId || memberIdInput.value.trim() !== fetchedMemberId) {
                event.preventDefault();
                showMemberError('Fetch a valid Member ID before updating.');
                return;
            }

            const sponsorId = sponsorIdInput.value.trim().toUpperCase();
            const sponsorAlreadyValidated = sponsorIdValid && validatedSponsorId === sponsorId;

            markTouched('member_name');
            markTouched('sponsor_id');
            markTouched('email');
            markTouched('mobile_no');
            markTouched('pan_card_no');

            const isValid = validateMemberName(true)
                && (sponsorAlreadyValidated || validateSponsorId(true))
                && validateEmail(true)
                && validateMobileNo(true)
                && validatePanCard(true)
                && validatePassword();

            if (!isValid) {
                event.preventDefault();
                if (!sponsorIdValid) {
                    setError('sponsor_id', 'Please enter a valid, existing Sponsor ID.');
                }
            }
        });

        if ((memberIdInput?.value || '').trim() !== '') {
            fetchMemberDetailsButton.click();
        }
    </script>
@endsection