@extends('auth.layout')

@section('title', 'Verify OTP')

@section('content')
    <div class="login-title">Enter OTP</div>
    <div class="login-subtitle">We will send an OTP to your registered email address.</div>

    @if ($errors->any())
        <div class="auth-alert auth-alert-danger">{{ $errors->first() }}</div>
    @endif
    @if ($maskedEmail)
        <div class="auth-alert auth-alert-info">OTP sent to {{ $maskedEmail }}. It expires in 10 minutes.</div>
    @endif

    <form method="POST" action="{{ route('member.forgot.verify.store') }}">
        @csrf
        <div class="input-group">
            <label>Enter OTP</label>
            <div class="input-box">
                <span>🔐</span>
                <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit OTP" required autofocus>
            </div>
        </div>
        <button type="submit" class="login-btn">Verify OTP</button>
    </form>

    <div class="auth-links">
        <form method="POST" action="{{ route('member.forgot.resend') }}">
            @csrf
            <button type="submit" class="auth-link-button">Resend OTP</button>
        </form>
        <a href="{{ route('login') }}" class="forgot">Sign In</a>
    </div>
@endsection
