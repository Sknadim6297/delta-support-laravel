<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    
    
    

    <!-- Bootstrap 5 -->
    

    <!-- Bootstrap Icons -->
    
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    

</head>


<body>

    <div>
        <!-- LEFT -->
        <div>
            <div>
                <img src="{{ asset('assets/img/logo.png') }}" alt="DevEngine Logo">
            </div>

            <h2>Welcome Back!</h2>
            <p>
                Login to your account and manage your dashboard, members and business
                activities easily.
            </p>
        </div>
        <!-- RIGHT -->
        <div>
            <div>Sign In </div>
            <div>Please enter your account details </div>

            @if ($errors->any())
                <div>
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('success'))
                <div>
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div>
                    <label>Member ID</label>
                    <div>
                        <span>👤</span>
                        <input type="text" name="member_id" value="{{ old('member_id') }}" placeholder="Enter Member ID" autocomplete="username" required>
                    </div>
                </div>

                <div>
                    <label>Password</label>
                    <div>
                        <span>🔒</span>
                        <input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
                    </div>
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="{{ route('member.forgot') }}">Forgot Password?</a>
                </div>

                <button type="submit">
                    Login
                </button>
            </form>

            <div>Don't have an account? <a href="{{ route('member.register') }}">Create Account</a></div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>



</body>
</html>
