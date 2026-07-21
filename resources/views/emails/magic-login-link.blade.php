@php
    $url = route('magic.login.verify', $loginToken->token);
    $name = $loginToken->user?->name ?: 'membre Swap Îles';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accès à votre compte Swap Îles</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="padding:28px;text-align:center;background:#0f766e;color:#ffffff;">
                <div style="font-size:30px;font-weight:900;">Swap Îles</div>
                <div style="font-size:14px;margin-top:6px;">La marketplace seconde main des îles</div>
            </div>

            <div style="padding:30px;">
                @if($migrationMode ?? false)
                    <h1 style="font-size:24px;margin:0 0 18px;color:#111827;">
                        Swap Îles évolue 🌴
                    </h1>

                    <p style="font-size:16px;line-height:1.7;color:#374151;">
                        Bonjour {{ $name }},
                    </p>

                    <p style="font-size:16px;line-height:1.7;color:#374151;">
                        Nous avons lancé une nouvelle version de Swap Îles afin de rendre l’achat, la vente, l’échange et le don encore plus simples entre les îles.
                    </p>

                    <p style="font-size:16px;line-height:1.7;color:#374151;">
                        Pour retrouver votre compte, cliquez simplement sur le bouton ci-dessous. Vous serez connecté automatiquement et pourrez accéder à votre espace personnel.
                    </p>

                    <p style="font-size:16px;line-height:1.7;color:#374151;">
                        Une fois connecté, nous vous recommandons de vérifier vos informations et de définir un nouveau mot de passe si l’option est proposée.
                    </p>
                @else
                    <h1 style="font-size:24px;margin:0 0 18px;color:#111827;">
                        Accédez à votre compte
                    </h1>

                    <p style="font-size:16px;line-height:1.7;color:#374151;">
                        Bonjour {{ $name }}, cliquez sur le bouton ci-dessous pour accéder à votre compte Swap Îles.
                    </p>
                @endif

                <div style="text-align:center;margin-top:28px;">
                    <a href="{{ $url }}"
                       style="display:inline-block;background:#0f766e;color:#ffffff !important;text-decoration:none;font-weight:800;padding:14px 24px;border-radius:16px;">
                        🔐 Accéder à mon compte
                    </a>
                </div>

                <p style="font-size:13px;line-height:1.6;color:#6b7280;margin-top:24px;">
                    Ce lien est personnel et sécurisé. Il expirera automatiquement.
                </p>

                <p style="font-size:12px;line-height:1.6;color:#9ca3af;margin-top:18px;">
                    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                    <span style="word-break:break-all;">{{ $url }}</span>
                </p>
            </div>

            <div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;line-height:1.6;text-align:center;">
                Merci de faire partie de la communauté Swap Îles.<br>
                <strong>L’équipe Swap Îles</strong><br>
                <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
            </div>
        </div>
    </div>
</body>
</html>
