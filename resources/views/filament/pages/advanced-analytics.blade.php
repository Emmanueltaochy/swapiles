<x-filament-panels::page>
    @php
        $eur = fn ($v) => \App\Support\TableauDeBord::euros((float) $v);
        $num = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $pct = fn ($v) => number_format((float) $v, 1, ',', ' ') . ' %';
        $part = fn ($a, $b) => $b > 0 ? $pct($a / $b * 100) : '—';
        $v = $visiteurs;
        $a = $aujourdhui;
        $heures = array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . 'h', range(0, 23));
    @endphp

    <style>
        .swp-aa{display:flex;flex-direction:column;gap:1.25rem;}
        .swp-aa-grid{display:grid;gap:.75rem;}
        .swp-aa-2{grid-template-columns:repeat(2,minmax(0,1fr));}
        .swp-aa-3{grid-template-columns:repeat(3,minmax(0,1fr));}
        .swp-aa-4{grid-template-columns:repeat(4,minmax(0,1fr));}
        @media(max-width:1024px){.swp-aa-4,.swp-aa-3{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:640px){.swp-aa-2.colonnes{grid-template-columns:1fr;}}
        .swp-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:.85rem 1rem;min-width:0;}
        .swp-kpi .l{font-size:.75rem;font-weight:700;opacity:.65;}
        .swp-kpi .v{font-size:1.5rem;font-weight:900;line-height:1.15;margin-top:.2rem;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
        .swp-kpi .s{font-size:.72rem;font-weight:600;opacity:.6;margin-top:.15rem;}
        .swp-kpi.accent{background:rgba(13,148,136,.14);}
        @media(max-width:640px){.swp-kpi .v{font-size:1.25rem;}.swp-kpi{padding:.75rem .8rem;}}
        .swp-legend{display:flex;flex-wrap:wrap;gap:.9rem;font-size:.8rem;font-weight:600;margin:0 0 .4rem;}
        .swp-legend span{display:inline-flex;align-items:center;gap:.35rem;opacity:.85;}
        .swp-dot{width:.65rem;height:.65rem;border-radius:.2rem;display:inline-block;}
        .swp-sous{margin:1rem 0 .35rem;font-size:.85rem;font-weight:700;opacity:.75;}
        .swp-bar-row{display:flex;align-items:center;gap:.6rem;margin:.4rem 0;font-size:.85rem;}
        .swp-bar-row .nm{width:38%;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .swp-bar-track{flex:1;height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;}
        .swp-bar-fill{height:100%;border-radius:1rem;}
        .swp-bar-val{min-width:58px;text-align:right;font-weight:800;opacity:.8;font-variant-numeric:tabular-nums;}
        .swp-etape{display:flex;align-items:center;gap:.75rem;margin:.5rem 0;}
        .swp-etape .barre{height:2.3rem;border-radius:.6rem;display:flex;align-items:center;padding:0 .75rem;color:#fff;font-weight:800;min-width:3.2rem;white-space:nowrap;}
        .swp-etape .txt{font-size:.85rem;font-weight:700;line-height:1.25;}
        .swp-etape .txt small{display:block;font-weight:600;opacity:.6;font-size:.75rem;}
        .swp-note{font-size:.78rem;opacity:.65;margin-top:.5rem;}
    </style>

    <div class="swp-aa">

        {{-- Période --}}
        <x-filament::section>
            <x-slot name="heading">📅 Période : {{ $periodLabel }}</x-slot>

            <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                @foreach($periods as $key => $label)
                    <x-filament::button tag="a" :href="url()->current() . '?period=' . $key"
                        :color="(! $isCustom && $period === $key) ? 'primary' : 'gray'" size="sm">
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            <form method="GET" action="{{ url()->current() }}"
                  style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:.6rem;margin-top:.9rem;padding-top:.9rem;border-top:1px solid rgba(148,163,184,.18);">
                <div>
                    <label for="aa-du" style="display:block;font-size:.72rem;font-weight:700;opacity:.6;margin-bottom:.2rem;">Du</label>
                    <input id="aa-du" type="date" name="from" value="{{ $isCustom ? $from : '' }}"
                           style="border:1px solid rgba(148,163,184,.4);border-radius:.55rem;padding:.4rem .6rem;background:transparent;color:inherit;font-size:.85rem;">
                </div>
                <div>
                    <label for="aa-au" style="display:block;font-size:.72rem;font-weight:700;opacity:.6;margin-bottom:.2rem;">Au</label>
                    <input id="aa-au" type="date" name="to" value="{{ $to }}"
                           style="border:1px solid rgba(148,163,184,.4);border-radius:.55rem;padding:.4rem .6rem;background:transparent;color:inherit;font-size:.85rem;">
                </div>
                <x-filament::button type="submit" size="sm" :color="$isCustom ? 'primary' : 'gray'" icon="heroicon-o-calendar-days">Appliquer</x-filament::button>
                @if($isCustom)
                    <x-filament::button tag="a" :href="url()->current()" size="sm" color="gray">Réinitialiser</x-filament::button>
                @endif
            </form>
        </x-filament::section>

        {{-- Aujourd'hui --}}
        <x-filament::section>
            <x-slot name="heading">🕐 Aujourd’hui</x-slot>
            <x-slot name="description">Visiteurs humains confirmés, comme la page « Visiteurs en direct »</x-slot>

            <div class="swp-aa-grid swp-aa-4">
                <div class="swp-kpi accent">
                    <div class="l">🟢 En ligne maintenant</div>
                    <div class="v">{{ $num($a['en_ligne']) }}</div>
                    <div class="s">personnes sur le site ou l’appli</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">👥 Visiteurs aujourd’hui</div>
                    <div class="v">{{ $num($a['visiteurs']) }}</div>
                    <div class="s">
                        @if($a['hier_meme_heure'] !== null)
                            {{ $num($a['hier_meme_heure']) }} hier à la même heure
                        @else
                            {{ $num($a['membres']) }} membres connectés
                        @endif
                    </div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🔥 Pic de connectés</div>
                    <div class="v">{{ $num($a['connectes']['pic']['nombre']) }}</div>
                    <div class="s">{{ $a['connectes']['pic']['heure'] ? 'en même temps, à ' . $a['connectes']['pic']['heure'] : 'pas encore de relevé' }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">📱 Dans l’appli</div>
                    <div class="v">{{ $part($a['appli'], $a['visiteurs']) }}</div>
                    <div class="s">{{ $num($a['appli']) }} visiteurs · {{ $num($a['membres']) }} membres connectés</div>
                </div>
            </div>

            @if(count($a['connectes']['labels']) > 1)
                <div class="swp-sous">Connectés en même temps (relevé toutes les 10 min)</div>
                {!! \App\Support\Charts::line($a['connectes']['labels'], [['name' => 'Connectés', 'color' => '#0d9488', 'data' => $a['connectes']['data']]], 190, 560) !!}
            @endif

            <div class="swp-sous">Arrivées de visiteurs heure par heure</div>
            @if(array_sum($a['par_heure']) > 0)
                {!! \App\Support\Charts::bars($heures, $a['par_heure'], '#3b82f6', 170, 560) !!}
            @else
                <p class="swp-note">⏳ Le graphique se remplit au fil de la journée, dès les premières arrivées.</p>
            @endif
        </x-filament::section>

        {{-- Chiffres clés de la période --}}
        <x-filament::section>
            <x-slot name="heading">⭐ Chiffres clés · {{ $periodLabel }}</x-slot>
            @if($v['tronque'])
                <x-slot name="description">Visiteurs comptés depuis le {{ $v['debut_fiable']->format('d/m/Y') }} : avant, les robots faussaient le comptage.</x-slot>
            @endif

            <div class="swp-aa-grid swp-aa-4">
                <div class="swp-kpi">
                    <div class="l">👥 Visiteurs uniques</div>
                    <div class="v">{{ $num($v['uniques']) }}</div>
                    <div class="s">{{ $num($v['par_jour']) }} par jour en moyenne</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🆕 Nouveaux membres</div>
                    <div class="v">{{ $num($newUsers) }}</div>
                    <div class="s">{{ $num($newListings) }} annonces créées</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">💰 Ventes payées en ligne</div>
                    <div class="v">{{ $eur($gmv) }}</div>
                    <div class="s">{{ $num($paidCount) }} vente{{ $paidCount > 1 ? 's' : '' }}@if($paidCount > 0) · panier moyen {{ $eur($aov) }}@endif</div>
                </div>
                <div class="swp-kpi accent">
                    <div class="l">🏦 Revenus Swap’Îles</div>
                    <div class="v">{{ $eur($netRevenue) }}</div>
                    <div class="s">@if($gmv > 0){{ $pct($takeRate) }} des ventes en ligne @else protection + part relais @endif</div>
                </div>
            </div>
            <p class="swp-note">
                🤝 Main propre déclarée par les vendeurs : {{ $num($ventesDeclarees) }} vente{{ $ventesDeclarees > 1 ? 's' : '' }} · {{ $eur($volumeDeclare) }} (non vérifiable, ne rapporte rien).
                @if($credit > 0) · 🎁 Dont {{ $eur($credit) }} payés avec le crédit parrainage. @endif
            </p>
        </x-filament::section>

        {{-- Évolution --}}
        <x-filament::section>
            <x-slot name="heading">📈 Évolution jour par jour</x-slot>

            <div class="swp-sous" style="margin-top:0">👥 Visiteurs humains par jour</div>
            @if(count($v['serie']['labels']) > 1)
                {!! \App\Support\Charts::line($v['serie']['labels'], [['name' => 'Visiteurs', 'color' => '#6366f1', 'data' => $v['serie']['data']]], 200, 560) !!}
            @else
                <p class="swp-note">La courbe apparaîtra après quelques jours de comptage.</p>
            @endif

            <div class="swp-sous">Activité de la marketplace</div>
            <div class="swp-legend">
                <span><i class="swp-dot" style="background:#0d9488;"></i> Inscriptions</span>
                <span><i class="swp-dot" style="background:#3b82f6;"></i> Annonces</span>
                <span><i class="swp-dot" style="background:#f59e0b;"></i> Ventes en ligne</span>
            </div>
            {!! \App\Support\Charts::line($activite['labels'], [
                ['name' => 'Inscriptions', 'color' => '#0d9488', 'data' => $activite['inscriptions']],
                ['name' => 'Annonces', 'color' => '#3b82f6', 'data' => $activite['annonces']],
                ['name' => 'Ventes', 'color' => '#f59e0b', 'data' => $activite['ventes']],
            ], 220, 560) !!}
        </x-filament::section>

        {{-- Entonnoir par cohorte --}}
        <x-filament::section>
            <x-slot name="heading">🎯 Du visiteur au vendeur</x-slot>
            <x-slot name="description">Les membres inscrits sur la période, et ce qu’ils ont fait depuis</x-slot>

            @php
                $f = $funnel;
                $etapes = [
                    ['Visiteurs uniques', $f['visiteurs'], '#6366f1', null],
                    ['Se sont inscrits', $f['inscrits'], '#0d9488', $f['visiteurs'] > 0 && ! $v['tronque'] ? $part($f['inscrits'], $f['visiteurs']) . ' des visiteurs' : null],
                    ['Ont publié une annonce', $f['publie'], '#3b82f6', $part($f['publie'], $f['inscrits']) . ' des inscrits'],
                    ['Ont vendu au moins 1 article', $f['vendu'], '#f59e0b', $part($f['vendu'], $f['publie']) . ' de ceux qui ont publié'],
                    ['Ont acheté en ligne', $f['achete'], '#ec4899', $part($f['achete'], $f['inscrits']) . ' des inscrits'],
                ];
                $maxEtape = max(1, ...array_column($etapes, 1));
            @endphp
            @foreach($etapes as [$nom, $n, $couleur, $taux])
                <div class="swp-etape">
                    <div class="barre" style="background:{{ $couleur }};width:{{ max(14, round($n / $maxEtape * 62)) }}%;">{{ $num($n) }}</div>
                    <div class="txt">{{ $nom }}@if($taux)<small>{{ $taux }}</small>@endif</div>
                </div>
            @endforeach
        </x-filament::section>

        {{-- Fidélité --}}
        <x-filament::section>
            <x-slot name="heading">🔁 Fidélité et engagement</x-slot>
            <div class="swp-aa-grid swp-aa-3">
                <div class="swp-kpi">
                    <div class="l">🔁 Visiteurs fidèles</div>
                    <div class="v">{{ $pct($v['fideles_pct']) }}</div>
                    <div class="s">{{ $num($v['fideles']) }} venus au moins 2 jours</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">📄 Pages par visite</div>
                    <div class="v">{{ number_format($pages['par_visite'], 1, ',', ' ') }}</div>
                    <div class="s">{{ $num($pages['vues']) }} pages vues par des humains</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🧲 Reviennent chaque jour</div>
                    <div class="v">{{ $pct($v['stickiness']) }}</div>
                    <div class="s">des visiteurs du mois, un jour donné</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">☀️ Actifs aujourd’hui</div>
                    <div class="v">{{ $num($v['actifs_jour']) }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">📅 Actifs sur 7 jours</div>
                    <div class="v">{{ $num($v['actifs_semaine']) }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🗓️ Actifs sur 30 jours</div>
                    <div class="v">{{ $num($v['actifs_mois']) }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- D'où viennent-ils --}}
        <div class="swp-aa-grid swp-aa-2 colonnes">
            <x-filament::section>
                <x-slot name="heading">🌐 D’où viennent les visiteurs</x-slot>
                @php $totalSources = max(1, array_sum($v['sources'])); @endphp
                @forelse($v['sources'] as $src => $n)
                    <div class="swp-bar-row">
                        <div class="nm">{{ $src }}</div>
                        <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($n / $totalSources * 100) }}%;background:#6366f1;"></div></div>
                        <div class="swp-bar-val">{{ round($n / $totalSources * 100) }} %</div>
                    </div>
                @empty
                    <p class="swp-note">Pas encore de données.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">🏝️ Par île</x-slot>
                @php $totalIles = max(1, array_sum($v['iles'])); @endphp
                @forelse($v['iles'] as $ile => $n)
                    <div class="swp-bar-row">
                        <div class="nm">{{ $ile }}</div>
                        <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($n / $totalIles * 100) }}%;background:#0d9488;"></div></div>
                        <div class="swp-bar-val">{{ round($n / $totalIles * 100) }} %</div>
                    </div>
                @empty
                    <p class="swp-note">Pas encore de données.</p>
                @endforelse
                @if($v['uniques'] > 0)
                    <p class="swp-note">📱 {{ $part($v['appli'], $v['uniques']) }} des visiteurs utilisent l’appli.</p>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">🕐 À quelle heure arrivent les visiteurs</x-slot>
            <x-slot name="description">{{ $periodLabel }} · heure de La Réunion</x-slot>
            @if(array_sum($v['par_heure']) > 0)
                {!! \App\Support\Charts::bars($heures, $v['par_heure'], '#0d9488', 170, 560) !!}
            @else
                <p class="swp-note">⏳ Les heures d’arrivée sont enregistrées depuis cette mise à jour : le graphique se remplira dans les prochains jours.</p>
            @endif
        </x-filament::section>

        {{-- Marketplace --}}
        <x-filament::section>
            <x-slot name="heading">🏪 Santé de la marketplace · {{ $periodLabel }}</x-slot>
            <div class="swp-aa-grid swp-aa-4">
                <div class="swp-kpi">
                    <div class="l">🛍️ Vendeurs actifs</div>
                    <div class="v">{{ $num($activeSellers) }}</div>
                    <div class="s">ont vendu (en ligne ou main propre)</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🛒 Acheteurs en ligne</div>
                    <div class="v">{{ $num($activeBuyers) }}</div>
                    <div class="s">ont payé sur Swap’Îles</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">✅ Annonces déjà vendues</div>
                    <div class="v">{{ $pct($sellThrough) }}</div>
                    <div class="s">{{ $num($annoncesVendues) }} sur {{ $num($newListings) }} créées</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">💵 Revenu par acheteur</div>
                    <div class="v">{{ $eur($arpu) }}</div>
                    <div class="s">revenus ÷ acheteurs en ligne</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Pages --}}
        <x-filament::section>
            <x-slot name="heading">🔥 Pages les plus vues</x-slot>
            <x-slot name="description">Par des visiteurs humains · {{ $periodLabel }}</x-slot>
            @php $maxPage = max(1, ...(count($pages['top']) ? array_values($pages['top']) : [1])); @endphp
            @forelse($pages['top'] as $page => $n)
                <div class="swp-bar-row">
                    <div class="nm" title="{{ $page }}">{{ $page }}</div>
                    <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($n / $maxPage * 100) }}%;background:#a855f7;"></div></div>
                    <div class="swp-bar-val">{{ $num($n) }}</div>
                </div>
            @empty
                <p class="swp-note">Pas encore de pages vues par des visiteurs confirmés.</p>
            @endforelse
        </x-filament::section>

    </div>
</x-filament-panels::page>
