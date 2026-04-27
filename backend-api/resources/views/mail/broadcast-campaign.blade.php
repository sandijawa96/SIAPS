<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:680px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
            <div style="padding:24px 28px;background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 55%,#0f766e 100%);color:#ffffff;">
                <div style="font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;opacity:0.9;">
                    {{ strtoupper($type) }}
                </div>
                <div style="margin-top:12px;font-size:28px;font-weight:700;line-height:1.25;">
                    {{ $title }}
                </div>
            </div>

            <div style="padding:28px;">
                <div style="font-size:15px;line-height:1.8;color:#334155;white-space:pre-line;">{{ $messageBody }}</div>

                @if (!empty($ctaLabel) && !empty($ctaUrl))
                    <div style="margin-top:28px;">
                        <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;">
                            {{ $ctaLabel }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
