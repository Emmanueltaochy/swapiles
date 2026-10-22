@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
    $dateTexte = $date->locale('fr')->translatedFormat('j F Y');
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Swap’Îles · Dossier partenaires</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1f2937; line-height: 1.4; }
    .page { padding: 15mm 16mm 16mm; page-break-after: always; }
    .page.fin { page-break-after: auto; }
    .pied { position: fixed; left: 16mm; right: 16mm; bottom: 7mm; font-size: 7pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 1.5mm; }
    h1 { font-size: 26pt; line-height: 1.1; color: #134e4a; margin: 0; }
    h2 { font-size: 17pt; color: #134e4a; margin: 0 0 1mm; }
    h3 { font-size: 10.5pt; color: #0f766e; margin: 5mm 0 1.5mm; }
    .chapeau { font-size: 10pt; color: #4b5563; margin: 0 0 4mm; }
    .sur { font-size: 8pt; font-weight: bold; color: #0f766e; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2mm; }
    p { margin: 0 0 2.5mm; }
    .petit { font-size: 8pt; color: #6b7280; }

    /* Couverture */
    .couv { background: #faf7f0; padding: 0; height: 296mm; overflow: hidden; }
    .couv-haut { padding: 22mm 18mm 0; }
    .couv-bande { background: #0f766e; color: #fff; padding: 14mm 18mm; margin-top: 30mm; }
    .couv-bande .t { font-size: 13pt; font-weight: bold; }
    .couv-bande .s { font-size: 9.5pt; opacity: .9; margin-top: 2mm; }
    .couv-bas { padding: 14mm 18mm; }
    .tag { display: inline-block; background: #ccfbf1; color: #134e4a; font-weight: bold; font-size: 8.5pt; border-radius: 3mm; padding: 1.2mm 3mm; }

    /* Chiffres clés */
    table.kpi { width: 100%; border-collapse: separate; border-spacing: 2.5mm; margin: 0 -2.5mm; }
    table.kpi td { background: #f0fdfa; border-radius: 3mm; padding: 3mm 3.5mm; vertical-align: top; width: 33.3%; }
    table.kpi .v { font-size: 16pt; font-weight: bold; color: #0f766e; line-height: 1.1; }
    table.kpi .l { font-size: 8.5pt; color: #374151; margin-top: 1mm; }
    table.kpi td.fort { background: #0f766e; }
    table.kpi td.fort .v, table.kpi td.fort .l { color: #fff; }

    /* Tableaux */
    table.t { width: 100%; border-collapse: collapse; margin-top: 1mm; }
    table.t th { text-align: left; font-size: 7.5pt; color: #6b7280; font-weight: bold; border-bottom: 1.5px solid #0f766e; padding: 1.5mm 2mm; }
    table.t td { border-bottom: 1px solid #e5e7eb; padding: 1.5mm 2mm; }
    table.t .num { text-align: right; white-space: nowrap; }
    table.t tr.total td { font-weight: bold; border-bottom: 0; background: #f0fdfa; }

    /* Étapes */
    table.etapes { width: 100%; border-collapse: separate; border-spacing: 2.5mm; margin: 0 -2.5mm; }
    table.etapes td { width: 25%; vertical-align: top; border: 1px solid #ccfbf1; border-radius: 3mm; padding: 3mm; font-size: 8.5pt; }
    .pastille { font-size: 15pt; font-weight: bold; color: #0f766e; line-height: 1; }
    table.etapes .t { font-weight: bold; color: #134e4a; margin: 2mm 0 1mm; }

    /* Formules */
    table.formules { width: 100%; border-collapse: separate; border-spacing: 2.5mm; margin: 0 -2.5mm; }
    table.formules td { width: 33.3%; vertical-align: top; border: 1px solid #d1d5db; border-radius: 3mm; padding: 4mm; }
    table.formules td.mise { border: 2px solid #0f766e; }
    table.formules .nom { font-size: 12pt; font-weight: bold; color: #134e4a; }
    table.formules .prix { font-size: 11pt; font-weight: bold; color: #0f766e; margin: 1.5mm 0; }
    table.formules .pour { font-size: 8pt; color: #6b7280; margin-bottom: 2.5mm; }
    table.formules ul { margin: 0; padding-left: 4mm; }
    table.formules li { margin-bottom: 1.5mm; font-size: 8.5pt; }

    .encadre { background: #f9fafb; border-left: 3px solid #0f766e; padding: 3mm 4mm; margin-top: 4mm; font-size: 8.5pt; color: #374151; }
    .contact { background: #134e4a; color: #fff; border-radius: 3mm; padding: 5mm 6mm; margin-top: 6mm; }
    .contact .n { font-size: 12pt; font-weight: bold; }
</style>
</head>
<body>
<div class="pied">Swap’Îles · Dossier partenaires · swapiles.com</div>

{{-- 1. Couverture --}}
<div class="page couv">
    <div class="couv-haut">
        <img src="{{ $logo }}" style="width: 52mm;" alt="Swap’Îles">
        <div style="margin-top: 26mm;" class="sur">Dossier partenaires</div>
        <h1>Le réemploi local,<br>au cœur des Outre-mer.</h1>
        <p class="chapeau" style="margin-top: 5mm; font-size: 12pt;">Swap’Îles, la plateforme de seconde main pensée pour les îles.</p>
        @if($ile)
            <span class="tag">Focus : {{ $ile }}</span>
        @endif
    </div>
    <div class="couv-bande">
        <div class="t">{{ $n($membres) }} membres · {{ $n($impact['articles']) }} articles réemployés · {{ $kg($impact['kg']) }} détournés des déchets</div>
        <div class="s">Chiffres réels de la plateforme au {{ $dateTexte }}.</div>
    </div>
    <div class="couv-bas">
        <p><strong>{{ $contact['nom'] }}</strong>, {{ mb_strtolower($contact['fonction']) }}</p>
        <p>{{ $contact['email'] }}@if($contact['telephone']) · {{ $contact['telephone'] }}@endif · swapiles.com</p>
    </div>
</div>

{{-- 2. Swap'Îles en bref --}}
<div class="page">
    <div class="sur">Qui sommes-nous</div>
    <h2>Swap’Îles en bref</h2>
    <p class="chapeau">Dans les îles, un objet qui ne sert plus finit souvent au placard ou à la déchetterie, pendant que d’autres habitants commandent le même en métropole et attendent des semaines. Swap’Îles relie les deux, sur place.</p>

    <p>Swap’Îles est une plateforme de seconde main, sur le web et en application iPhone et Android, dédiée à La Réunion, la Martinique, la Guadeloupe, la Guyane et Mayotte. Les habitants y vendent, échangent ou donnent leurs vêtements, objets de puériculture, équipements de maison ou de sport, entre voisins de la même île.</p>

    <h3>Comment ça marche</h3>
    <table class="etapes"><tr>
        <td><div class="pastille">1</div><div class="t">Déposer</div>Une photo, un prix : l’annonce est en ligne. Le dépôt est gratuit.</td>
        <td><div class="pastille">2</div><div class="t">Vendre, échanger, donner</div>Chacun choisit : vente, troc ou don.</td>
        <td><div class="pastille">3</div><div class="t">Remettre sur l’île</div>En main propre, en point relais chez un commerçant, ou par Colissimo.</td>
        <td><div class="pastille">4</div><div class="t">Payer en confiance</div>Paiement protégé : le vendeur est payé quand l’acheteur a reçu l’article.</td>
    </tr></table>

    <h3>Les chiffres clés</h3>
    <table class="kpi">
        <tr>
            <td class="fort"><div class="v">{{ $n($membres) }}</div><div class="l">membres inscrits<br>+{{ $n($nouveaux_30j) }} ces 30 derniers jours</div></td>
            <td><div class="v">{{ $n($annonces) }}</div><div class="l">annonces en ligne aujourd’hui</div></td>
            <td><div class="v">{{ $n($impact['articles']) }}</div><div class="l">articles réemployés depuis le lancement</div></td>
        </tr>
        <tr>
            <td><div class="v">{{ $n($installations) }}</div><div class="l">installations de l’application utilisées</div></td>
            <td><div class="v">{{ $n($iles) }}</div><div class="l">territoires avec des membres actifs</div></td>
            <td><div class="v">{{ $n($points_relais) }}</div><div class="l">points relais chez des commerçants</div></td>
        </tr>
    </table>

    @if(count($par_ile))
        <h3>Membres par territoire</h3>
        <table class="t">
            <tr><th>Territoire</th><th class="num">Membres</th><th class="num">Part</th><th class="num">Nouveaux (30 j)</th></tr>
            @foreach($par_ile as $l)
                <tr>
                    <td>{{ $l['ile'] }}</td>
                    <td class="num">{{ $n($l['membres']) }}</td>
                    <td class="num">{{ $membres > 0 ? round($l['membres'] / $membres * 100) : 0 }} %</td>
                    <td class="num">{{ $l['nouveaux'] > 0 ? '+' . $n($l['nouveaux']) : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</div>

{{-- 3. Impact --}}
<div class="page">
    <div class="sur">Impact</div>
    <h2>Ce que le réemploi a déjà évité</h2>
    <p class="chapeau">Chaque article qui change de main sur l’île, c’est un article neuf qui n’est pas fabriqué ni importé, et un objet qui ne part pas en déchet.</p>

    <table class="kpi">
        <tr>
            <td class="fort"><div class="v">{{ $n($impact['articles']) }}</div><div class="l">articles réemployés</div></td>
            <td><div class="v">{{ $kg($impact['kg']) }}</div><div class="l">détournés des déchets (estimation)</div></td>
            <td><div class="v">{{ $kg($impact['co2']) }}</div><div class="l">de CO₂e évités (estimation)<br>soit {{ $n($impact['km']) }} km en voiture</div></td>
        </tr>
    </table>
    <p class="petit">Ces 30 derniers jours : {{ $n($impact_30j['articles']) }} articles, {{ $kg($impact_30j['kg']) }}, {{ $kg($impact_30j['co2']) }} de CO₂e.</p>

    @if(count($par_type))
        <h3>Comment les articles ont trouvé preneur</h3>
        <table class="t">
            <tr><th>Mode</th><th class="num">Articles</th><th class="num">Poids</th><th class="num">CO₂e évité</th></tr>
            @foreach($par_type as $l)
                <tr><td>{{ $l['libelle'] }}</td><td class="num">{{ $n($l['articles']) }}</td><td class="num">{{ $kg($l['kg']) }}</td><td class="num">{{ $kg($l['co2']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if(count($par_categorie))
        <h3>Principales catégories</h3>
        <table class="t">
            <tr><th>Catégorie</th><th class="num">Articles</th><th class="num">Poids</th><th class="num">CO₂e évité</th></tr>
            @foreach($par_categorie as $l)
                <tr><td>{{ $l['libelle'] }}</td><td class="num">{{ $n($l['articles']) }}</td><td class="num">{{ $kg($l['kg']) }}</td><td class="num">{{ $kg($l['co2']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if(count($impact_par_ile) > 1)
        <h3>Par territoire</h3>
        <table class="t">
            <tr><th>Territoire</th><th class="num">Articles</th><th class="num">Poids</th><th class="num">CO₂e évité</th></tr>
            @foreach($impact_par_ile as $l)
                <tr><td>{{ $l['libelle'] }}</td><td class="num">{{ $n($l['articles']) }}</td><td class="num">{{ $kg($l['kg']) }}</td><td class="num">{{ $kg($l['co2']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="encadre">
        <strong>Méthode.</strong> Un article est compté une seule fois, quand il est vendu, échangé ou donné (ventes contestées exclues). Poids : celui indiqué par le vendeur, sinon un poids moyen par catégorie. CO₂e évité : fabrication d’un article neuf équivalent qui n’a pas eu lieu, avec des facteurs moyens prudents par catégorie inspirés de la Base Empreinte de l’ADEME. Équivalence voiture : {{ str_replace('.', ',', (string) config('impact.co2_par_km_voiture', 0.2)) }} kg CO₂e par km. Ce sont des estimations, pas une mesure certifiée.
    </div>

</div>

{{-- 4. Croissance (+ focus île) --}}
<div class="page">
    <div class="sur">Dynamique</div>
    <h2>Une communauté qui grandit</h2>
    <p class="chapeau">Évolution mois par mois, sur tous les territoires.</p>

    <table class="t">
        <tr><th>Mois</th><th class="num">Nouveaux membres</th><th class="num">Annonces publiées</th><th class="num">Ventes, échanges et dons</th></tr>
        @foreach($croissance as $m)
            <tr>
                <td>{{ $m['libelle'] }}@if($m['en_cours']) <span class="petit">(en cours)</span>@endif</td>
                <td class="num">{{ $n($m['inscriptions']) }}</td>
                <td class="num">{{ $n($m['annonces']) }}</td>
                <td class="num">{{ $n($m['ventes']) }}</td>
            </tr>
        @endforeach
    </table>

    @if($focus)
        <h3 style="margin-top: 9mm;">Focus : {{ $ile }}</h3>
        <table class="kpi">
            <tr>
                <td class="fort"><div class="v">{{ $n($focus['membres']) }}</div><div class="l">membres<br>+{{ $n($focus['nouveaux_30j']) }} ces 30 derniers jours</div></td>
                <td><div class="v">{{ $n($focus['annonces']) }}</div><div class="l">annonces en ligne</div></td>
                <td><div class="v">{{ $n($focus['points_relais']) }}</div><div class="l">points relais</div></td>
            </tr>
            <tr>
                <td><div class="v">{{ $n($focus['impact']['articles']) }}</div><div class="l">articles réemployés</div></td>
                <td><div class="v">{{ $kg($focus['impact']['kg']) }}</div><div class="l">détournés des déchets</div></td>
                <td><div class="v">{{ $kg($focus['impact']['co2']) }}</div><div class="l">de CO₂e évités</div></td>
            </tr>
        </table>
    @endif

    <h3 style="margin-top: 9mm;">Pourquoi s’associer à Swap’Îles</h3>
    <table class="t">
        <tr><td style="width: 35%;"><strong>Un impact local et mesuré</strong></td><td>Des chiffres concrets pour votre rapport RSE : articles réemployés, déchets et CO₂e évités, sur votre territoire.</td></tr>
        <tr><td><strong>Du passage en magasin</strong></td><td>Les points relais reçoivent vendeurs et acheteurs : deux visites par colis.</td></tr>
        <tr><td><strong>Une action visible</strong></td><td>Journées réemploi, publications communes, présence sur la plateforme.</td></tr>
        <tr><td><strong>Des données respectées</strong></td><td>Les rapports ne contiennent que des totaux anonymes, conformes au RGPD.</td></tr>
    </table>

</div>

{{-- 5. Formules et contact --}}
<div class="page fin">
    <div class="sur">Devenir partenaire</div>
    <h2>Trois façons d’agir avec nous</h2>
    <p class="chapeau">Chaque partenariat est construit avec vous, selon vos objectifs et vos territoires.</p>

    <table class="formules"><tr>
        @foreach($formules as $i => $f)
            <td class="{{ $i === 1 ? 'mise' : '' }}">
                <div class="nom">{{ $f['nom'] }}</div>
                <div class="prix">{{ $f['prix'] }}</div>
                <div class="pour">{{ $f['pour'] }}</div>
                <ul>
                    @foreach($f['inclus'] as $ligne)
                        <li>{{ $ligne }}</li>
                    @endforeach
                </ul>
            </td>
        @endforeach
    </tr></table>

    <h3>Les prochaines étapes</h3>
    <table class="etapes"><tr>
        <td><div class="pastille">1</div><div class="t">Un échange</div>15 minutes pour comprendre vos objectifs RSE.</td>
        <td><div class="pastille">2</div><div class="t">Une proposition</div>Une action adaptée à votre territoire et à votre budget.</td>
        <td><div class="pastille">3</div><div class="t">Le lancement</div>Point relais, journée réemploi ou partenariat annuel.</td>
        <td><div class="pastille">4</div><div class="t">Le suivi</div>Un rapport d’impact chiffré, régulier.</td>
    </tr></table>

    <div class="contact">
        <div class="n">Parlons-en</div>
        <p style="margin: 2mm 0 0;">{{ $contact['nom'] }}, {{ mb_strtolower($contact['fonction']) }} de Swap’Îles</p>
        <p style="margin: 0;">{{ $contact['email'] }}@if($contact['telephone']) · {{ $contact['telephone'] }}@endif · swapiles.com</p>
    </div>

    <p class="petit" style="margin-top: 5mm;">Swap’Îles est une entreprise : les partenariats font l’objet d’une facture et ne relèvent pas du mécénat. Chiffres extraits de la plateforme le {{ $dateTexte }}.</p>

</div>

</body>
</html>
