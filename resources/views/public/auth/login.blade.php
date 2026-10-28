<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Delta Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/auth-portal.css'])
    @include('partials.brand-tokens')
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-form-panel">
                <div class="login-logo">
                    <a href="{{ route('home') }}">
                        <img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support Logo">
                    </a>
                </div>

                <div class="form-top">
                    <span class="portal-label">MEMBER PORTAL</span>
                    <h2 class="form-heading">Sign In</h2>
                    <p class="form-subtitle">Enter your account details to continue.</p>
                </div>

                @if (session('success'))
                    <div class="form-message mb-4" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="form-message mb-4 text-red-600" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('member.login.store') }}" id="loginForm">
                    @csrf
                    <div class="mb-6">
                        <label for="username" class="form-label">Member ID</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="username" name="member_id" value="{{ old('member_id') }}" placeholder="Enter your Member ID (STxxxxxx)" autocomplete="username" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="remember-label">
                            <input type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember'))>
                            <span>Remember me</span>
                        </label>
                        <a href="{{ route('member.forgot') }}" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="login-btn">
                        <span>Login</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="form-bottom">
                    <p class="register-text">
                        Don't have an account?
                        <a href="{{ route('member.register') }}">Create Account</a>
                    </p>
                    <a href="{{ route('home') }}" class="back-home">
                        <i class="bi bi-arrow-left"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    </script>
</body>
</html>
