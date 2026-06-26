<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reset Password — ADT Sports</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0F0E0D;color:#F5F0EB;height:100vh;display:flex;align-items:center;justify-content:center;-webkit-font-smoothing:antialiased}
.box{width:100%;max-width:380px;padding:0 24px}
.logo{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:36px}
.logo-name{font-size:20px;font-weight:700}.logo-name em{font-style:normal;color:#D4420A}
h2{font-size:22px;font-weight:700;text-align:center;margin-bottom:6px}
.sub{font-size:13px;color:#78716C;text-align:center;margin-bottom:28px;line-height:1.6}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:#6B6560;margin-bottom:7px}
.field input{width:100%;background:#1C1917;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:11px 14px;font-size:14px;color:#F5F0EB;outline:none;transition:border-color .2s;font-family:'Inter',sans-serif}
.field input:focus{border-color:#D4420A}
.field input::placeholder{color:#44403C}
.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);color:#FCA5A5;font-size:12px;padding:10px 14px;border-radius:6px;margin-bottom:14px}
.ok{background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.3);color:#86EFAC;font-size:12px;padding:10px 14px;border-radius:6px;margin-bottom:14px}
.btn-login{width:100%;background:#D4420A;color:#fff;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background .15s;margin-top:4px;font-family:'Inter',sans-serif}
.btn-login:hover{background:#B83808}
.back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#78716C;text-decoration:none}
.back:hover{color:#A8A09A}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <img src="/uploads/logo.png" width="45" height="45" alt="ADT">
    <span class="logo-name"><em>ADT</em> Sports Admin</span>
  </div>
  <h2>Forgot your password?</h2>
  <p class="sub">Enter your account email and we'll send you a link to reset it.</p>

  @if(session('status'))
    <div class="ok">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@adtsports.in">
    </div>
    <button type="submit" class="btn-login">Send reset link</button>
  </form>

  <a href="{{ route('admin.login') }}" class="back">← Back to sign in</a>
</div>
</body>
</html>
