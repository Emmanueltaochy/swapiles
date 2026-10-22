<x-filament-panels::page>
    @php
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
    @endphp

    <x-filament::section>
        <x-slot name="heading">Ce qu’il y a dans le PDF (5 pages)</x-slot>
        <div style="font-size:.88rem;line-height:1.7;">
            <p>1. <strong>Couverture</strong> : {{ $n($d['membres']) }} membres · {{ $n($d['impact']['articles']) }} articles réemployés · {{ $kg($d['impact']['kg']) }} détournés des déchets.</p>
            <p>2. <strong>Swap’Îles en bref</strong> : le constat, comment ça marche, les chiffres clés, les membres par île.</p>
            <p>3. <strong>Impact</strong> : articles, déchets et CO₂e évités ({{ $kg($d['impact']['co2']) }}), par mode, par catégorie, par île, avec la méthode de calcul.</p>
            <p>4. <strong>Dynamique</strong> : les 6 derniers mois, plus une page « Focus » sur une île si vous la choisissez.</p>
            <p>5. <strong>Devenir partenaire</strong> : les 3 formules, les prochaines étapes, votre contact ({{ $d['contact']['email'] }}).</p>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Bon à savoir</x-slot>
        <div style="font-size:.85rem;line-height:1.6;opacity:.8;">
            <p>Les chiffres sont recalculés à chaque téléchargement : téléchargez-le juste avant de l’envoyer.</p>
            <p>Uniquement des totaux anonymes : aucun nom ni aucune adresse de membre.</p>
            <p>Les prix des formules affichent « Sur devis » tant qu’aucun tarif n’est fixé.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
