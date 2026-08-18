@php $__brandName = (string) brand_name(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $__brandName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    {{-- Brand header --}}
                    <tr>
                        <td align="center" style="padding:24px;border-bottom:1px solid #f0f0f0;font-size:20px;font-weight:700;color:#075E54;">
                            {{ $__brandName }}
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px 32px;">
                            <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;">Welcome to {{ $__brandName }}</h1>

                            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">Hi {{ $name }},</p>

                            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">
                                An administrator created an account for you on <strong>{{ $__brandName }}</strong>. Use the
                                credentials below to sign in.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="background:#f9fafb;border:1px solid #eef0f2;border-radius:8px;margin:0 0 18px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:14px;line-height:1.7;">
                                        <strong>Email:</strong> {{ $email }}
                                        @if (!empty($plainPassword))
                                            <br><strong>Temporary password:</strong>
                                            <span style="font-family:Menlo,Consolas,monospace;background:#eef0f2;padding:2px 6px;border-radius:4px;">{{ $plainPassword }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if (!empty($plainPassword))
                                <p style="margin:0 0 14px;font-size:13px;line-height:1.6;color:#6b7280;">
                                    You'll be asked to set a new password on your first login.
                                </p>
                            @endif

                            {{-- Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
                                <tr>
                                    <td align="center" style="border-radius:8px;background:#075E54;">
                                        <a href="{{ $loginUrl }}" target="_blank"
                                           style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                                            Sign in to {{ $__brandName }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            @if (!empty($resetUrl))
                                <p style="margin:0 0 14px;font-size:12.5px;line-height:1.6;color:#6b7280;">
                                    Prefer to set your own password?
                                    <a href="{{ $resetUrl }}" style="color:#075E54;">Choose a password here</a>.
                                </p>
                            @endif

                            <p style="margin:0;font-size:12.5px;line-height:1.6;color:#6b7280;">
                                If you weren't expecting this email, you can safely ignore it — no further action is needed.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:18px 24px;border-top:1px solid #f0f0f0;font-size:11.5px;color:#9ca3af;">
                            © {{ date('Y') }} {{ $__brandName }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
