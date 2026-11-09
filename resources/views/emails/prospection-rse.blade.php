@php
    $nb = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $lieu = $p->ville ?: $p->territoire;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Swap’Îles : réemploi et pouvoir d’achat</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:620px;margin:0 auto;padding:24px 16px;">
    <div style="background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="padding:22px 26px;background:#0f766e;color:#ffffff;">
            <div style="font-size:24px;font-weight:900;">Swap’Îles</div>
            <div style="font-size:13px;margin-top:4px;">La seconde main des Outre-mer : acheter, vendre, donner, échanger</div>
        </div>
        <div style="padding:26px;font-size:15px;line-height:1.65;color:#374151;">
            <p style="margin-top:0;">Bonjour,</p>
            @if($relance)
                <p>Je me permets de revenir vers vous au sujet de mon message du {{ $p->contacte_at?->translatedFormat('j F') ?? 'mois dernier' }}, qui a pu se perdre parmi vos courriers.</p>
                <p>En quelques mots : <strong>Swap’Îles</strong> est l’application de seconde main née à La Réunion. Les habitants y achètent, vendent, <strong>donnent</strong> et échangent leurs objets près de chez eux.@if($chiffres['membres'] > 0) Nous comptons déjà <strong>{{ $nb($chiffres['membres']) }} membres</strong> en {{ $p->territoire === 'La Réunion' ? 'Réunion' : $p->territoire }}.@endif</p>
                <p>Je serais heureux d’échanger 15 minutes avec vous sur ce que nous pourrions faire ensemble pour {{ $p->type === 'ccas' ? 'les familles que vous accompagnez' : 'vos habitants' }}.</p>
            @else
                <p>Je m’appelle {{ $signature['nom'] }}, fondateur de <strong>Swap’Îles</strong>, l’application de seconde main née à La Réunion : les habitants y achètent, vendent, <strong>donnent</strong> et échangent leurs objets près de chez eux.</p>
                @if($chiffres['membres'] > 0)
                    <p>{{ $p->territoire === 'La Réunion' ? 'À La Réunion' : 'En ' . $p->territoire }}, <strong>{{ $nb($chiffres['membres']) }} habitants</strong> l’utilisent déjà{{ $chiffres['annonces'] > 0 ? ', avec ' . $nb($chiffres['annonces']) . ' annonces en ligne' : '' }}{{ $chiffres['dons'] > 0 ? ' dont ' . $nb($chiffres['dons']) . ' objets donnés gratuitement' : '' }}.</p>
                @endif

                @if($p->type === 'ccas')
                    <p>Pour les familles que vous accompagnez, c’est une façon concrète de <strong>s’équiper gratuitement ou à petit prix</strong> : vêtements d’enfants, puériculture, électroménager, meubles… donnés ou revendus par d’autres habitants, sans frais d’inscription.</p>
                @elseif($p->type === 'epci')
                    <p>Chaque objet revendu ou donné localement, c’est <strong>un objet de moins à collecter et à traiter</strong>. Swap’Îles fait vivre le réemploi sur votre territoire, et nous pouvons vous fournir des chiffres d’impact (objets réemployés, kilos et CO₂ évités) pour vos bilans.</p>
                @else
                    <p>C’est un outil simple pour le <strong>pouvoir d’achat</strong> de vos habitants et pour la <strong>réduction des déchets</strong>, sans aucun coût pour {{ in_array($p->type, ['mairie']) ? 'la commune' : 'votre organisme' }}.</p>
                @endif

                <p style="margin-bottom:6px;"><strong>Quelques pistes, selon vos besoins :</strong></p>
                <ul style="margin-top:0;padding-left:20px;">
                    <li>faire connaître l’application à vos habitants (bulletin, réseaux, accueil) ;</li>
                    <li>organiser une opération de dons ou un vide-grenier en ligne avec vos services ;</li>
                    <li>recevoir des chiffres d’impact du réemploi sur votre territoire.</li>
                </ul>
            @endif

            <p style="text-align:center;margin:24px 0;">
                <a href="{{ $lien }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px;">Découvrir la démarche</a>
            </p>
            <p><strong>Répondez simplement à ce mail</strong>{!! ! empty($contact['telephone']) ? ' ou appelez-moi au <strong>' . e($contact['telephone']) . '</strong>' : '' !!} : je vous réponds personnellement.</p>
            <p>Bien cordialement,<br><strong>{{ $signature['nom'] }}</strong><br>{{ $signature['fonction'] }} · swapiles.com</p>
        </div>
        <div style="padding:16px 26px;background:#f9fafb;font-size:12px;line-height:1.5;color:#6b7280;">
            Vous recevez ce message car l’adresse de votre organisme est publiée dans l’annuaire officiel de l’administration (service-public.fr).
            {{ $relance ? 'C’est notre dernier message : sans réponse, nous ne vous écrirons plus.' : 'Sans réponse, nous vous enverrons au plus un rappel.' }}
            <a href="{{ $lienDesinscription }}" style="color:#6b7280;">Ne plus être contacté</a>.<br>
            Swap’Îles · {{ config('partenaires.editeur.nom') }} · {{ config('partenaires.editeur.adresse') }}
        </div>
    </div>
</div>
</body>
</html>
