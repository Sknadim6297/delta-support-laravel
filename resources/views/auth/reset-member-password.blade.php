@extends('auth.layout')

@section('title', 'Reset Password')

@section('content')
    <div class="login-title">Reset Password</div>
    <div class="login-subtitle">Choose a new password for your member account.</div>

    @if ($errors->any())
        <div class="auth-alert auth-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('member.forgot.reset.store') }}">
        @csrf
        <div class="input-group">
            <label>New Password</label>
            <div class="input-box">
                <span>🔒</span>
                <input type="password" name="password" placeholder="Enter new password" required autocomplete="new-password">
            </div>
        </div>
        <div class="input-group">
            <label>Confirm Password</label>
            <div class="input-box">
                <span>🔒</span>
                <input type="password" name="password_confirmation" placeholder="Confirm new password" required autocomplete="new-password">
            </div>
        </div>
        <button type="submit" class="login-btn">Reset Password</button>
    </form>
@endsection
