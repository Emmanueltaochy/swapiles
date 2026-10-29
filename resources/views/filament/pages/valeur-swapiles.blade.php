<x-filament-panels::page>
    @php
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $eur2 = fn ($v) => \App\Support\TableauDeBord::euros((float) $v);
        $num = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $r = $v['revenus'];
        $m = $v['multiple'];
        $h = $v['hypotheses'];
    @endphp

    <style>
        .vs-grille{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));}
        .vs-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:.85rem 1rem;min-width:0;}
        .vs-kpi .l{font-size:.75rem;font-weight:700;opacity:.65;}
        .vs-kpi .v{font-size:1.4rem;font-weight:900;margin-top:.2rem;font-variant-numeric:tabular-nums;}
        .vs-kpi .s{font-size:.72rem;font-weight:600;opacity:.6;margin-top:.15rem;}
        .vs-ligne{display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid rgba(148,163,184,.15);font-size:.88rem;}
        .vs-ligne span:last-child{font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .vs-texte{font-size:.85rem;line-height:1.6;opacity:.85;}
        .vs-texte p{margin:.4rem 0;}
        .vs-total{font-size:clamp(1.35rem,6.5vw,2rem);font-weight:900;font-variant-numeric:tabular-nums;white-space:nowrap;}
    </style>

    <x-filament::section>
        <x-slot name="heading">💎 Valeur estimée aujourd’hui</x-slot>
        <div class="vs-total">
            {{ $eur($v['fourchette']['bas']) }} – {{ $eur($v['fourchette']['haut']) }}
        </div>
        <p class="vs-texte" style="margin-top:.4rem;">
            @if($v['methode'] === 'revenus')
                Calculée surtout sur vos <strong>revenus</strong> : {{ $eur2($r['rythme_annuel']) }} par an au rythme actuel, × {{ str_replace('.', ',', $m['bas']) }} à {{ str_replace('.', ',', $m['haut']) }} ({{ $m['libelle'] }}).
            @else
                Les revenus sont encore trop faibles pour porter la valeur : elle repose sur la <strong>technologie</strong> (site, appli, paiement, relais) et les <strong>{{ $num($v['membres_actifs']) }} membres actifs</strong>. C’est normal au démarrage : ce sont les revenus réguliers qui la feront monter.
            @endif
        </p>
    </x-filament::section>

    <div class="vs-grille">
        <div class="vs-kpi"><div class="l">Revenus Swap’Îles · 30 jours</div><div class="v">{{ $eur2($r['trente_jours']) }}</div><div class="s">protection + relais, crédits parrainage déduits</div></div>
        <div class="vs-kpi"><div class="l">Revenus · 12 derniers mois</div><div class="v">{{ $eur2($r['douze_mois']) }}</div><div class="s">mois complets</div></div>
        <div class="vs-kpi"><div class="l">Croissance des revenus</div><div class="v">{{ $r['croissance'] === null ? '—' : ($r['croissance'] >= 0 ? '+' : '') . $r['croissance'] . ' %' }}</div><div class="s">3 derniers mois / 3 précédents</div></div>
        <div class="vs-kpi"><div class="l">Membres actifs · 30 jours</div><div class="v">{{ $num($v['membres_actifs']) }}</div><div class="s">sur {{ $num($v['membres']) }} membres</div></div>
        <div class="vs-kpi"><div class="l">Annonces en ligne</div><div class="v">{{ $num($v['annonces_en_ligne']) }}</div></div>
        <div class="vs-kpi"><div class="l">Ventes payées en ligne · 30 j</div><div class="v">{{ $num($v['ventes_en_ligne_30j']) }}</div><div class="s">{{ $v['part_en_ligne'] === null ? 'aucune vente sur 30 jours' : $v['part_en_ligne'] . ' % des ventes (le reste en espèces)' }}</div></div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Le détail du calcul</x-slot>
        <p class="vs-texte" style="margin-bottom:.5rem;"><strong>1. Par les revenus</strong> (ce que regarde un acheteur en premier)</p>
        <div class="vs-ligne"><span>Revenus des 3 derniers mois complets</span><span>{{ $eur2($r['trimestre']) }}</span></div>
        <div class="vs-ligne"><span>Rythme annuel (× 4)</span><span>{{ $eur2($r['rythme_annuel']) }}</span></div>
        <div class="vs-ligne"><span>Multiple retenu : {{ $m['libelle'] }}</span><span>× {{ str_replace('.', ',', $m['bas']) }} à {{ str_replace('.', ',', $m['haut']) }}</span></div>
        <div class="vs-ligne"><span>Valeur par les revenus</span><span>{{ $eur($v['par_revenus']['bas']) }} – {{ $eur($v['par_revenus']['haut']) }}</span></div>

        <p class="vs-texte" style="margin:1rem 0 .5rem;"><strong>2. Par les actifs</strong> (valeur plancher quand les revenus sont faibles)</p>
        <div class="vs-ligne"><span>Technologie (hypothèse prudente)</span><span>{{ $eur($h['technologie'][0]) }} – {{ $eur($h['technologie'][1]) }}</span></div>
        <div class="vs-ligne"><span>{{ $num($v['membres_actifs']) }} membres actifs × {{ $h['par_membre_actif'][0] }} à {{ $h['par_membre_actif'][1] }} €</span><span>{{ $eur($v['membres_actifs'] * $h['par_membre_actif'][0]) }} – {{ $eur($v['membres_actifs'] * $h['par_membre_actif'][1]) }}</span></div>
        <div class="vs-ligne"><span>Valeur par les actifs</span><span>{{ $eur($v['par_actifs']['bas']) }} – {{ $eur($v['par_actifs']['haut']) }}</span></div>

        <p class="vs-texte" style="margin-top:.75rem;">On garde la plus haute des deux méthodes. Montants arrondis au millier inférieur.</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">🚀 Ce qui fait monter la valeur</x-slot>
        <div class="vs-texte">
            <p>💶 <strong>Chaque 1 000 € de revenus en plus par mois</strong> ajoute environ <strong>{{ $eur($v['effet_1000_par_mois']['bas']) }} à {{ $eur($v['effet_1000_par_mois']['haut']) }}</strong> de valeur, au rythme de croissance actuel.</p>
            <p>📈 <strong>Une croissance régulière</strong> fait passer le multiple de × 1 à × 4.</p>
            <p>🛡️ <strong>Plus de ventes payées en ligne</strong> : ce sont les seules qui rapportent ({{ $v['part_en_ligne'] === null ? '—' : $v['part_en_ligne'] . ' %' }} aujourd’hui).</p>
            <p>🏪 <strong>Des contrats avec des partenaires</strong> (réseau de points relais) : un actif difficile à copier.</p>
            <p>🏢 <strong>Une société propre</strong> : SAS, marque et code au nom de la société, comptes clairs. Une entreprise individuelle se revend moins bien.</p>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Bon à savoir</x-slot>
        <div class="vs-texte">
            <p>Les charges (hébergement, IA, frais bancaires) ne sont pas dans Swap’Îles : un acheteur regardera aussi le <strong>bénéfice</strong>, qui fera baisser ou monter ce chiffre.</p>
            <p>Les hypothèses (technologie, valeur d’un membre, multiples) sont volontairement prudentes et réglables dans <code>config/valeur.php</code>. Seule une offre réelle, ou un expert-comptable, fixe un prix.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
