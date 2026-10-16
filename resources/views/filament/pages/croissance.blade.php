<x-filament-panels::page>
    @php
        $eur = fn ($v) => \App\Support\TableauDeBord::euros((float) $v);
        $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ');
        $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ') . ' %';
        $valeur = fn (array $p) => match ($p['format']) {
            'euros' => $eur($p['actuel']) . ' / ' . $eur($p['objectif']),
            'pct' => $pct($p['actuel']) . ' / ' . $pct($p['objectif']),
            default => $num($p['actuel']) . ' / ' . $num($p['objectif']),
        };
        $evo = function ($e) {
            if ($e === null) return '';
            $couleur = $e >= 0 ? '#059669' : '#dc2626';
            return '<span style="color:' . $couleur . ';font-weight:700;font-size:11px;white-space:nowrap">' . ($e >= 0 ? '▲ +' : '▼ ') . $e . ' %</span>';
        };
        $termines = array_values(array_filter($mois, fn ($m) => ! $m['en_cours']));
        $courbe = array_slice($mois, -12);
    @endphp

    <style>
        .cr-grille{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr));}
        @media(max-width:1024px){.cr-grille{grid-template-columns:repeat(2,minmax(0,1fr));}}
        .cr-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:.85rem 1rem;min-width:0;}
        .cr-kpi .l{font-size:.75rem;font-weight:700;opacity:.65;}
        .cr-kpi .v{font-size:1.45rem;font-weight:900;margin-top:.2rem;font-variant-numeric:tabular-nums;}
        .cr-kpi .s{font-size:.72rem;font-weight:600;opacity:.6;margin-top:.15rem;}
        @media(max-width:640px){.cr-kpi .v{font-size:1.2rem;}}
        .cr-paliers{display:flex;gap:.4rem;flex-wrap:wrap;margin:.25rem 0 1rem;}
        .cr-palier{flex:1;min-width:120px;border-radius:.8rem;padding:.55rem .7rem;font-size:.8rem;font-weight:700;border:1px solid rgba(148,163,184,.25);opacity:.55;}
        .cr-palier.fait{opacity:1;background:rgba(13,148,136,.12);border-color:rgba(13,148,136,.45);}
        .cr-palier.ici{opacity:1;background:#0d9488;color:#fff;border-color:#0d9488;}
        .cr-obj{margin:.6rem 0;}
        .cr-obj .haut{display:flex;justify-content:space-between;gap:.5rem;font-size:.82rem;font-weight:700;}
        .cr-obj .haut span:last-child{opacity:.7;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .cr-piste{height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;margin-top:.3rem;}
        .cr-piste div{height:100%;border-radius:1rem;background:linear-gradient(90deg,#0d9488,#22c55e);}
        .cr-table-wrap{overflow-x:auto;margin:0 -.25rem;}
        .cr-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:860px;}
        .cr-table th,.cr-table td{padding:.55rem .5rem;border-top:1px solid rgba(148,163,184,.18);text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .cr-table th{font-size:.72rem;opacity:.65;border-top:0;vertical-align:bottom;white-space:normal;}
        .cr-table td:first-child,.cr-table th:first-child{text-align:left;}
        .cr-table tr.en-cours td{opacity:.7;font-style:italic;}
        .cr-note{font-size:.78rem;opacity:.65;margin-top:.6rem;line-height:1.5;}
    </style>

    {{-- Palier --}}
    <x-filament::section>
        <x-slot name="heading">{{ $palier['emoji'] }} Palier actuel : {{ $palier['nom'] }}</x-slot>
        <x-slot name="description">Calculé sur les 30 derniers jours</x-slot>

        <div class="cr-paliers">
            @foreach(\App\Support\Croissance::PALIERS as $n => $p)
                <div class="cr-palier {{ $n === $palier['niveau'] ? 'ici' : ($n < $palier['niveau'] ? 'fait' : '') }}">{{ $p['emoji'] }} {{ $n }}. {{ $p['nom'] }}</div>
            @endforeach
        </div>

        @if($palier['suivant'])
            <div style="font-weight:800;font-size:.9rem">Pour passer à « {{ $palier['suivant']['nom'] }} » {{ $palier['suivant']['emoji'] }} :</div>
            @foreach($palier['progression'] as $p)
                <div class="cr-obj">
                    <div class="haut"><span>{{ $p['pct'] >= 100 ? '✅' : '⏳' }} {{ $p['libelle'] }}</span><span>{{ $valeur($p) }}</span></div>
                    <div class="cr-piste"><div style="width:{{ max(2, $p['pct']) }}%"></div></div>
                </div>
            @endforeach
        @else
            <p style="font-weight:700">🎉 Tous les paliers sont atteints : la plateforme est prête à être présentée à des repreneurs.</p>
        @endif
    </x-filament::section>

    {{-- 30 derniers jours --}}
    <div class="cr-grille">
        <div class="cr-kpi">
            <div class="l">🛒 Ventes payées en ligne</div>
            <div class="v">{{ $num($actuel['ventes']) }}</div>
            <div class="s">30 derniers jours</div>
        </div>
        <div class="cr-kpi">
            <div class="l">🏦 Revenus Swap’Îles</div>
            <div class="v">{{ $eur($actuel['revenus']) }}</div>
            <div class="s">30 derniers jours</div>
        </div>
        <div class="cr-kpi">
            <div class="l">👥 Visiteurs par jour</div>
            <div class="v">{{ $num($actuel['visiteurs_jour']) }}</div>
            <div class="s">humains, moyenne sur 30 jours</div>
        </div>
        <div class="cr-kpi">
            <div class="l">✅ Vendues en moins de 60 j</div>
            <div class="v">{{ $pct($actuel['liquidite']) }}</div>
            <div class="s">annonces créées il y a 2 à 3 mois</div>
        </div>
    </div>

    {{-- Courbes --}}
    @if(count($courbe) > 1)
        <x-filament::section>
            <x-slot name="heading">📈 Volume et revenus par mois</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:.9rem;font-size:.8rem;font-weight:600;margin-bottom:.4rem;">
                <span>🟧 Volume des ventes en ligne (€)</span><span>🟩 Revenus Swap’Îles (€)</span>
            </div>
            {!! \App\Support\Charts::line(
                array_map(fn ($m) => $m['mois']->locale('fr')->translatedFormat('M y'), $courbe),
                [
                    ['name' => 'Volume', 'color' => '#f59e0b', 'data' => array_column($courbe, 'volume')],
                    ['name' => 'Revenus', 'color' => '#0d9488', 'data' => array_column($courbe, 'revenus')],
                ],
                210, 560
            ) !!}
        </x-filament::section>
    @endif

    {{-- Tableau --}}
    <x-filament::section>
        <x-slot name="heading">🗓️ Mois par mois</x-slot>
        <x-slot name="description">Faites glisser le tableau sur le côté pour voir toutes les colonnes.</x-slot>

        <div class="cr-table-wrap">
            <table class="cr-table">
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th>Visiteurs uniques</th>
                        <th>Visiteurs / jour</th>
                        <th>Nouveaux membres</th>
                        <th>Annonces créées</th>
                        <th>Ventes en ligne</th>
                        <th>Volume</th>
                        <th>Revenus</th>
                        <th>Panier moyen</th>
                        <th>Acheteurs qui reviennent</th>
                        <th>Vendues en 60 j</th>
                        <th>Main propre déclarée</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_reverse($mois) as $m)
                        <tr class="{{ $m['en_cours'] ? 'en-cours' : '' }}">
                            <td><strong>{{ $m['libelle'] }}</strong>@if($m['en_cours'])<br><small>en cours</small>@endif</td>
                            <td>{{ $num($m['visiteurs']) }}</td>
                            <td>{{ $num($m['visiteurs_jour']) }}</td>
                            <td>{{ $num($m['inscriptions']) }} {!! $evo($m['evolution_membres']) !!}</td>
                            <td>{{ $num($m['annonces']) }}</td>
                            <td>{{ $num($m['ventes']) }}</td>
                            <td>{{ $eur($m['volume']) }} {!! $evo($m['evolution_volume']) !!}</td>
                            <td><strong>{{ $eur($m['revenus']) }}</strong></td>
                            <td>{{ $m['ventes'] > 0 ? $eur($m['panier_moyen']) : '—' }}</td>
                            <td>{{ $m['fidelite'] === null ? '—' : $pct($m['fidelite']) . ' (' . $m['acheteurs_revenants'] . '/' . $m['acheteurs'] . ')' }}</td>
                            <td>{{ $m['liquidite'] === null ? 'en cours' : $pct($m['liquidite']) }}</td>
                            <td>{{ $num($m['ventes_declarees']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="cr-note">
            <strong>Comment lire :</strong>
            « Volume » = ce que les acheteurs ont payé en ligne. « Revenus » = protection acheteur + part Swap’Îles des points relais, crédit parrainage déduit (mêmes chiffres que le tableau de bord).
            « Acheteurs qui reviennent » = acheteurs du mois qui avaient déjà acheté avant.
            « Vendues en 60 j » = part des annonces du mois vendues en moins de 60 jours (affichée une fois le délai écoulé).
            Visiteurs : humains confirmés, comptés depuis le {{ \Illuminate\Support\Carbon::parse(\App\Support\TableauDeBord::DEBUT_VISITEURS_FIABLES)->format('d/m/Y') }}.
        </p>
    </x-filament::section>
</x-filament-panels::page>
