<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient( 135deg, #ff4f9a, #ff79b5, #ffb6d5 );
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        /* ===================================== LOGIN WRAPPER ===================================== */

        .login-wrapper {
            width: 100%;
            max-width: 950px;
            min-height: 570px;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 28px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 25px 60px rgba(160, 20, 80, 0.30);
        }
        /* ===================================== LEFT PANEL ===================================== */

        .login-left {
            width: 50%;
            background: linear-gradient( 145deg, #d70e66, #580b2c, #992b58 );
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 50px;
            position: relative;
        }

            .login-left::before {
                content: "";
                width: 220px;
                height: 220px;
                border-radius: 50%;
                background: rgba(255,255,255,0.10);
                position: absolute;
                top: -80px;
                left: -80px;
            }

            .login-left::after {
                content: "";
                width: 280px;
                height: 280px;
                border-radius: 50%;
                background: rgba(255,255,255,0.08);
                position: absolute;
                bottom: -130px;
                right: -100px;
            }

        .brand {
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 18px;
            position: relative;
            z-index: 2;
        }

        .login-left h2 {
            font-size: 30px;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .login-left p {
            max-width: 350px;
            line-height: 1.7;
            font-size: 15px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        /* ===================================== RIGHT PANEL ===================================== */

        .login-right {
            width: 50%;
            padding: 55px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-title {
            font-size: 30px;
            font-weight: 700;
            color: #222;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }
        /* ===================================== INPUT ===================================== */

        .input-group {
            margin-bottom: 20px;
        }

            .input-group label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #444;
                margin-bottom: 8px;
            }

        .input-box {
            position: relative;
        }

            .input-box span {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 18px;
                color: #e83e8c;
            }

            .input-box input {
                width: 100%;
                height: 52px;
                border: 1px solid #e5e5e5;
                border-radius: 12px;
                padding: 0 15px 0 46px;
                font-size: 15px;
                outline: none;
                transition: 0.3s;
            }

                .input-box input:focus {
                    border-color: #ed3f91;
                    box-shadow: 0 0 0 4px rgba(237,63,145,0.10);
                }
        /* ===================================== OPTIONS ===================================== */

        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 5px 0 25px;
            font-size: 13px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #666;
        }

            .remember input {
                accent-color: #e83e8c;
            }

        .forgot {
            color: #e83e8c;
            text-decoration: none;
            font-weight: 600;
        }

            .forgot:hover {
                text-decoration: underline;
            }
        /* ===================================== LOGIN BUTTON ===================================== */

        .login-btn {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 12px;
            background: linear-gradient( 135deg, #d41468, #f2297d );
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 20px rgba(212,20,104,0.25);
        }

            .login-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 25px rgba(212,20,104,0.35);
            }
        /* ===================================== FOOTER ===================================== */

        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: #888;
        }

            .login-footer a {
                color: #e83e8c;
                text-decoration: none;
                font-weight: 700;
            }
        /* ===================================== MOBILE ===================================== */

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .login-wrapper {
                flex-direction: column;
                min-height: auto;
                max-width: 450px;
                border-radius: 22px;
            }

            .login-left {
                width: 100%;
                min-height: 220px;
                padding: 35px 25px;
            }

            .brand {
                font-size: 28px;
            }

            .login-left h2 {
                font-size: 23px;
            }

            .login-left p {
                font-size: 13px;
                max-width: 320px;
            }

            .login-right {
                width: 100%;
                padding: 35px 25px 40px;
            }

            .login-title {
                font-size: 26px;
            }
        }
        /* ===================================== SMALL MOBILE ===================================== */

        @media (max-width: 380px) {
            .login-left {
                min-height: 200px;
            }

            .login-right {
                padding: 30px 20px;
            }

            .login-options {
                font-size: 12px;
            }
        }
        .brand img {
    width: 150px;
    height: auto;
    display: block;
    margin-bottom: 25px;
}
    </style>

</head>


<body>

    <div class="login-wrapper">
        <!-- LEFT -->
        <div class="login-left">
            <div class="brand">
                <img src="{{ asset('assets/img/logo.png') }}" alt="DevEngine Logo">
            </div>

            <h2>Welcome Back!</h2>
            <p>
                Login to your account and manage your dashboard, members and business
                activities easily.
            </p>
        </div>
        <!-- RIGHT -->
        <div class="login-right">
            <div class="login-title">Sign In </div>
            <div class="login-subtitle">Please enter your account details </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-3">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success mb-3">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div class="input-group">
                    <label>Member ID</label>
                    <div class="input-box">
                        <span>👤</span>
                        <input type="text" name="member_id" value="{{ old('member_id') }}" placeholder="Enter Member ID" autocomplete="username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-box">
                        <span>🔒</span>
                        <input type="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="login-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="{{ route('member.forgot') }}" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">
                    Login
                </button>
            </form>

            <div class="login-footer">Don't have an account? <a href="{{ route('member.register') }}">Create Account</a></div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>



</body>
</html>
