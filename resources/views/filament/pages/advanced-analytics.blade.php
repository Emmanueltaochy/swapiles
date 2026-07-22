<x-filament-panels::page>
    @php
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
        $num = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <style>
        .swp-aa{display:flex;flex-direction:column;gap:1.5rem;}
        .swp-aa-grid{display:grid;gap:.9rem;}
        .swp-aa-2{grid-template-columns:repeat(2,minmax(0,1fr));}
        .swp-aa-3{grid-template-columns:repeat(3,minmax(0,1fr));}
        .swp-aa-4{grid-template-columns:repeat(4,minmax(0,1fr));}
        @media(max-width:1024px){.swp-aa-4{grid-template-columns:repeat(2,minmax(0,1fr));}.swp-aa-3{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:640px){.swp-aa-4,.swp-aa-3,.swp-aa-2{grid-template-columns:1fr;}}
        .swp-kpi{border-radius:1rem;background:rgba(148,163,184,.10);padding:1rem 1.1rem;}
        .swp-kpi .l{font-size:.78rem;font-weight:700;opacity:.6;display:flex;align-items:center;gap:.35rem;}
        .swp-kpi .v{font-size:1.6rem;font-weight:900;line-height:1.15;margin-top:.25rem;letter-spacing:-.02em;}
        .swp-kpi .s{font-size:.72rem;font-weight:600;opacity:.5;margin-top:.15rem;}
        .swp-title{font-size:1.05rem;font-weight:800;margin-bottom:.25rem;}
        .swp-legend{display:flex;flex-wrap:wrap;gap:1rem;font-size:.8rem;font-weight:600;margin:.4rem 0 .2rem;}
        .swp-legend span{display:inline-flex;align-items:center;gap:.4rem;opacity:.8;}
        .swp-dot{width:.7rem;height:.7rem;border-radius:.2rem;display:inline-block;}
        .swp-bar-row{display:flex;align-items:center;gap:.6rem;margin:.35rem 0;font-size:.85rem;}
        .swp-bar-row .nm{width:34%;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .swp-bar-track{flex:1;height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;}
        .swp-bar-fill{height:100%;border-radius:1rem;}
        .swp-bar-val{width:70px;text-align:right;font-weight:800;opacity:.75;}
        .swp-funnel{display:flex;flex-direction:column;gap:.55rem;}
        .swp-funnel-step{display:flex;align-items:center;gap:.8rem;}
        .swp-funnel-bar{height:2.4rem;border-radius:.6rem;display:flex;align-items:center;padding:0 .8rem;color:#fff;font-weight:800;min-width:3rem;white-space:nowrap;}
        .swp-funnel-meta{font-size:.78rem;opacity:.6;font-weight:600;}
    </style>

    <div class="swp-aa">

        {{-- Filtre de période --}}
        <x-filament::section>
            <x-slot name="heading">📈 Analyse avancée — vue produit</x-slot>
            <x-slot name="description">Toutes les métriques clés de la marketplace · {{ $periodLabel }}</x-slot>

            <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                @foreach($periods as $key => $label)
                    <x-filament::button
                        tag="a"
                        :href="request()->fullUrlWithQuery(['period' => $key])"
                        :color="$period === $key ? 'primary' : 'gray'"
                        size="sm">
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            @unless($hasEvents)
                <p style="margin-top:1rem;font-size:.85rem;opacity:.6;">
                    ⚠️ Le suivi d'audience (analytics_events) n'est pas encore actif : les métriques de trafic, sessions et rebond s'afficheront une fois les premières visites enregistrées.
                </p>
            @endunless
        </x-filament::section>

        {{-- KPIs vue d'ensemble --}}
        <div class="swp-aa-grid swp-aa-4">
            <div class="swp-kpi">
                <div class="l">👥 Visiteurs uniques</div>
                <div class="v">{{ $num($uniqueVisitors) }}</div>
                <div class="s">{{ $num($sessions) }} sessions · {{ $num($pageViews) }} pages vues</div>
            </div>
            <div class="swp-kpi">
                <div class="l">🆕 Nouveaux membres</div>
                <div class="v">{{ $num($newUsers) }}</div>
                <div class="s">{{ $num($newListings) }} annonces créées</div>
            </div>
            <div class="swp-kpi">
                <div class="l">💰 Volume d'affaires (GMV)</div>
                <div class="v">{{ $eur($gmv) }}</div>
                <div class="s">{{ $num($paidCount) }} ventes · panier moyen {{ $eur($aov) }}</div>
            </div>
            <div class="swp-kpi">
                <div class="l">🏦 Revenu net Swap’Îles</div>
                <div class="v">{{ $eur($netRevenue) }}</div>
                <div class="s">Take rate {{ number_format($takeRate, 1, ',', ' ') }}% · dont {{ $eur($commission) }} commission</div>
            </div>
        </div>

        {{-- Évolution --}}
        <x-filament::section>
            <x-slot name="heading">📊 Évolution ({{ $chartDays }} derniers jours)</x-slot>

            <div class="swp-legend">
                <span><i class="swp-dot" style="background:#0d9488;"></i> Inscriptions</span>
                <span><i class="swp-dot" style="background:#3b82f6;"></i> Annonces</span>
                <span><i class="swp-dot" style="background:#f59e0b;"></i> Ventes</span>
                @if($series['events'])<span><i class="swp-dot" style="background:#a855f7;"></i> Pages vues</span>@endif
            </div>

            <div style="color:inherit;">
                {!! \App\Support\Charts::line(
                    $series['signups']['labels'],
                    array_filter([
                        ['name' => 'Inscriptions', 'color' => '#0d9488', 'data' => $series['signups']['data']],
                        ['name' => 'Annonces', 'color' => '#3b82f6', 'data' => $series['listings']['data']],
                        ['name' => 'Ventes', 'color' => '#f59e0b', 'data' => $series['sales']['data']],
                        $series['events'] ? ['name' => 'Pages vues', 'color' => '#a855f7', 'data' => $series['events']['data']] : null,
                    ]),
                    260
                ) !!}
            </div>
        </x-filament::section>

        {{-- Funnel de conversion --}}
        <x-filament::section>
            <x-slot name="heading">🎯 Entonnoir de conversion</x-slot>
            <x-slot name="description">Du visiteur au vendeur · {{ $periodLabel }}</x-slot>

            @php
                $steps = [
                    ['Visiteurs uniques', $funnel['visitors'], '#6366f1'],
                    ['Inscriptions', $funnel['signups'], '#0d9488'],
                    ['Ont publié une annonce', $funnel['publishers'], '#3b82f6'],
                    ['Ont réalisé une vente', $funnel['sellers'], '#f59e0b'],
                ];
                $funnelMax = max(1, $funnel['visitors'], $funnel['signups'], $funnel['publishers'], $funnel['sellers']);
            @endphp
            <div class="swp-funnel">
                @foreach($steps as $i => $step)
                    @php
                        $pct = $funnelMax > 0 ? max(4, round($step[1] / $funnelMax * 100)) : 4;
                        $convFromPrev = ($i > 0 && $steps[$i-1][1] > 0) ? round($step[1] / $steps[$i-1][1] * 100, 1) : null;
                    @endphp
                    <div class="swp-funnel-step">
                        <div class="swp-funnel-bar" style="background:{{ $step[2] }};width:{{ $pct }}%;">
                            {{ $num($step[1]) }}
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem;">{{ $step[0] }}</div>
                            @if($convFromPrev !== null)
                                <div class="swp-funnel-meta">{{ number_format($convFromPrev, 1, ',', ' ') }}% de l'étape précédente</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Engagement & rétention --}}
        <div class="swp-aa-grid swp-aa-4">
            <div class="swp-kpi">
                <div class="l">📄 Pages / session</div>
                <div class="v">{{ number_format($pagesPerSession, 1, ',', ' ') }}</div>
                <div class="s">profondeur de visite</div>
            </div>
            <div class="swp-kpi">
                <div class="l">↩️ Taux de rebond</div>
                <div class="v">{{ number_format($bounceRate, 1, ',', ' ') }}%</div>
                <div class="s">sessions à 1 seule page</div>
            </div>
            <div class="swp-kpi">
                <div class="l">🔁 Visiteurs récurrents</div>
                <div class="v">{{ number_format($returningRate, 1, ',', ' ') }}%</div>
                <div class="s">{{ $num($returningVisitors) }} revenus ≥ 2 jours</div>
            </div>
            <div class="swp-kpi">
                <div class="l">🧲 Stickiness (DAU/MAU)</div>
                <div class="v">{{ number_format($stickiness, 1, ',', ' ') }}%</div>
                <div class="s">fidélité quotidienne</div>
            </div>
        </div>

        <div class="swp-aa-grid swp-aa-3">
            <div class="swp-kpi">
                <div class="l">☀️ Actifs / jour (DAU)</div>
                <div class="v">{{ $num($dau) }}</div>
                <div class="s">dernières 24 h</div>
            </div>
            <div class="swp-kpi">
                <div class="l">📅 Actifs / semaine (WAU)</div>
                <div class="v">{{ $num($wau) }}</div>
                <div class="s">7 derniers jours</div>
            </div>
            <div class="swp-kpi">
                <div class="l">🗓️ Actifs / mois (MAU)</div>
                <div class="v">{{ $num($mau) }}</div>
                <div class="s">30 derniers jours</div>
            </div>
        </div>

        {{-- Activité par heure --}}
        <x-filament::section>
            <x-slot name="heading">🕐 Activité par heure</x-slot>
            <x-slot name="description">Quand vos utilisateurs sont les plus actifs · {{ $periodLabel }}</x-slot>
            <div style="color:inherit;">
                {!! \App\Support\Charts::bars(
                    array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . 'h', range(0, 23)),
                    array_values($hourly),
                    '#0d9488',
                    170
                ) !!}
            </div>
        </x-filament::section>

        {{-- Sources & appareils --}}
        <div class="swp-aa-grid swp-aa-2">
            <x-filament::section>
                <x-slot name="heading">🌐 Sources de trafic</x-slot>
                @php $srcMax = max(1, ...(count($sources) ? array_values($sources) : [1])); @endphp
                @forelse($sources as $src => $c)
                    <div class="swp-bar-row">
                        <div class="nm">{{ $src }}</div>
                        <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($c / $srcMax * 100) }}%;background:#6366f1;"></div></div>
                        <div class="swp-bar-val">{{ $num($c) }}</div>
                    </div>
                @empty
                    <p style="opacity:.6;">Aucune donnée de source pour le moment.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">📱 Appareils</x-slot>
                @php $devTotal = max(1, $devices->sum('c')); @endphp
                @forelse($devices as $d)
                    <div class="swp-bar-row">
                        <div class="nm">{{ ucfirst($d->device) }}</div>
                        <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($d->c / $devTotal * 100) }}%;background:#0d9488;"></div></div>
                        <div class="swp-bar-val">{{ round($d->c / $devTotal * 100) }}%</div>
                    </div>
                @empty
                    <p style="opacity:.6;">Aucune donnée d'appareil pour le moment.</p>
                @endforelse
            </x-filament::section>
        </div>

        {{-- Santé marketplace & revenu --}}
        <x-filament::section>
            <x-slot name="heading">🏪 Santé de la marketplace</x-slot>
            <div class="swp-aa-grid swp-aa-4">
                <div class="swp-kpi">
                    <div class="l">🛍️ Vendeurs actifs</div>
                    <div class="v">{{ $num($activeSellers) }}</div>
                    <div class="s">ayant vendu · {{ $periodLabel }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">🛒 Acheteurs actifs</div>
                    <div class="v">{{ $num($activeBuyers) }}</div>
                    <div class="s">ayant acheté · {{ $periodLabel }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">✅ Taux d'écoulement</div>
                    <div class="v">{{ number_format($sellThrough, 1, ',', ' ') }}%</div>
                    <div class="s">{{ $num($listingsSold) }} vendues / {{ $num($listingsPublishedNow + $listingsSold) }}</div>
                </div>
                <div class="swp-kpi">
                    <div class="l">💵 Revenu / acheteur (ARPU)</div>
                    <div class="v">{{ $eur($arpu) }}</div>
                    <div class="s">protection acheteur {{ $eur($protection) }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Top pages --}}
        <x-filament::section>
            <x-slot name="heading">🔥 Pages les plus vues</x-slot>
            @php $pageMax = max(1, ...($topPages->count() ? $topPages->pluck('c')->all() : [1])); @endphp
            @forelse($topPages as $p)
                <div class="swp-bar-row">
                    <div class="nm" title="{{ $p->path }}">{{ $p->label ?: $p->path }}</div>
                    <div class="swp-bar-track"><div class="swp-bar-fill" style="width:{{ round($p->c / $pageMax * 100) }}%;background:#a855f7;"></div></div>
                    <div class="swp-bar-val">{{ $num($p->c) }}</div>
                </div>
            @empty
                <p style="opacity:.6;">Aucune page vue enregistrée pour le moment.</p>
            @endforelse
        </x-filament::section>

    </div>
</x-filament-panels::page>
