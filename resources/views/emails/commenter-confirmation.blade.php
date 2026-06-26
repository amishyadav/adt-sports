<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f4f1ec;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1a1410">
  <div style="max-width:520px;margin:0 auto;padding:32px 20px">
    <div style="background:#fff;border-radius:14px;padding:32px 28px;border:1px solid #e7e1d8">
      <div style="font-size:20px;font-weight:800;color:#D4420A;margin-bottom:6px">{{ $siteName }}</div>
      <h1 style="font-size:20px;margin:14px 0 8px">Confirm your email to join the conversation</h1>
      <p style="font-size:14px;line-height:1.6;color:#4a4640;margin:0 0 22px">
        Hi {{ $name }}, tap the button below to confirm your email. Once confirmed you can comment,
        and you'll start receiving the {{ $siteName }} Kabaddi newsletter. You can unsubscribe anytime.
      </p>
      <a href="{{ $confirmUrl }}"
         style="display:inline-block;background:#D4420A;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:9px">
        Confirm my email →
      </a>
      <p style="font-size:12px;line-height:1.6;color:#8a847b;margin:24px 0 0">
        If the button doesn't work, copy and paste this link into your browser:<br>
        <a href="{{ $confirmUrl }}" style="color:#D4420A;word-break:break-all">{{ $confirmUrl }}</a>
      </p>
      <p style="font-size:12px;line-height:1.6;color:#8a847b;margin:18px 0 0">
        This link expires in 3 days. If you didn't request this, just ignore the email — you won't be subscribed.
      </p>
    </div>
    <p style="text-align:center;font-size:11px;color:#a8a29a;margin:18px 0 0">© {{ date('Y') }} {{ $siteName }}</p>
  </div>
</body>
</html>
