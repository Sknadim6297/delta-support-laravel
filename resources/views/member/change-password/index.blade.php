<!-- //now fix the all details as a member -->

@extends('member.layouts.app')

@section('title', 'Account Security')
@section('page-title', 'Account Security')
@section('content')

        <main>

    <div>


        @if ($errors->any())
            <div>{{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div>{{ session('success') }}</div>
        @endif

        <!-- TITLE -->
        <div>
            <h4>Change Password</h4>
        </div>

        <form method="POST" action="{{ route('member.change-password.post') }}" id="changePasswordForm">
            @csrf
            <div>

                <!-- OLD PASSWORD -->
                <div>
                    <label>
                        Old Password <span>*</span>
                    </label>

                    <input type="password"
                           id="currentPassword"
                           name="current_password"
                          
                           data-error-for="current_password"
                           placeholder="Enter Old Password">
                </div>


                <!-- NEW PASSWORD -->
                <div>
                    <label>
                        New Password <span>*</span>
                    </label>

                    <input type="password"
                           id="newPassword"
                           name="new_password"
                          
                           data-error-for="new_password"
                           placeholder="Enter New Password">
                </div>


                <!-- CONFIRM PASSWORD -->
                <div>
                    <label>
                        Confirm New Password <span>*</span>
                    </label>

                    <input type="password"
                           id="confirmPassword"
                           name="new_password_confirmation"
                          
                           data-error-for="new_password_confirmation"
                           placeholder="Confirm New Password">
                </div>

            </div>


            <!-- SUBMIT -->
            <div>

                <button type="submit"
                       
                       >

                    <i></i>
                    Submit

                </button>

            </div>
        </form>

    </div>

       </main>
       @endsection

@section('scripts')
    <script>
        const currentPasswordInput = document.getElementById('currentPassword');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) return;

            let error = target.parentElement.querySelector('.validation-message');
            if (!error) {
                error = document.createElement('div');
                error.className = 'validation-message';
                target.parentElement.appendChild(error);
            }

            error.textContent = message;
            error.hidden = !message;
        }

        function validateCurrentPassword(force = false) {
            const value = (currentPasswordInput.value || '').trim();
            if (!value) {
                setError('current_password', force ? 'Old Password is required.' : '');
                return false;
            }
            setError('current_password', '');
            return true;
        }

        function validateNewPassword(force = false) {
            const value = (newPasswordInput.value || '').trim();
            if (!value) {
                setError('new_password', force ? 'New Password is required.' : '');
                return false;
            }
            if (value.length < 8) {
                setError('new_password', 'New Password must be at least 8 characters.');
                return false;
            }
            setError('new_password', '');
            return true;
        }

        function validateConfirmPassword(force = false) {
            const value = (confirmPasswordInput.value || '').trim();
            if (!value) {
                setError('new_password_confirmation', force ? 'Confirm New Password is required.' : '');
                return false;
            }
            if (value !== newPasswordInput.value) {
                setError('new_password_confirmation', 'New Password and Confirm New Password do not match.');
                return false;
            }
            setError('new_password_confirmation', '');
            return true;
        }

        currentPasswordInput.addEventListener('input', function () {
            validateCurrentPassword();
        });
        currentPasswordInput.addEventListener('blur', function () {
            validateCurrentPassword(true);
        });

        newPasswordInput.addEventListener('input', function () {
            validateNewPassword();
            if (confirmPasswordInput.value) {
                validateConfirmPassword();
            }
        });
        newPasswordInput.addEventListener('blur', function () {
            validateNewPassword(true);
        });

        confirmPasswordInput.addEventListener('input', function () {
            validateConfirmPassword();
        });
        confirmPasswordInput.addEventListener('blur', function () {
            validateConfirmPassword(true);
        });

        document.getElementById('changePasswordForm').addEventListener('submit', function (event) {
            const currentOk = validateCurrentPassword(true);
            const newOk = validateNewPassword(true);
            const confirmOk = validateConfirmPassword(true);

            if (!currentOk || !newOk || !confirmOk) {
                event.preventDefault();
            }
        });
    </script>
@endsection