<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Verifikasi OTP</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background-color:#1d4ed8;padding:20px 24px;">
                            <h1 style="margin:0;font-size:18px;color:#ffffff;">Portal Pernyataan Benturan Kepentingan</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                Halo,
                            </p>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                Gunakan kode verifikasi (OTP) berikut untuk melanjutkan registrasi akun Anda:
                            </p>

                            <div style="margin:24px 0;text-align:center;">
                                <span style="display:inline-block;font-size:32px;letter-spacing:8px;font-weight:bold;color:#1d4ed8;background-color:#eff6ff;border:1px dashed #93c5fd;border-radius:8px;padding:16px 24px;">
                                    {{ $code }}
                                </span>
                            </div>

                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                                Kode ini berlaku selama <strong>{{ $expiryMinutes }} menit</strong> sejak email ini dikirim.
                            </p>
                            <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#b91c1c;">
                                Demi keamanan, <strong>jangan bagikan kode ini</strong> kepada siapa pun. Petugas kami tidak akan pernah meminta kode OTP Anda.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                Jika Anda tidak merasa melakukan registrasi, abaikan email ini.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb;padding:16px 24px;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">
                                FI/SP/GRC/973 &mdash; Rev. 01
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
