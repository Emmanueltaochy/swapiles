<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="padding:28px;text-align:center;background:#0f766e;color:#ffffff;">
                <div style="font-size:30px;font-weight:900;">Swap’Îles</div>
                <div style="font-size:14px;margin-top:6px;">La marketplace seconde main des îles</div>
            </div>

            <div style="padding:30px;">
                <h1 style="font-size:24px;margin:0 0 20px;color:#111827;">{{ $subject }}</h1>

                <div style="font-size:16px;line-height:1.7;color:#374151;">
                    {!! $html !!}
                </div>

                @if($buttonUrl && $buttonLabel)
                    <div style="text-align:center;margin-top:28px;">
                        <a href="{{ $buttonUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff !important;text-decoration:none;font-weight:800;padding:14px 24px;border-radius:16px;">
                            {{ $buttonLabel }}
                        </a>
                    </div>
                @endif
            </div>

            <div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;line-height:1.6;text-align:center;">
                Merci de faire partie de la communauté Swap’Îles.<br>
                <strong>L’équipe Swap’Îles</strong><br>
                <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
            </div>
        </div>
    </div>
</body>
</html>
