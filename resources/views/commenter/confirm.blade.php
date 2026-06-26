<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirm your email — {{ $siteName }}</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body{margin:0;background:#f4f1ec;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1a1410;display:flex;min-height:100vh;align-items:center;justify-content:center}
  .card{max-width:440px;width:calc(100% - 40px);background:#fff;border:1px solid #e7e1d8;border-radius:16px;padding:36px 30px;text-align:center}
  .brand{font-size:20px;font-weight:800;color:#D4420A;margin-bottom:14px}
  h1{font-size:20px;margin:0 0 8px}
  p{font-size:14px;line-height:1.6;color:#4a4640;margin:0 0 22px}
  button{background:#D4420A;color:#fff;border:0;font-weight:700;font-size:15px;padding:13px 26px;border-radius:10px;cursor:pointer}
  a{color:#8a847b;font-size:12px;text-decoration:none;display:inline-block;margin-top:18px}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">{{ $siteName }}</div>
    <h1>Confirm your email</h1>
    <p>Click the button to confirm your email and unlock commenting{{ $article ? ' on “' . \Illuminate\Support\Str::limit($article->title, 60) . '”' : '' }}. You'll also start receiving our Kabaddi newsletter — unsubscribe anytime.</p>
    <form method="POST" action="{{ $action }}">
      @csrf
      <button type="submit">Confirm my email →</button>
    </form>
    <a href="{{ $article ? route('article', $article->slug) : route('home') }}">No thanks, take me back</a>
  </div>
</body>
</html>
