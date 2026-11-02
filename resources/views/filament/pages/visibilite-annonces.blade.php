<x-filament-panels::page>
    @php
        $num = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $duree = function ($h) {
            if ($h === null) return '—';
            if ($h < 1) return 'moins d’1 h';
            if ($h < 48) return round($h) . ' h';
            return round($h / 24) . ' jours';
        };
        $verdict = function ($h, $annonces) {
            if ($h === null || $annonces < \App\Support\Remontees::PAGE_ACCUEIL) return ['⚪', 'Pas assez d’annonces pour remplir la première page.'];
            if ($h < 24) return ['🔥', 'La place en tête devient rare : un Booster payant peut avoir du sens.'];
            if ($h < 72) return ['🟠', 'Ça se resserre. À surveiller.'];
            return ['🟢', 'Trop tôt pour un Booster payant : chaque annonce reste longtemps visible.'];
        };
        $libelles = ['prix' => '📉 Baisse de prix (≥ 10 %)', 'rotation' => '🔄 Rotation des anciennes'];
    @endphp

    <style>
        .va-ligne{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:.55rem 0;border-bottom:1px solid rgba(148,163,184,.15);font-size:.88rem;}
        .va-ligne:last-child{border-bottom:0;}
        .va-ligne .d{font-weight:800;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .va-s{font-size:.75rem;opacity:.65;margin-top:.15rem;}
        .va-grille{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));}
        .va-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:.85rem 1rem;min-width:0;}
        .va-kpi .l{font-size:.75rem;font-weight:700;opacity:.65;}
        .va-kpi .v{font-size:1.4rem;font-weight:900;margin-top:.2rem;font-variant-numeric:tabular-nums;}
        .va-texte{font-size:.85rem;line-height:1.6;opacity:.85;}
    </style>

    <x-filament::section>
        <x-slot name="heading">⏱️ Temps en première page de l’accueil</x-slot>
        <p class="va-texte" style="margin-bottom:.5rem;">Combien de temps une nouvelle annonce reste dans les {{ \App\Support\Remontees::PAGE_ACCUEIL }} premières de l’accueil de chaque île. Quand ça tombe sous 1 jour, les vendeurs se battent pour être vus : c’est le moment de proposer un Booster payant.</p>
        @foreach($pages as $ile => $p)
            @php [$icone, $texte] = $verdict($p['heures'], $p['annonces']); @endphp
            <div class="va-ligne">
                <div>
                    <div><strong>{{ $ile }}</strong></div>
                    <div class="va-s">{{ $icone }} {{ $texte }} · {{ $num($p['nouvelles_7j']) }} nouvelles annonces en 7 jours</div>
                </div>
                <div class="d">{{ $duree($p['heures']) }}</div>
            </div>
        @endforeach
    </x-filament::section>

    <div class="va-grille">
        @foreach($bilan['motifs'] as $motif => $m)
            <div class="va-kpi">
                <div class="l">{{ $libelles[$motif] ?? $motif }} · 30 j</div>
                <div class="v">{{ $num($m['annonces']) }} annonces</div>
                <div class="va-s">+{{ $num($m['vues_gagnees']) }} vues en moyenne depuis la remontée</div>
                <div class="va-s">{{ $num($m['vendues']) }} vendues ensuite{{ $m['taux'] !== null ? ' (' . $m['taux'] . ' %)' : '' }}</div>
            </div>
        @endforeach
        <div class="va-kpi">
            <div class="l">💡 Conseils de prix · 30 j</div>
            <div class="v">{{ $num($bilan['conseils']['envoyes']) }} envoyés</div>
            <div class="va-s">{{ $num($bilan['conseils']['prix_baisse']) }} vendeurs ont baissé le prix ensuite</div>
            <div class="va-s">{{ $num($bilan['conseils']['vendues']) }} annonces vendues ensuite</div>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Comment ça marche</x-slot>
        <div class="va-texte">
            <p>• <strong>Baisse de prix d’au moins 10 %</strong> : l’annonce repasse en tête (une fois toutes les 2 semaines au plus) et ceux qui l’ont en favori sont prévenus.</p>
            <p>• <strong>Rotation</strong> : chaque heure, sur chaque île, environ 1 annonce de plus d’une semaine est remise en avant pour 4 nouvelles publiées. Chacune son tour, et seulement les vendeurs venus sur le site ce mois-ci.</p>
            <p>• <strong>Conseil de prix</strong> : après 14 jours et au moins {{ \App\Support\ConseilsPrix::VUES_MIN }} vues sans acheteur, le vendeur reçoit une notification avec les prix repères (ou des astuces). Une seule par vendeur et par semaine.</p>
            <p>• Aucun bouton de remontée manuelle : personne ne peut envahir l’accueil.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
