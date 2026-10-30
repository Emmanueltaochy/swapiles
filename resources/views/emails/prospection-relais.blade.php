@php
    $eur = fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', ' ') . ' €';
    $lieu = $p->ville ?: $p->territoire;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Devenir point relais Swap’Îles</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:620px;margin:0 auto;padding:24px 16px;">
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="padding:22px 26px;background:#0f766e;color:#ffffff;">
            <div style="font-size:24px;font-weight:900;">Swap’Îles</div>
            <div style="font-size:13px;margin-top:4px;">L’application de seconde main des îles</div>
        </div>
        <div style="padding:26px;font-size:15px;line-height:1.65;color:#374151;">
            <p style="margin-top:0;">Bonjour,</p>
            <p>Je m’appelle Emmanuel Taochy, fondateur de Swap’Îles, l’application de seconde main née à La Réunion : nos membres achètent, vendent et donnent leurs articles près de chez eux.</p>
            <p>Beaucoup de nos membres à <strong>{{ $lieu }}</strong> cherchent un commerce de confiance pour déposer et retirer leurs colis. Votre commerce, <strong>{{ $p->nom }}</strong>, serait idéal comme <strong>point relais Swap’Îles</strong>.</p>
            <p style="margin-bottom:6px;"><strong>Ce que ça vous apporte :</strong></p>
            <ul style="margin-top:0;padding-left:20px;">
                <li><strong>Des clients en plus</strong> : chaque colis amène deux personnes chez vous, celle qui dépose et celle qui retire.</li>
                <li><strong>{{ $eur($commission) }} par colis</strong>, versé chaque mois avec un relevé automatique.</li>
                <li><strong>Rien à installer, pas d’argent à manipuler</strong> : vous vérifiez un code dans l’application, c’est tout. Le colis reste chez vous {{ $garde }} jours au plus.</li>
                <li><strong>Votre commerce mis en avant</strong> dans l’application, sur la carte des points relais.</li>
                <li><strong>Sans engagement</strong> : vous arrêtez quand vous voulez.</li>
            </ul>
            <p style="text-align:center;margin:24px 0;">
                <a href="{{ $lienPartenaire }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px;">Découvrir et devenir point relais</a>
            </p>
            <p>Vous pouvez aussi simplement <strong>répondre à ce mail</strong> ou m’appeler{!! ! empty($contact['telephone']) ? ' au <strong>' . e($contact['telephone']) . '</strong>' : '' !!} : je passe vous présenter tout ça en 10 minutes.</p>
            <p>Bien cordialement,<br><strong>Emmanuel Taochy</strong><br>Fondateur de Swap’Îles · swapiles.com</p>
        </div>
        <div style="padding:16px 26px;background:#f9fafb;font-size:12px;line-height:1.5;color:#6b7280;">
            Vous recevez ce message car votre commerce est référencé publiquement (OpenStreetMap ou votre site). Il s’agit d’un message unique : sans réponse de votre part, nous ne vous relancerons pas.
            <a href="{{ $lienDesinscription }}" style="color:#6b7280;">Ne plus être contacté</a>.<br>
            Swap’Îles · {{ config('partenaires.editeur.nom') }} · {{ config('partenaires.editeur.adresse') }}
        </div>
    </div>
</div>
</body>
</html>
