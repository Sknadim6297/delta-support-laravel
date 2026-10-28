<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delta Support')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 20px; display: flex; align-items: center; justify-content: center; font-family: Inter, sans-serif; background: #f3f6fc; color: #172033; }
        .login-wrapper { width: 100%; max-width: 950px; min-height: 570px; display: flex; overflow: hidden; background: #fff; border-radius: 24px; box-shadow: 0 25px 70px rgba(30,55,100,.12); }
        .login-left { position: relative; width: 50%; padding: 50px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: white; background: linear-gradient(145deg, var(--ds-foreground), var(--ds-primary-hover) 65%, var(--ds-primary)); }
        .login-left::before, .login-left::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,.1); }
        .login-left::before { width: 220px; height: 220px; top: -80px; left: -80px; }
        .login-left::after { width: 280px; height: 280px; right: -100px; bottom: -130px; background: rgba(255,255,255,.08); }
        .brand, .login-left h2, .login-left p { position: relative; z-index: 1; }
        .brand img { width: 150px; margin-bottom: 25px; }
        .login-left h2 { margin: 0 0 15px; font-size: 30px; }
        .login-left p { max-width: 350px; margin: 0; font-size: 15px; line-height: 1.7; opacity: .9; }
        .login-right { width: 50%; padding: 45px 60px; display: flex; flex-direction: column; justify-content: center; }
        .login-title { margin-bottom: 8px; color: #222; font-size: 30px; font-weight: 700; }
        .login-subtitle { margin-bottom: 24px; color: #888; font-size: 14px; }
        .input-group { margin-bottom: 16px; }
        .input-group label { display: block; margin-bottom: 8px; color: #444; font-size: 14px; font-weight: 600; }
        .input-box { position: relative; }
        .input-box span { position: absolute; top: 50%; left: 16px; transform: translateY(-50%); color: var(--ds-primary); font-size: 18px; }
        .input-box input { width: 100%; height: 52px; padding: 0 15px 0 46px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; font-size: 15px; transition: .3s; }
        .input-box input:focus { border-color: var(--ds-primary); box-shadow: 0 0 0 4px var(--ds-focus); }
        .input-box input[readonly] { background: #fafafa; color: #666; }
        .login-btn { width: 100%; height: 52px; border: 0; border-radius: 12px; color: white; background: linear-gradient(100deg, var(--ds-primary), var(--ds-primary-hover)); font-size: 16px; font-weight: 700; box-shadow: 0 8px 18px rgba(8,120,209,.2); cursor: pointer; transition: .3s; }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(8,120,209,.28); }
        .login-footer { margin-top: 22px; color: #758096; font-size: 13px; text-align: center; }
        .login-footer a, .forgot { color: var(--ds-primary); text-decoration: none; font-weight: 700; }
        .login-footer a:hover, .forgot:hover { text-decoration: underline; }
        .auth-links { display: flex; justify-content: space-between; margin-top: 18px; font-size: 13px; }
        .validation-message { min-height: 18px; color: #dc3545; font-size: 12px; }
        .auth-alert { margin-bottom: 16px; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .auth-alert-danger { background: #fde8ea; color: #b42318; }
        .auth-alert-success { background: #e8f8ef; color: #067647; }
        .auth-alert-info { background: #e8f3fc; color: #0752a0; }
        .auth-link-button { border: 0; background: transparent; padding: 0; color: var(--ds-primary); font-weight: 700; cursor: pointer; }
        @media (max-width: 768px) { body { padding: 15px; } .login-wrapper { flex-direction: column; max-width: 450px; min-height: auto; border-radius: 22px; } .login-left { width: 100%; min-height: 220px; padding: 35px 25px; } .login-left h2 { font-size: 23px; } .login-left p { font-size: 13px; } .login-right { width: 100%; padding: 35px 25px 40px; } .login-title { font-size: 26px; } }
        @media (max-width: 380px) { .login-right { padding: 30px 20px; } }
    </style>
    @stack('head')
    @include('partials.brand-tokens')
</head>
<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="brand"><img src="{{ \App\Support\BrandTokens::logoUrl() }}" alt="Delta Support"></div>
            <h2>Delta Support</h2>
            <p>Reset your member password and return to your account.</p>
        </div>
        <div class="login-right">
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
