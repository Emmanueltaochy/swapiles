<x-filament-panels::page>
    @php
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
        $t = $b['total'];
        $maxIle = max(1, ...(array_column($b['par_ile'], 'articles') ?: [1]));
        $maxCat = max(1, ...(array_column($b['par_categorie'], 'co2') ?: [1]));
        $mois = $b['par_mois'];
        $libelles = \Illuminate\Support\Collection::make(\App\Support\Categories::ARBRE)->map(fn ($c) => $c['label']);
    @endphp

    <style>
        .ir-grille{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr));}
        @media(max-width:1024px){.ir-grille{grid-template-columns:repeat(2,minmax(0,1fr));}}
        .ir-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:.9rem 1rem;min-width:0;}
        .ir-kpi .l{font-size:.75rem;font-weight:700;opacity:.65;}
        .ir-kpi .v{font-size:1.5rem;font-weight:900;margin-top:.2rem;font-variant-numeric:tabular-nums;}
        .ir-kpi .s{font-size:.72rem;font-weight:600;opacity:.6;margin-top:.15rem;}
        @media(max-width:640px){.ir-kpi .v{font-size:1.25rem;}}
        .ir-deux{display:grid;gap:1rem;grid-template-columns:repeat(2,minmax(0,1fr));}
        @media(max-width:768px){.ir-deux{grid-template-columns:1fr;}}
        .ir-ligne{display:flex;align-items:center;gap:.6rem;margin:.45rem 0;font-size:.85rem;}
        .ir-ligne .nm{width:40%;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .ir-piste{flex:1;height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;}
        .ir-piste div{height:100%;border-radius:1rem;}
        .ir-val{min-width:5.5rem;text-align:right;font-weight:800;font-variant-numeric:tabular-nums;font-size:.8rem;}
        .ir-table{width:100%;border-collapse:collapse;font-size:.82rem;}
        .ir-table th,.ir-table td{padding:.5rem .45rem;border-top:1px solid rgba(148,163,184,.18);text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .ir-table th{font-size:.72rem;opacity:.65;border-top:0;}
        .ir-table td:first-child,.ir-table th:first-child{text-align:left;}
        .ir-note{font-size:.78rem;opacity:.7;line-height:1.55;}
    </style>

    <div class="ir-grille">
        <div class="ir-kpi" style="background:rgba(13,148,136,.14);">
            <div class="l">♻️ Articles réemployés</div>
            <div class="v">{{ $n($t['articles']) }}</div>
            <div class="s">+{{ $n($b30['total']['articles']) }} ces 30 derniers jours</div>
        </div>
        <div class="ir-kpi">
            <div class="l">🗑️ Déchets évités</div>
            <div class="v">{{ $kg($t['kg']) }}</div>
            <div class="s">+{{ $kg($b30['total']['kg']) }} en 30 j</div>
        </div>
        <div class="ir-kpi" style="background:rgba(34,197,94,.14);">
            <div class="l">🌍 CO₂ évité</div>
            <div class="v">{{ $kg($t['co2']) }}</div>
            <div class="s">+{{ $kg($b30['total']['co2']) }} en 30 j</div>
        </div>
        <div class="ir-kpi">
            <div class="l">🚗 Soit l’équivalent de</div>
            <div class="v">{{ $n($b['km_voiture']) }} km</div>
            <div class="s">en voiture thermique évités</div>
        </div>
    </div>

    <div class="ir-deux">
        <x-filament::section compact>
            <x-slot name="heading">Comment les articles ont trouvé preneur</x-slot>
            @forelse($b['par_type'] as $type)
                <div class="ir-ligne">
                    <div class="nm">{{ $type['libelle'] }}</div>
                    <div class="ir-piste"><div style="width:{{ max(2, round($type['articles'] / max(1, $t['articles']) * 100)) }}%;background:#0d9488;"></div></div>
                    <div class="ir-val">{{ $n($type['articles']) }}</div>
                </div>
            @empty
                <p class="ir-note">Pas encore d’article réemployé.</p>
            @endforelse
            @if($b['via_relais'] > 0)
                <p class="ir-note" style="margin-top:.4rem;">🏪 {{ $n($b['via_relais']) }} article(s) passé(s) par un point relais.</p>
            @endif
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Par île</x-slot>
            @foreach($b['par_ile'] as $ile => $l)
                <div class="ir-ligne">
                    <div class="nm">{{ $ile }}</div>
                    <div class="ir-piste"><div style="width:{{ max(2, round($l['articles'] / $maxIle * 100)) }}%;background:#6366f1;"></div></div>
                    <div class="ir-val">{{ $n($l['articles']) }} · {{ $kg($l['co2']) }} CO₂</div>
                </div>
            @endforeach
        </x-filament::section>
    </div>

    <x-filament::section compact>
        <x-slot name="heading">CO₂ évité par catégorie</x-slot>
        @foreach($b['par_categorie'] as $cat => $l)
            <div class="ir-ligne">
                <div class="nm">{{ $cat }}</div>
                <div class="ir-piste"><div style="width:{{ max(2, round($l['co2'] / $maxCat * 100)) }}%;background:#22c55e;"></div></div>
                <div class="ir-val">{{ $kg($l['co2']) }} · {{ $n($l['articles']) }} art.</div>
            </div>
        @endforeach
    </x-filament::section>

    @if(count($mois) > 1)
        <x-filament::section compact>
            <x-slot name="heading">Articles réemployés par mois</x-slot>
            {!! \App\Support\Charts::bars(
                array_map(fn ($m) => $m['mois']->locale('fr')->translatedFormat('M'), $mois),
                array_column($mois, 'articles'), '#0d9488', 180, 560
            ) !!}
            <div style="overflow-x:auto;margin-top:.75rem;">
                <table class="ir-table">
                    <thead><tr><th>Mois</th><th>Articles</th><th>Déchets évités</th><th>CO₂ évité</th></tr></thead>
                    <tbody>
                        @foreach(array_reverse($mois) as $m)
                            @continue($m['articles'] === 0 && $m['mois']->lt(now()->startOfMonth()))
                            <tr>
                                <td><strong>{{ ucfirst($m['mois']->locale('fr')->translatedFormat('F Y')) }}</strong></td>
                                <td>{{ $n($m['articles']) }}</td>
                                <td>{{ $kg($m['kg']) }}</td>
                                <td>{{ $kg($m['co2']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section compact collapsible collapsed>
        <x-slot name="heading">📐 Méthode de calcul (à joindre au dossier)</x-slot>
        <div class="ir-note">
            <p><strong>Article réemployé</strong> : chaque annonce comptée une fois, vendue en ligne ou en main propre, échangée, donnée, ou clôturée « Vendue ». Les ventes contestées par l’acheteur sont exclues.</p>
            <p style="margin-top:.4rem;"><strong>Déchets évités</strong> : poids saisi par le vendeur quand il existe, sinon poids moyen de la catégorie.</p>
            <p style="margin-top:.4rem;"><strong>CO₂ évité</strong> : émissions de fabrication d’un article neuf équivalent, avec l’hypothèse que chaque achat d’occasion remplace un achat neuf. Ces valeurs moyennes, volontairement prudentes, sont à affiner avec la Base Empreinte de l’ADEME.</p>
            <table class="ir-table" style="margin-top:.6rem;">
                <thead><tr><th>Catégorie</th><th>Poids moyen</th><th>CO₂ évité / article</th></tr></thead>
                <tbody>
                    @foreach($facteurs as $cle => $f)
                        <tr><td>{{ $libelles[$cle] ?? $cle }}</td><td>{{ str_replace('.', ',', $f['poids']) }} kg</td><td>{{ $f['co2'] }} kg CO₂e</td></tr>
                    @endforeach
                    <tr><td>Autre / non précisée</td><td>{{ str_replace('.', ',', $defaut['poids']) }} kg</td><td>{{ $defaut['co2'] }} kg CO₂e</td></tr>
                </tbody>
            </table>
            <p style="margin-top:.4rem;"><strong>Équivalent voiture</strong> : {{ str_replace('.', ',', $kmVoiture) }} kg CO₂e par km (voiture thermique moyenne).</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
