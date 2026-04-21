<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin {{ $mode === 'login' ? 'Login' : 'Register' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: url("https://payout-dashboard.ertitech.com/bg-login.png") no-repeat center center fixed;
            background-size: cover;
        }
        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 100px;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 40px 50px;
            max-width: 430px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .logo {
            width: 180px;
            margin-bottom: 20px;
            object-fit: cover;
            display: block;
        }
        .title {
            color: #0a4297;
            letter-spacing: 0.7px;
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }
        .subtitle {
            letter-spacing: 1px;
            color: #858384;
            margin: 12px 0 0 0;
            font-size: 1.3rem;
        }
        .subtitle strong { font-weight: 700; }
        form { padding-top: 30px; }
        .row { position: relative; margin-bottom: 35px; }
        label {
            color: #999;
            display: block;
            font-size: 14px;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        input {
            width: 100%;
            height: 40px;
            font-size: 15px;
            border: none;
            border-bottom: 1px solid #ddd;
            background: transparent;
            padding: 0;
            color: #333;
            outline: none;
        }
        input:focus { border-bottom: 2px solid #0a4297; }
        .password-wrap { position: relative; }
        .eye {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #999;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }
        .actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        .btn {
            background: #0a4297;
            cursor: pointer;
            font-size: 1rem;
            letter-spacing: 0.7px;
            font-weight: 600;
            padding: 12px 80px;
            color: #fff;
            border: none;
            border-radius: 10px;
            width: 100%;
            max-width: 270px;
        }
        .btn:hover { background: #084080; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .forgot {
            font-size: 0.95rem;
            letter-spacing: 0.6px;
            color: #0a4297;
            text-decoration: none;
            display: inline-block;
            margin-top: 2px;
        }
        .switch {
            margin-top: 14px;
            text-align: center;
            font-size: 14px;
            color: #586174;
        }
        .switch a { color: #0a4297; text-decoration: none; }
        .alert {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .alert.error { background: #ffe7e7; color: #a11f1f; }
        .alert.success { background: #e6f8ed; color: #126539; }
        @media (max-width: 1110px) {
            .page { padding: 50px; }
        }
        @media (max-width: 992px) {
            .page { justify-content: center; padding: 20px; }
            .page::before {
                content: "";
                position: fixed;
                inset: 0;
                background: rgba(255, 255, 255, 0.4);
                z-index: 0;
            }
            .card { position: relative; z-index: 1; }
        }
        @media (max-width: 767px) {
            .card { width: 400px; }
            .logo { width: 150px; }
        }
        @media (max-width: 405px) {
            .card { width: 100%; padding: 30px 20px; }
            .logo { width: 130px; }
            .btn { padding: 10px 60px; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <img src="https://payout-dashboard.ertitech.com/er.png" alt="Ertitech" class="logo">

        @if($mode === 'login')
            <h1 class="title">MERCHANT DASHBOARD</h1>
            <p class="subtitle">Login to your <strong>Account</strong></p>
        @else
            <h1 class="title">ADMIN REGISTER</h1>
            <p class="subtitle">Create admin <strong>Account</strong></p>
        @endif

        <form method="post" action="{{ $mode === 'login' ? route('admin.login') : route('admin.register') }}">
            @csrf

            @if(session('error'))
                <div class="alert error">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif

            @if($mode === 'register')
                <div class="row">
                    <label>Name</label>
                    <input name="name" value="{{ old('name') }}" required>
                </div>
                <div class="row">
                    <label>Phone</label>
                    <input name="phone" value="{{ old('phone') }}">
                </div>
                <div class="row">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                </div>
            @endif

            <div class="row">
                <label>{{ $mode === 'login' ? 'Username or Email' : 'Email' }}</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="info@xenntech.in" required>
            </div>

            <div class="row password-wrap">
                <label>Password</label>
                <input id="password" type="password" name="password" placeholder="Enter password" required>
                <button class="eye" type="button" onclick="togglePassword()">Show</button>
            </div>

            @if($mode === 'register')
                <div class="row">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" required>
                </div>
            @endif

            <div class="actions">
                <button class="btn" type="submit">{{ $mode === 'login' ? 'Login' : 'Create Admin Account' }}</button>
                @if($mode === 'login')
                    <a class="forgot" href="#" onclick="return false;">Forgot Password?</a>
                @endif
            </div>
        </form>

        <div class="switch">
            @if($mode === 'login')
                New admin? <a href="{{ route('admin.register.form') }}">Register</a>
            @else
                Already have admin account? <a href="{{ route('admin.login.form') }}">Login</a>
            @endif
        </div>
    </div>
</div>
<script>
    function togglePassword() {
        const input = document.getElementById('password');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>
</body>
</html>
