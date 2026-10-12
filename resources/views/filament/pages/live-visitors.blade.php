<x-filament-panels::page>
    <style>
        .lv-grid{display:grid;gap:1rem}
        .lv-kpi{grid-template-columns:repeat(5,minmax(0,1fr))}
        .lv-2{grid-template-columns:1.35fr .65fr}
        .lv-3{grid-template-columns:repeat(3,minmax(0,1fr))}
        @media(max-width:1200px){.lv-kpi{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:1024px){.lv-2,.lv-3{grid-template-columns:1fr}}
        @media(max-width:640px){.lv-kpi{grid-template-columns:repeat(2,minmax(0,1fr))}}
        .lv-klabel{font-size:.78rem;font-weight:700;opacity:.6}
        .lv-kvalue{font-size:2rem;font-weight:800;line-height:1;margin-top:.45rem}
        .lv-ksub{font-size:.75rem;opacity:.55;margin-top:.4rem}
        .lv-pulse{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;background:#10b981;box-shadow:0 0 0 0 rgba(16,185,129,.6);animation:lvPulse 1.6s infinite}
        @keyframes lvPulse{70%{box-shadow:0 0 0 .55rem rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}
        .lv-ligne{padding:.55rem 0;border-bottom:1px solid rgba(148,163,184,.15)}
        .lv-ligne:last-child{border-bottom:0}
        .lv-ligne-tete{display:flex;justify-content:space-between;gap:1rem;font-weight:700;font-size:.88rem}
        .lv-bar{height:.45rem;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:.35rem}
        .lv-bar>div{height:100%;background:#0d9488;border-radius:999px;transition:width .5s ease}
        .lv-feed{display:flex;flex-direction:column;gap:.55rem;max-height:540px;overflow:auto;padding-right:.3rem}
        .lv-item{display:flex;justify-content:space-between;gap:.8rem;border:1px solid rgba(148,163,184,.22);border-radius:.9rem;padding:.65rem .8rem}
        .lv-trunc{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lv-chip{display:inline-block;font-size:.66rem;font-weight:800;padding:.1rem .45rem;border-radius:999px;background:rgba(148,163,184,.18);margin-left:.25rem}
        .lv-chip-membre{background:rgba(13,148,136,.15);color:#0d9488}
        .lv-chip-pub{background:rgba(59,130,246,.15);color:#2563eb}
        .lv-note{font-size:.8rem;opacity:.6;line-height:1.45}
    </style>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    @php
        $d = $this->getDonnees();
        $c = $d['compte'];
        $total = max(1, $c['humains']);
    @endphp

    <div wire:poll.10s style="display:flex;flex-direction:column;gap:1.25rem;">

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;">
            <span class="lv-pulse"></span>
            <span style="font-weight:800;">En direct</span>
            <span style="opacity:.55;font-size:.85rem;">· actualisé toutes les 10 s · présents dans les 3 dernières minutes</span>
        </div>

        {{-- Chiffres clés --}}
        <div class="lv-grid lv-kpi">
            <x-filament::section>
                <div class="lv-klabel">🟢 Humains en ligne</div>
                <div class="lv-kvalue" style="color:#10b981;" data-lv-humains>{{ $c['humains'] }}</div>
                <div class="lv-ksub">confirmés par leur navigateur</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">👤 Membres connectés</div>
                <div class="lv-kvalue">{{ $c['membres'] }}</div>
                <div class="lv-ksub">{{ $c['humains'] - $c['membres'] }} visiteur(s) sans compte</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">📲 Dans l'appli</div>
                <div class="lv-kvalue">{{ $c['appli'] }}</div>
                <div class="lv-ksub">iPhone et Android</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">🌐 Site : mobile · ordinateur</div>
                <div class="lv-kvalue">{{ $c['mobile'] }} · {{ $c['ordinateur'] }}</div>
                <div class="lv-ksub">navigateur internet</div>
            </x-filament::section>
            <x-filament::section>
                <div class="lv-klabel">🤖 Trafic non confirmé</div>
                <div class="lv-kvalue" style="opacity:.55;">{{ $c['non_confirmes'] }}</div>
                <div class="lv-ksub">robots, vérification des pubs, aperçus de liens — non comptés</div>
            </x-filament::section>
        </div>

        {{-- Carte + îles --}}
        <div class="lv-grid lv-2">
            <x-filament::section>
                <x-slot name="heading">🗺️ Carte en direct</x-slot>
                <x-slot name="description">Membres connectés placés sur leur commune ; les autres visiteurs sont regroupés par île (leur position exacte n'est pas connue).</x-slot>

                <div wire:ignore>
                    <div id="lv-carte" style="height:420px;width:100%;border-radius:.75rem;overflow:hidden;z-index:0;background:rgba(148,163,184,.1);"></div>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem;" id="lv-iles-boutons">
                        <x-filament::button size="xs" color="gray" data-lv-zoom="toutes">Toutes les îles</x-filament::button>
                        @foreach(\App\Filament\Pages\LiveVisitors::DRAPEAUX as $ile => $drapeau)
                            <x-filament::button size="xs" color="gray" :data-lv-zoom="$ile">{{ $drapeau }} {{ $ile }}</x-filament::button>
                        @endforeach
                    </div>
                    <p id="lv-carte-erreur" style="display:none;margin-top:.6rem;" class="lv-note">⚠️ La carte n'a pas pu se charger (OpenStreetMap inaccessible). Les chiffres restent à jour.</p>
                </div>
                <script type="application/json" id="lv-points">@json($d['points'])</script>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">🏝️ Par île</x-slot>
                @foreach($d['iles'] as $ile)
                    <div class="lv-ligne">
                        <div class="lv-ligne-tete">
                            <span>{{ $ile['drapeau'] }} {{ $ile['ile'] }}</span>
                            <span>{{ $ile['nombre'] }} · {{ round($ile['nombre'] / $total * 100) }} %</span>
                        </div>
                        <div class="lv-bar"><div style="width:{{ round($ile['nombre'] / $total * 100) }}%"></div></div>
                    </div>
                @endforeach
            </x-filament::section>
        </div>

        {{-- Provenance, appareils, pages --}}
        <div class="lv-grid lv-3">
            <x-filament::section>
                <x-slot name="heading">📣 D'où ils viennent</x-slot>
                <x-slot name="description">Publicités, moteurs de recherche, liens partagés…</x-slot>
                @foreach($d['sources'] as $source => $nombre)
                    @continue($nombre === 0)
                    <div class="lv-ligne">
                        <div class="lv-ligne-tete"><span>{{ \App\Support\VisiteursEnDirect::SOURCES[$source] }}</span><span>{{ $nombre }}</span></div>
                        <div class="lv-bar"><div style="width:{{ round($nombre / $total * 100) }}%"></div></div>
                    </div>
                @endforeach
                @if($c['humains'] === 0)<p class="lv-note">Personne en ligne pour le moment.</p>@endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">📱 Appareils</x-slot>
                @foreach($d['appareils'] as $appareil => $nombre)
                    <div class="lv-ligne">
                        <div class="lv-ligne-tete"><span>{{ \App\Support\VisiteursEnDirect::APPAREILS[$appareil] }}</span><span>{{ $nombre }}</span></div>
                        <div class="lv-bar"><div style="width:{{ round($nombre / $total * 100) }}%"></div></div>
                    </div>
                @endforeach
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">🔥 Pages consultées</x-slot>
                @forelse($d['pages'] as $page => $nombre)
                    <div class="lv-ligne">
                        <div class="lv-ligne-tete"><span class="lv-trunc">{{ $page }}</span><span>{{ $nombre }}</span></div>
                        <div class="lv-bar"><div style="width:{{ round($nombre / $total * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="lv-note">Aucune page consultée pour le moment.</p>
                @endforelse
            </x-filament::section>
        </div>

        {{-- Flux --}}
        <x-filament::section>
            <x-slot name="heading">📡 Flux en direct</x-slot>
            <x-slot name="description">{{ $c['humains'] }} visiteur(s) en ligne, du plus récent au plus ancien.</x-slot>

            <div class="lv-feed">
                @forelse($d['visites'] as $v)
                    <div class="lv-item">
                        <div class="lv-trunc">
                            <div class="lv-trunc" style="font-weight:700;">
                                {{ $v['drapeau'] }}
                                @if($v['membre'])
                                    <a href="{{ $v['lien_membre'] }}" style="color:#0d9488;">{{ $v['membre'] }}</a><span class="lv-chip lv-chip-membre">membre</span>
                                @else
                                    Visiteur · {{ $v['ile'] }}
                                @endif
                                <span class="lv-chip {{ $v['pub'] ? 'lv-chip-pub' : '' }}">{{ $v['source'] }}</span>
                            </div>
                            <div class="lv-trunc" style="font-size:.8rem;opacity:.6;">
                                {{ $v['page'] }} · {{ $v['appareil'] }} · {{ $v['pages'] }} page(s)
                            </div>
                        </div>
                        <div style="font-size:.72rem;font-weight:700;opacity:.55;white-space:nowrap;">depuis {{ $v['depuis'] }}</div>
                    </div>
                @empty
                    <div style="text-align:center;opacity:.55;padding:2.5rem 0;">Aucun visiteur en ligne pour le moment.</div>
                @endforelse
            </div>
        </x-filament::section>

        <p class="lv-note">
            Comment on compte : une personne n'est comptée que si sa page s'est vraiment ouverte et qu'elle l'a touchée
            (défilement, appui, clic). Les robots qui se font passer pour des navigateurs, la vérification des publicités
            par Meta ou Google et les aperçus de liens (WhatsApp, Messenger…) apparaissent seulement dans « Trafic non confirmé ».
        </p>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var VUES = {
                'toutes': null,
                'La Réunion': [-21.1151, 55.5364, 10],
                'Martinique': [14.6415, -61.0242, 10],
                'Guadeloupe': [16.25, -61.55, 10],
                'Guyane': [4.0, -53.0, 7],
                'Mayotte': [-12.8275, 45.1662, 11],
            };
            var carte, calque, dernier = '';

            function lirePoints() {
                var el = document.getElementById('lv-points');
                return el ? el.textContent : '[]';
            }

            function dessiner(force) {
                var brut = lirePoints();
                if (!force && brut === dernier) return;
                dernier = brut;
                calque.clearLayers();

                JSON.parse(brut).forEach(function (p) {
                    var membre = p.type === 'membre';
                    var rayon = Math.min(34, 9 + Math.sqrt(p.nombre) * 6);
                    var cercle = L.circleMarker([p.lat, p.lng], {
                        radius: rayon,
                        color: membre ? '#0f766e' : '#2563eb',
                        weight: 2,
                        fillColor: membre ? '#14b8a6' : '#3b82f6',
                        fillOpacity: .55,
                    }).addTo(calque);
                    cercle.bindTooltip(String(p.nombre), { permanent: true, direction: 'center', className: 'lv-etiquette' });
                    cercle.bindPopup('<strong>' + p.libelle + '</strong><br>' + p.nombre + ' '
                        + (membre ? 'membre(s) connecté(s)' : 'visiteur(s) sur l’île'));
                });
            }

            function cadrer(ile) {
                var v = VUES[ile];
                if (v) { carte.setView([v[0], v[1]], v[2]); return; }
                // Toutes les îles : de la Guyane à Mayotte.
                carte.invalidateSize();
                carte.fitBounds([[-22, -62], [17, 56]], { padding: [6, 6] });
            }

            function init() {
                var el = document.getElementById('lv-carte');
                if (!el || el._leaflet_id) return;
                if (typeof L === 'undefined') {
                    document.getElementById('lv-carte-erreur').style.display = 'block';
                    return;
                }

                var style = document.createElement('style');
                style.textContent = '.leaflet-tooltip.lv-etiquette{background:transparent;border:0;box-shadow:none;padding:0;color:#fff;font-weight:800;font-size:12px;text-shadow:0 1px 2px rgba(0,0,0,.45)}'
                    + '.leaflet-tooltip.lv-etiquette:before{display:none}';
                document.head.appendChild(style);

                carte = L.map(el, { worldCopyJump: true, zoomSnap: 0.25 });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(carte);
                calque = L.layerGroup().addTo(carte);

                // On ouvre sur l'île où il y a le plus de monde.
                var points = JSON.parse(lirePoints());
                var parIle = {};
                points.forEach(function (p) {
                    var ile = Object.keys(VUES).filter(function (k) {
                        var v = VUES[k]; return v && Math.abs(v[0] - p.lat) < 3 && Math.abs(v[1] - p.lng) < 3;
                    })[0];
                    if (ile) parIle[ile] = (parIle[ile] || 0) + p.nombre;
                });
                var iles = Object.keys(parIle).sort(function (a, b) { return parIle[b] - parIle[a]; });
                cadrer(iles.length ? iles[0] : 'La Réunion');

                document.querySelectorAll('[data-lv-zoom]').forEach(function (b) {
                    b.addEventListener('click', function () { cadrer(b.getAttribute('data-lv-zoom')); });
                });

                dessiner(true);
                // Le reste de la page se met à jour tout seul (Livewire) ;
                // la carte relit ses points sans se recharger.
                window.setInterval(function () { dessiner(false); }, 3000);
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
            document.addEventListener('livewire:navigated', init);
        })();
    </script>
</x-filament-panels::page>
