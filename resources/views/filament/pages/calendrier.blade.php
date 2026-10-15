<x-filament-panels::page>
    @php
        $moisFr = ucfirst($debutMois->locale('fr')->translatedFormat('F Y'));
        $decalage = $debutMois->dayOfWeekIso - 1;
        $aujourdhui = now()->toDateString();
        $d = $detail;
        $evo = fn ($a, $b) => \App\Support\CalendrierStats::evolution($a === null ? null : (float) $a, $b === null ? null : (float) $b);
        $badgeEvo = function ($e) {
            if ($e === null) return '';
            $couleur = $e >= 0 ? '#059669' : '#dc2626';
            return '<span style="color:' . $couleur . ';font-weight:600">' . ($e >= 0 ? '▲ +' : '▼ ') . $e . ' %</span> vs même jour sem. dernière';
        };
        $euros = fn ($v) => \App\Support\TableauDeBord::euros((float) $v);
    @endphp

    <style>
        .cal-grille { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:6px; }
        .cal-case { position:relative; min-height:74px; border-radius:12px; padding:6px 8px; text-align:left; border:1px solid rgba(127,127,127,.18); cursor:pointer; transition:transform .1s; }
        .cal-case:hover { transform:translateY(-1px); border-color:#0d9488; }
        .cal-num { font-size:12px; font-weight:700; opacity:.7; }
        .cal-vis { font-size:18px; font-weight:800; line-height:1.2; margin-top:2px; font-variant-numeric:tabular-nums; }
        .cal-mini { font-size:11px; opacity:.8; margin-top:2px; white-space:nowrap; overflow:hidden; }
        .cal-cartes { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; }
        .cal-carte { border:1px solid rgba(127,127,127,.18); border-radius:12px; padding:12px; }
        .cal-carte .lib { font-size:12px; opacity:.7; font-weight:600; }
        .cal-carte .val { font-size:24px; font-weight:800; margin-top:2px; font-variant-numeric:tabular-nums; }
        .cal-carte .sous { font-size:12px; opacity:.85; margin-top:2px; }
        .cal-tab { width:100%; border-collapse:collapse; font-size:14px; }
        .cal-tab td, .cal-tab th { padding:8px 6px; border-top:1px solid rgba(127,127,127,.15); text-align:left; }
        .cal-tab th { font-size:12px; opacity:.7; border-top:0; }
        @media (max-width: 640px) { .cal-mini { display:none; } .cal-case { min-height:54px; padding:5px; } .cal-vis { font-size:14px; } }
    </style>

    <x-filament::section>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="moisPrecedent" label="Mois précédent" />
            <h2 style="font-size:18px;font-weight:800">{{ $moisFr }}</h2>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="moisSuivant" label="Mois suivant" />
        </div>

        <div class="cal-grille" style="margin-bottom:6px">
            @foreach(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $j)
                <div style="text-align:center;font-size:12px;font-weight:700;opacity:.6">{{ $j }}</div>
            @endforeach
        </div>
        <div class="cal-grille">
            @for($i = 0; $i < $decalage; $i++)<div></div>@endfor
            @foreach($jours as $date => $c)
                @php
                    $futur = $date > $aujourdhui;
                    $intensite = $c['visiteurs'] ? 0.06 + 0.44 * ($c['visiteurs'] / $maxVisiteurs) : 0;
                    $choisi = $date === $this->jour;
                @endphp
                <button type="button" wire:click="choisir('{{ $date }}')" class="cal-case"
                        style="background:rgba(13,148,136,{{ $intensite }});{{ $futur ? 'opacity:.35;' : '' }}{{ $choisi ? 'outline:2px solid #0d9488;outline-offset:1px;' : '' }}{{ $date === $aujourdhui ? 'border-color:#0d9488;' : '' }}"
                        aria-label="Voir le {{ \Illuminate\Support\Carbon::parse($date)->locale('fr')->translatedFormat('j F') }}">
                    <div class="cal-num">{{ (int) substr($date, 8) }}</div>
                    @unless($futur)
                        <div class="cal-vis" title="Visiteurs uniques">{{ $c['visiteurs'] ?? '·' }}</div>
                        <div class="cal-mini">
                            @if($c['ventes'])🛍 {{ $c['ventes'] }} @endif
                            @if($c['inscriptions'])👤 {{ $c['inscriptions'] }} @endif
                            @if($c['annonces'])📦 {{ $c['annonces'] }}@endif
                        </div>
                    @endunless
                </button>
            @endforeach
        </div>
        <p style="margin-top:10px;font-size:12px;opacity:.7">Grand chiffre = visiteurs uniques (humains, robots exclus, compté depuis le {{ \Illuminate\Support\Carbon::parse(\App\Support\TableauDeBord::DEBUT_VISITEURS_FIABLES)->format('d/m/Y') }}). 🛍 ventes · 👤 inscriptions · 📦 annonces publiées. Plus la case est foncée, plus il y a eu de monde.</p>
    </x-filament::section>

    <x-filament::section :heading="ucfirst($d['date']->locale('fr')->translatedFormat('l j F Y'))" description="Le détail de la journée.">
        <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;opacity:.6;margin-bottom:8px">Audience</h3>
        <div class="cal-cartes">
            <div class="cal-carte">
                <div class="lib">Visiteurs uniques</div>
                <div class="val">{{ $d['visiteurs'] ?? '—' }}</div>
                <div class="sous">
                    @if($d['visiteurs'] !== null) dont {{ $d['visiteurs_appli'] }} dans l’appli, {{ $d['visiteurs_membres'] }} membres connectés<br>{!! $badgeEvo($evo($d['visiteurs'], $d['visiteurs_semaine_passee'])) !!}
                    @else comptage fiable à partir du {{ \Illuminate\Support\Carbon::parse(\App\Support\TableauDeBord::DEBUT_VISITEURS_FIABLES)->format('d/m/Y') }} @endif
                </div>
            </div>
            <div class="cal-carte">
                <div class="lib">Nouvelles installations de l’appli</div>
                <div class="val">{{ $d['installations_ios'] + $d['installations_android'] }}</div>
                <div class="sous">{{ $d['installations_ios'] }} iPhone · {{ $d['installations_android'] }} Android</div>
            </div>
            <div class="cal-carte">
                <div class="lib">Notifications push</div>
                <div class="val">{{ $d['push']['parties'] ?? 0 }}</div>
                <div class="sous">{{ $d['push']['ouvertes'] ?? 0 }} ouverte(s)@if(($d['push']['taux'] ?? null) !== null) · {{ str_replace('.', ',', (string) $d['push']['taux']) }} %@endif</div>
            </div>
        </div>

        <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;opacity:.6;margin:18px 0 8px">Activité</h3>
        <div class="cal-cartes">
            <div class="cal-carte"><div class="lib">Inscriptions</div><div class="val">{{ $d['inscriptions'] }}</div><div class="sous">{!! $badgeEvo($evo($d['inscriptions'], $d['semaine_passee']['inscriptions'])) !!}</div></div>
            <div class="cal-carte"><div class="lib">Annonces publiées</div><div class="val">{{ $d['annonces'] }}</div><div class="sous">{!! $badgeEvo($evo($d['annonces'], $d['semaine_passee']['annonces'])) !!}</div></div>
            <div class="cal-carte"><div class="lib">Messages envoyés</div><div class="val">{{ $d['messages'] }}</div><div class="sous">{!! $badgeEvo($evo($d['messages'], $d['semaine_passee']['messages'])) !!}</div></div>
            <div class="cal-carte"><div class="lib">Favoris ajoutés</div><div class="val">{{ $d['favoris'] }}</div><div class="sous">hors propres annonces</div></div>
            @if($d['lives'])<div class="cal-carte"><div class="lib">Lives</div><div class="val">{{ $d['lives'] }}</div></div>@endif
        </div>

        <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;opacity:.6;margin:18px 0 8px">Argent</h3>
        <div class="cal-cartes">
            <div class="cal-carte"><div class="lib">Ventes en ligne</div><div class="val">{{ $d['ventes'] }}</div><div class="sous">{{ $euros($d['volume']) }} payés par les acheteurs</div></div>
            <div class="cal-carte"><div class="lib">Revenus Swap’Îles</div><div class="val">{{ $euros($d['revenus']) }}</div><div class="sous">protection + commission + part relais</div></div>
            <div class="cal-carte"><div class="lib">Main propre déclarée</div><div class="val">{{ $d['ventes_declarees'] }}</div><div class="sous">{{ $euros($d['volume_declare']) }} · {{ $d['ventes_declarees_confirmees'] }} confirmée(s) par l’acheteur</div></div>
        </div>

        @if($d['liste_ventes']->isNotEmpty())
            <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;opacity:.6;margin:18px 0 4px">Ventes du jour</h3>
            <div style="overflow-x:auto">
                <table class="cal-tab">
                    <thead><tr><th>Heure</th><th>Article</th><th>Vendeur → acheteur</th><th>Mode</th><th style="text-align:right">Montant</th></tr></thead>
                    <tbody>
                        @foreach($d['liste_ventes'] as $t)
                            <tr>
                                <td>{{ $t->created_at->format('H:i') }}</td>
                                <td><a href="{{ \App\Filament\Resources\Transactions\TransactionResource::getUrl('view', ['record' => $t]) }}" style="color:#0d9488;font-weight:600">{{ $t->listing->title ?? 'Annonce supprimée' }}</a></td>
                                <td>{{ $t->seller->name ?? '?' }} → {{ $t->buyer->name ?? '?' }}</td>
                                <td>{{ $t->estDeclaree() ? 'Main propre déclarée' : 'En ligne' }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $euros($t->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($d['nouveaux_membres']->isNotEmpty())
            <h3 style="font-size:13px;font-weight:800;text-transform:uppercase;opacity:.6;margin:18px 0 4px">Nouveaux membres</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($d['nouveaux_membres'] as $m)
                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $m]) }}" style="border:1px solid rgba(127,127,127,.2);border-radius:999px;padding:4px 10px;font-size:13px">
                        {{ $m->name }} <span style="opacity:.6">· {{ $m->territoire ?: '?' }} · {{ $m->created_at->format('H:i') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
