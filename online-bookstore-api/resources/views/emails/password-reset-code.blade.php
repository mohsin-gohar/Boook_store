<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your Folio password reset code</title>
</head>
<body style="margin:0;padding:24px;background:#f7f3eb;font-family:Georgia,'Times New Roman',serif;color:#101512;">
    <div style="max-width:520px;margin:0 auto;background:#fffdf8;border:1px solid #e2e8f0;border-radius:8px;padding:32px;">
        <p style="margin:0 0 4px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#a9792b;">Folio</p>
        <h1 style="margin:0 0 16px;font-size:22px;color:#12503c;">Reset your password</h1>

        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
            We received a request to reset the password for
            <strong>{{ $email }}</strong>.
        </p>

        <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">
            Use this six-digit code to continue:
        </p>

        <div style="margin:0 0 20px;padding:18px;text-align:center;background:#f7f3eb;border:1px dashed #a9792b;border-radius:6px;">
            <span style="font-size:34px;letter-spacing:0.32em;font-weight:700;color:#12503c;">{{ $code }}</span>
        </div>

        <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#5a6b62;">
            The code expires in 15 minutes and can only be used once.
            If you did not request a password reset you can safely ignore this
            e-mail — your password will not change.
        </p>

        <p style="margin:0;font-size:13px;line-height:1.6;color:#5a6b62;">
            — The Folio team
        </p>
    </div>
</body>
</html>
