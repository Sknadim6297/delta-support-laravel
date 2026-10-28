@extends('admin.layouts.app')

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
<div class="ds-catalog">
    <x-ui.page-header
        kicker="Security"
        title="Change Password"
        lead="Enter the current password, then choose a new one."
    />

    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form class="ds-settings-form ds-password-form" method="POST" action="{{ route('admin.change-password.update') }}" id="changePasswordForm">
        @csrf
        <div class="ds-field-grid">
            <label>
                <span>Old Password <span>*</span></span>
                <input type="password"
                       id="currentPassword"
                       name="current_password"
                       data-error-for="current_password"
                       placeholder="Enter Old Password"
                       autocomplete="current-password">
                @error('current_password')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>New Password <span>*</span></span>
                <input type="password"
                       id="newPassword"
                       name="new_password"
                       data-error-for="new_password"
                       placeholder="Enter New Password"
                       autocomplete="new-password">
                @error('new_password')<small>{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Confirm New Password <span>*</span></span>
                <input type="password"
                       id="confirmPassword"
                       name="new_password_confirmation"
                       data-error-for="new_password_confirmation"
                       placeholder="Confirm New Password"
                       autocomplete="new-password">
                @error('new_password_confirmation')<small>{{ $message }}</small>@enderror
            </label>
        </div>
        <div class="ds-form-actions">
            <button type="submit">Submit</button>
        </div>
    </form>
</div>
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
