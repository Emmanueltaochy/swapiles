<x-filament-panels::page>
    @php
        $e = fn ($m) => $m === null ? '—' : \App\Support\TableauDeBord::euros((float) $m);
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $maxJour = max(1, collect($jours)->max('ventes'));
        $maxMois = max(1, collect($mois)->max('volume_en_ligne'));
    @endphp
    <style>
        .dv-kpis{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr))}
        @media(max-width:1100px){.dv-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
        .dv-k{border:1px solid rgba(148,163,184,.25);border-radius:.9rem;padding:.85rem 1rem}
        .dv-l{font-size:.75rem;font-weight:700;opacity:.6}
        .dv-v{font-size:1.5rem;font-weight:800;margin-top:.2rem}
        .dv-s{font-size:.72rem;opacity:.55;margin-top:.15rem}
        .dv-t{width:100%;font-size:.85rem;border-collapse:collapse}
        .dv-t th{text-align:left;font-size:.72rem;opacity:.6;font-weight:700;padding:.4rem .5rem;border-bottom:1px solid rgba(148,163,184,.3)}
        .dv-t td{padding:.45rem .5rem;border-bottom:1px solid rgba(148,163,184,.12)}
        .dv-t td.num,.dv-t th.num{text-align:right;white-space:nowrap}
        .dv-2{display:grid;gap:1rem;grid-template-columns:1fr 1fr}
        @media(max-width:1100px){.dv-2{grid-template-columns:1fr}}
        .dv-barre{height:.5rem;border-radius:999px;background:#0d9488}
        .dv-vide{font-size:.85rem;opacity:.6}
    </style>

    {{-- 1. Croissance : ce que regarde un acheteur --}}
    <x-filament::section>
        <x-slot name="heading">📈 Croissance de l’entreprise</x-slot>
        <x-slot name="description">Les indicateurs qu’un acheteur ou un investisseur demande en premier.</x-slot>
        <div class="dv-kpis">
            <div class="dv-k"><div class="dv-l">Membres</div><div class="dv-v">{{ $n($c['membres']) }}</div><div class="dv-s">{{ $n($c['membres_actifs_30j']) }} actifs sur 30 j ({{ $c['taux_activite'] }} %)</div></div>
            <div class="dv-k"><div class="dv-l">Volume de ventes en ligne (GMV)</div><div class="dv-v">{{ $e($c['gmv_en_ligne']) }}</div><div class="dv-s">{{ $n($c['ventes_en_ligne']) }} ventes · panier moyen {{ $e($c['panier_moyen']) }}</div></div>
            <div class="dv-k"><div class="dv-l">Revenus Swap’Îles</div><div class="dv-v" style="color:#0d9488;">{{ $e($c['revenus']) }}</div><div class="dv-s">taux de prélèvement {{ str_replace('.', ',', $c['take_rate']) }} %</div></div>
            <div class="dv-k"><div class="dv-l">Annonces en ligne</div><div class="dv-v">{{ $n($c['annonces_en_ligne']) }}</div><div class="dv-s">{{ $n($c['vendeurs_actifs']) }} vendeurs actifs</div></div>
            <div class="dv-k"><div class="dv-l">Annonces vendues (12 mois)</div><div class="dv-v">{{ $c['taux_vente_annonces'] }} %</div><div class="dv-s">délai moyen de vente : {{ $c['delai_vente_jours'] !== null ? str_replace('.', ',', $c['delai_vente_jours']) . ' j' : '—' }}</div></div>
            <div class="dv-k"><div class="dv-l">Acheteurs fidèles</div><div class="dv-v">{{ $c['acheteurs_recurrents'] }} %</div><div class="dv-s">ont acheté 2 fois ou plus ({{ $n($c['acheteurs']) }} acheteurs)</div></div>
            <div class="dv-k"><div class="dv-l">Appli installée</div><div class="dv-v">{{ $n($c['installations_appli']) }}</div><div class="dv-s">installations ouvertes (iPhone + Android)</div></div>
            <div class="dv-k"><div class="dv-l">Articles réemployés</div><div class="dv-v">{{ $n($c['articles_reemployes']) }}</div><div class="dv-s">objets qui ont trouvé une 2ᵉ vie</div></div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">📅 Mois par mois</x-slot>
        <div style="overflow-x:auto;">
            <table class="dv-t">
                <thead><tr><th>Mois</th><th class="num">Inscriptions</th><th class="num">Annonces</th><th class="num">Ventes en ligne</th><th class="num">Ventes main propre</th><th>Volume en ligne</th><th class="num">Revenus</th></tr></thead>
                <tbody>
                    @foreach($mois as $m)
                        <tr>
                            <td>{{ $m['libelle'] }}</td>
                            <td class="num">{{ $n($m['inscriptions']) }}</td>
                            <td class="num">{{ $n($m['annonces']) }}</td>
                            <td class="num">{{ $n($m['ventes_en_ligne']) }}</td>
                            <td class="num">{{ $n($m['ventes_main_propre']) }}</td>
                            <td style="min-width:160px;">
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="dv-barre" style="width:{{ round($m['volume_en_ligne'] / $maxMois * 100) }}%;min-width:2px;"></div>
                                    <span style="white-space:nowrap;">{{ $e($m['volume_en_ligne']) }}</span>
                                </div>
                            </td>
                            <td class="num">{{ $e($m['revenus']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- 2. Marché : ce qui intéresse marques et enseignes --}}
    <x-filament::section>
        <x-slot name="heading">🛍️ Le marché de l’occasion par catégorie</x-slot>
        <x-slot name="description">Offre (annonces, prix), envie (favoris) et ventes. Ce qu’une enseigne ou une marque veut savoir.</x-slot>
        @if($categories)
            <div style="overflow-x:auto;">
                <table class="dv-t">
                    <thead><tr><th>Catégorie</th><th class="num">Annonces en ligne</th><th class="num">Prix médian</th><th class="num">Favoris</th><th class="num">Ventes</th><th class="num">Prix de vente moyen</th></tr></thead>
                    <tbody>
                        @foreach($categories as $l)
                            <tr><td>{{ $l['categorie'] }}</td><td class="num">{{ $n($l['annonces']) }}</td><td class="num">{{ $e($l['prix_median']) }}</td><td class="num">{{ $n($l['favoris']) }}</td><td class="num">{{ $n($l['ventes']) }}</td><td class="num">{{ $e($l['prix_vente_moyen']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="dv-vide">Pas encore assez d’annonces par catégorie.</p>
        @endif
    </x-filament::section>

    <div class="dv-2">
        <x-filament::section>
            <x-slot name="heading">🏝️ Par île</x-slot>
            @if($iles)
                <table class="dv-t">
                    <thead><tr><th>Île</th><th class="num">Membres</th><th class="num">Annonces</th><th class="num">Ventes</th><th class="num">Panier moyen</th></tr></thead>
                    <tbody>
                        @foreach($iles as $l)
                            <tr><td>{{ $l['ile'] }}</td><td class="num">{{ $n($l['membres']) }}</td><td class="num">{{ $n($l['annonces_en_ligne']) }}</td><td class="num">{{ $n($l['ventes']) }}</td><td class="num">{{ $e($l['panier_moyen']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="dv-vide">Pas encore assez de membres par île.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">🏷️ Marques les plus présentes</x-slot>
            @if($marques)
                <table class="dv-t">
                    <thead><tr><th>Marque</th><th class="num">Annonces</th><th class="num">Prix médian</th></tr></thead>
                    <tbody>
                        @foreach($marques as $l)
                            <tr><td>{{ $l['marque'] }}</td><td class="num">{{ $n($l['annonces']) }}</td><td class="num">{{ $e($l['prix_median']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="dv-vide">Pas encore de marque avec au moins {{ \App\Support\DonneesMarche::SEUIL_ANONYMAT }} annonces.</p>
            @endif
        </x-filament::section>
    </div>

    <div class="dv-2">
        <x-filament::section>
            <x-slot name="heading">🔎 Demande non satisfaite (90 jours)</x-slot>
            <x-slot name="description">Cherché, mais introuvable sur Swap’Îles : une vraie information de marché.</x-slot>
            @if($demande)
                <table class="dv-t">
                    <thead><tr><th>Recherche</th><th class="num">Recherches</th><th class="num">Personnes</th></tr></thead>
                    <tbody>
                        @foreach($demande as $l)
                            <tr><td>{{ $l['terme'] }}</td><td class="num">{{ $n($l['recherches']) }}</td><td class="num">{{ $n($l['personnes']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="dv-vide">Aucun terme cherché par au moins {{ \App\Support\DonneesMarche::SEUIL_ANONYMAT }} personnes différentes.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">🤝 Comment les articles changent de main</x-slot>
            @forelse($modes as $l)
                <div style="padding:.4rem 0;">
                    <div style="display:flex;justify-content:space-between;font-size:.85rem;"><span>{{ $l['mode'] }}</span><strong>{{ $n($l['ventes']) }} · {{ $l['part'] }} %</strong></div>
                    <div style="height:.45rem;border-radius:999px;background:rgba(148,163,184,.2);margin-top:.25rem;"><div class="dv-barre" style="width:{{ $l['part'] }}%;"></div></div>
                </div>
            @empty
                <p class="dv-vide">Pas encore de vente.</p>
            @endforelse

            <div class="dv-l" style="margin-top:1rem;">Jours où l’on vend le plus</div>
            <div style="display:flex;gap:.4rem;align-items:flex-end;height:90px;margin-top:.5rem;">
                @foreach($jours as $j)
                    <div style="flex:1;text-align:center;">
                        <div style="height:{{ max(3, round($j['ventes'] / $maxJour * 70)) }}px;background:#0d9488;border-radius:.3rem .3rem 0 0;opacity:.85;"></div>
                        <div style="font-size:.65rem;opacity:.6;margin-top:.2rem;">{{ mb_substr($j['jour'], 0, 3) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">⚖️ Ce qu’on a le droit de partager</x-slot>
        <div style="font-size:.85rem;line-height:1.6;opacity:.8;">
            <p>✅ <strong>Partageable</strong> : tout ce qui est sur cette page (chiffres agrégés et anonymes, export CSV compris), avec un acheteur de l’entreprise, un investisseur, une marque ou une collectivité.</p>
            <p>✅ <strong>En cas de vente de l’entreprise</strong> : la base membres complète peut être transmise avec la société, à condition de prévenir les membres et de garder le même usage.</p>
            <p>❌ <strong>Interdit</strong> : vendre ou céder à une autre entreprise des données personnelles (noms, e-mails, téléphones, historiques individuels) sans l’accord explicite de chaque membre. Le RGPD et les règles d’Apple (5.1.2) l’interdisent.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
