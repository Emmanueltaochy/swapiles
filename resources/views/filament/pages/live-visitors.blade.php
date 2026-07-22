<x-filament-panels::page>
    <style>
        .lv-grid{display:grid;gap:1rem}
        .lv-4{grid-template-columns:repeat(4,minmax(0,1fr))}
        .lv-2{grid-template-columns:1.3fr .7fr}
        @media(max-width:1024px){.lv-4{grid-template-columns:repeat(2,minmax(0,1fr))}.lv-2{grid-template-columns:1fr}}
        @media(max-width:560px){.lv-4{grid-template-columns:1fr}}
        .lv-klabel{font-size:.78rem;font-weight:700;opacity:.6}
        .lv-kvalue{font-size:2.1rem;font-weight:800;line-height:1;margin-top:.4rem}
        .lv-pulse{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;background:#10b981;box-shadow:0 0 0 0 rgba(16,185,129,.6);animation:lvPulse 1.6s infinite}
        @keyframes lvPulse{70%{box-shadow:0 0 0 .55rem rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}
        .lv-island{display:flex;align-items:center;gap:.9rem;padding:.7rem 0;border-bottom:1px solid rgba(148,163,184,.18)}
        .lv-island:last-child{border-bottom:0}
        .lv-flag{font-size:1.6rem;line-height:1}
        .lv-bar{height:.5rem;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:.35rem}
        .lv-bar>div{height:100%;background:#0d9488;border-radius:999px;transition:width .5s ease}
        .lv-feed{display:flex;flex-direction:column;gap:.6rem;max-height:520px;overflow:auto;padding-right:.4rem}
        .lv-feeditem{display:flex;justify-content:space-between;gap:.8rem;border:1px solid rgba(148,163,184,.22);border-radius:1rem;padding:.7rem .85rem}
        .lv-trunc{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lv-chip{font-size:.68rem;font-weight:800;padding:.12rem .5rem;border-radius:999px;background:rgba(13,148,136,.14);color:#0d9488}
    </style>

    @php
        $visits = $this->getLiveVisits();
        $active = $visits->count();
        $connected = $visits->whereNotNull('user_id')->count();
        $mobile = $visits->where('device', 'Mobile')->count();
        $desktop = $visits->where('device', 'Desktop')->count();

        $islands = [
            'La Réunion' => '🇷🇪',
            'Martinique' => '🇲🇶',
            'Guadeloupe' => '🇬🇵',
            'Guyane' => '🇬🇫',
            'Mayotte' => '🇾🇹',
        ];

        $islandCounts = collect($islands)
            ->map(fn ($flag, $name) => ['name' => $name, 'flag' => $flag, 'count' => $visits->where('territoire', $name)->count()])
            ->sortByDesc('count')
            ->values();

        $topPages = $visits
            ->groupBy('path')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(8);

        $pageLabels = [
            '/' => '🏠 Accueil',
            '/recherche' => '🔍 Recherche',
            '/deposer-une-annonce' => '➕ Déposer une annonce',
            '/mon-compte' => '👤 Mon compte',
            '/connexion' => '🔑 Connexion',
            '/inscription' => '✍️ Inscription',
        ];
    @endphp

    <div wire:poll.10s style="display:flex;flex-direction:column;gap:1.5rem;">

        <div style="display:flex;align-items:center;gap:.6rem;">
            <span class="lv-pulse"></span>
            <span style="font-weight:800;">En direct</span>
            <span style="opacity:.55;font-size:.85rem;">· actualisé automatiquement toutes les 10 s</span>
        </div>

        {{-- KPI --}}
        <div class="lv-grid lv-4">
            <x-filament::section>
                <div class="lv-klabel">🟢 Visiteurs en ligne</div>
                <div class="lv-kvalue" style="color:#10b981;">{{ $active }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">👤 Connectés</div>
                <div class="lv-kvalue">{{ $connected }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">📱 Mobile</div>
                <div class="lv-kvalue">{{ $mobile }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">🖥️ Desktop</div>
                <div class="lv-kvalue">{{ $desktop }}</div>
            </x-filament::section>
        </div>

        <div class="lv-grid lv-2">
            {{-- Activité par île --}}
            <x-filament::section>
                <x-slot name="heading">🏝️ Activité par île</x-slot>
                <x-slot name="description">Répartition des visiteurs en ligne par territoire.</x-slot>

                @foreach($islandCounts as $island)
                    @php $share = $active > 0 ? round(($island['count'] / $active) * 100) : 0; @endphp
                    <div class="lv-island">
                        <span class="lv-flag">{{ $island['flag'] }}</span>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:.9rem;">
                                <span>{{ $island['name'] }}</span>
                                <span>{{ $island['count'] }} · {{ $share }}%</span>
                            </div>
                            <div class="lv-bar"><div style="width:{{ $share }}%"></div></div>
                        </div>
                    </div>
                @endforeach

                @if($active === 0)
                    <p style="opacity:.55;margin-top:.75rem;">Personne en ligne pour le moment.</p>
                @endif
            </x-filament::section>

            {{-- Flux en direct --}}
            <x-filament::section>
                <x-slot name="heading">📡 Flux en direct</x-slot>
                <x-slot name="description">{{ $active }} visiteur{{ $active > 1 ? 's' : '' }} actif{{ $active > 1 ? 's' : '' }}.</x-slot>

                <div class="lv-feed">
                    @forelse($visits as $visit)
                        <div class="lv-feeditem">
                            <div class="lv-trunc">
                                <div style="font-weight:700;" class="lv-trunc">
                                    {{ $islands[$visit->territoire] ?? '📍' }} {{ $visit->territoire ?: 'Non renseigné' }}
                                    @if($visit->user_id)<span class="lv-chip">membre</span>@endif
                                </div>
                                <div style="font-size:.8rem;opacity:.6;" class="lv-trunc">
                                    {{ $pageLabels[$visit->path] ?? $visit->path }} · {{ $visit->device }}
                                </div>
                            </div>
                            <div style="font-size:.72rem;font-weight:700;opacity:.55;white-space:nowrap;">
                                {{ $visit->last_seen_at->diffForHumans(null, true) }}
                            </div>
                        </div>
                    @empty
                        <div style="text-align:center;opacity:.55;padding:2.5rem 0;">
                            Aucun visiteur actif pour le moment.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Pages consultées maintenant --}}
        <x-filament::section>
            <x-slot name="heading">🔥 Pages consultées en ce moment</x-slot>

            @forelse($topPages as $path => $count)
                @php $share = $active > 0 ? round(($count / $active) * 100) : 0; @endphp
                <div style="padding:.6rem 0;border-bottom:1px solid rgba(148,163,184,.15);">
                    <div style="display:flex;justify-content:space-between;gap:1rem;font-weight:700;font-size:.9rem;">
                        <span class="lv-trunc">{{ $pageLabels[$path] ?? $path }}</span>
                        <span style="white-space:nowrap;">{{ $count }} 👀</span>
                    </div>
                    <div class="lv-bar"><div style="width:{{ $share }}%"></div></div>
                </div>
            @empty
                <p style="opacity:.55;">Aucune page consultée pour le moment.</p>
            @endforelse
        </x-filament::section>

    </div>
</x-filament-panels::page>
