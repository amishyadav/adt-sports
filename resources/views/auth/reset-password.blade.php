<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Set New Password — ADT Sports</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0F0E0D;color:#F5F0EB;height:100vh;display:flex;align-items:center;justify-content:center;-webkit-font-smoothing:antialiased}
.box{width:100%;max-width:380px;padding:0 24px}
.logo{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:36px}
.logo-name{font-size:20px;font-weight:700}.logo-name em{font-style:normal;color:#D4420A}
h2{font-size:22px;font-weight:700;text-align:center;margin-bottom:6px}
.sub{font-size:13px;color:#78716C;text-align:center;margin-bottom:28px}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:#6B6560;margin-bottom:7px}
.field input{width:100%;background:#1C1917;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:11px 14px;font-size:14px;color:#F5F0EB;outline:none;transition:border-color .2s;font-family:'Inter',sans-serif}
.field input:focus{border-color:#D4420A}
.field input::placeholder{color:#44403C}
.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);color:#FCA5A5;font-size:12px;padding:10px 14px;border-radius:6px;margin-bottom:14px}
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
  <h2>Set a new password</h2>
  <p class="sub">Choose a strong password you don't use elsewhere.</p>

  @if($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" value="{{ old('email', $email) }}" required readonly>
    </div>
    <div class="field">
      <label>New Password</label>
      <input type="password" name="password" required autofocus placeholder="At least 8 characters" minlength="8">
    </div>
    <div class="field">
      <label>Confirm Password</label>
      <input type="password" name="password_confirmation" required placeholder="Re-enter new password">
    </div>
    <button type="submit" class="btn-login">Reset password</button>
  </form>

  <a href="{{ route('admin.login') }}" class="back">← Back to sign in</a>
</div>
</body>
</html>
