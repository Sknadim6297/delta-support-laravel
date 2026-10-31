@extends('auth.layout')

@section('title', 'Set Password')

@section('content')
    <div class="login-title">Set Password</div>
    <div class="login-subtitle">This link can be used once and expires in 60 minutes.</div>

    @if ($errors->any())
        <div class="auth-alert auth-alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($memberId === null)
        <div class="auth-alert auth-alert-danger">This password setup link is invalid or expired.</div>
        <div class="login-footer"><a href="{{ route('login') }}">Sign In</a></div>
    @else
        <form method="POST" action="{{ route('member.password-setup.store', ['token' => $token]) }}">
            @csrf
            <div class="input-group">
                <label>Member ID</label>
                <div class="input-box">
                    <span>#</span>
                    <input type="text" value="{{ $memberId }}" readonly>
                </div>
            </div>
            <div class="input-group">
                <label>New Password</label>
                <div class="input-box">
                    <span>🔒</span>
                    <input type="password" name="password" placeholder="Enter a password" required autocomplete="new-password">
                </div>
            </div>
            <div class="input-group">
                <label>Confirm Password</label>
                <div class="input-box">
                    <span>🔒</span>
                    <input type="password" name="password_confirmation" placeholder="Confirm the password" required autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="login-btn">Save Password</button>
        </form>
    @endif
@endsection
