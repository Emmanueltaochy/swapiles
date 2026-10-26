@php
    $e = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $moisTexte = ucfirst($mois->locale('fr')->translatedFormat('F Y'));
    $editeur = config('partenaires.editeur');
    $commerce = $point->raison_sociale ?: $point->name;
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Relevé {{ $numero }}</title>
<style>
    @page { margin: 16mm 16mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 16pt; color: #134e4a; margin: 0 0 1mm; }
    .petit { font-size: 7.5pt; color: #6b7280; }
    table.entete { width: 100%; margin-top: 8mm; }
    table.entete td { width: 50%; vertical-align: top; padding: 0; }
    .bloc { border: 1px solid #e5e7eb; border-radius: 2mm; padding: 3mm 4mm; margin-right: 3mm; }
    .bloc.d { margin: 0 0 0 3mm; }
    .bloc .t { font-size: 7.5pt; font-weight: bold; color: #0f766e; text-transform: uppercase; margin-bottom: 1mm; }
    table.l { width: 100%; border-collapse: collapse; margin-top: 7mm; }
    table.l th { text-align: left; font-size: 7.5pt; color: #6b7280; border-bottom: 1.5px solid #0f766e; padding: 1.5mm 2mm; }
    table.l td { border-bottom: 1px solid #e5e7eb; padding: 1.5mm 2mm; }
    .num { text-align: right; white-space: nowrap; }
    table.tot { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 4mm; }
    table.tot td { padding: 1.5mm 2mm; }
    table.tot tr.g td { font-weight: bold; font-size: 11pt; color: #134e4a; border-top: 1.5px solid #0f766e; }
    .mention { margin-top: 8mm; background: #f9fafb; border-left: 3px solid #0f766e; padding: 3mm 4mm; font-size: 8pt; }
</style>
</head>
<body>
    <table style="width:100%;"><tr>
        <td><img src="{{ $logo }}" style="width: 38mm;" alt="Swap’Îles"></td>
        <td style="text-align:right;">
            <h1>{{ $autofacture ? 'Facture' : 'Relevé de commissions' }}</h1>
            <div>N° {{ $numero }}</div>
            <div class="petit">Période : {{ $moisTexte }} · Émis le {{ now()->format('d/m/Y') }}</div>
        </td>
    </tr></table>

    <table class="entete"><tr>
        <td><div class="bloc">
            <div class="t">{{ $autofacture ? 'Prestataire (point relais)' : 'Point relais' }}</div>
            <strong>{{ $commerce }}</strong><br>
            @if($point->raison_sociale && $point->raison_sociale !== $point->name){{ $point->name }}<br>@endif
            {{ $point->fullAddress() }}<br>
            @if($point->siret)SIRET : {{ $point->siret }}@endif
        </div></td>
        <td><div class="bloc d">
            <div class="t">{{ $autofacture ? 'Client' : 'Émis par' }}</div>
            <strong>{{ $editeur['nom'] }}</strong><br>
            {{ $editeur['adresse'] }}<br>
            SIRET : {{ $editeur['siret'] }}
        </div></td>
    </tr></table>

    <table class="l">
        <tr><th>Date</th><th>Référence</th><th>Article</th><th>Remise</th><th class="num">Commission</th></tr>
        @forelse($lignes as $l)
            <tr>
                <td>{{ optional($l['date'])->format('d/m/Y') }}</td>
                <td>{{ $l['reference'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($l['article'], 40) }}</td>
                <td>{{ $l['remise'] }}</td>
                <td class="num">{{ $e($l['montant']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="petit">Aucun colis remis ce mois-ci.</td></tr>
        @endforelse
    </table>

    <table class="tot">
        <tr><td>Colis remis</td><td class="num">{{ $colis }}</td></tr>
        @if($taux_tva > 0)
            <tr><td>Total HT</td><td class="num">{{ $e($ht) }}</td></tr>
            <tr><td>TVA {{ str_replace('.', ',', (string) $taux_tva) }} %</td><td class="num">{{ $e($tva) }}</td></tr>
            <tr class="g"><td>Total TTC à verser</td><td class="num">{{ $e($total) }}</td></tr>
        @else
            <tr class="g"><td>Total à verser</td><td class="num">{{ $e($total) }}</td></tr>
        @endif
    </table>

    <div class="mention">
        @if($autofacture)
            <strong>Autofacturation.</strong> Facture établie par {{ $editeur['nom'] }} au nom et pour le compte de {{ $commerce }}, conformément au mandat d’autofacturation accepté le {{ $point->charte_acceptee_at->format('d/m/Y') }} avec la charte des points relais Swap’Îles.<br>
        @endif
        @if($taux_tva <= 0)
            TVA non applicable{{ in_array($point->territoire, ['Guyane', 'Mayotte'], true) ? ' (art. 294 du CGI)' : ', art. 293 B du CGI' }}.<br>
        @endif
        Commission de {{ $e(config('pricing.relay_merchant_fee', 1)) }} par colis remis à l’acheteur, ou rendu au vendeur après le délai de garde.
        @if($versement && $versement->verse_at)
            <br><strong>Versé le {{ $versement->verse_at->format('d/m/Y') }}</strong> par virement{{ $versement->reference ? ' (réf. ' . $versement->reference . ')' : '' }}.
        @else
            <br>Versement par virement au début du mois suivant, dès {{ $e(config('relais.versement_minimum', 10)) }} de solde.
        @endif
    </div>
</body>
</html>
