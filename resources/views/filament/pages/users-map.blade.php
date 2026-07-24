<x-filament-panels::page>
    @php $num = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <x-filament::section>
            <x-slot name="heading">🗺️ Localisation des membres — {{ $territoireDisplay }}</x-slot>
            <x-slot name="description">Chaque point = un membre, positionné sur sa commune. Choisissez l'île puis, si besoin, filtrez par ville.</x-slot>

            {{-- Sélecteur de territoire --}}
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem;">
                @foreach($territoireTabs as $t => $meta)
                    <x-filament::button
                        tag="a"
                        :href="url()->current() . '?territoire=' . urlencode($t)"
                        :color="$territoire === $t ? 'primary' : 'gray'"
                        size="sm">
                        {{ $meta['display'] }} ({{ $num($meta['count']) }})
                    </x-filament::button>
                @endforeach
            </div>

            {{-- Filtre par ville + stats --}}
            <form method="GET" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:.6rem;">
                <input type="hidden" name="territoire" value="{{ $territoire }}">
                <div>
                    <label style="display:block;font-size:.72rem;font-weight:700;opacity:.6;margin-bottom:.2rem;">Filtrer par ville</label>
                    <select name="city" onchange="this.form.submit()"
                            style="border:1px solid rgba(148,163,184,.4);border-radius:.55rem;padding:.45rem .7rem;background:transparent;color:inherit;font-size:.9rem;min-width:220px;">
                        <option value="">Toutes les villes ({{ $num($totalMembers) }})</option>
                        @foreach($cities as $c)
                            <option value="{{ $c }}" @selected($selectedCity === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selectedCity !== '')
                    <x-filament::button tag="a" :href="url()->current() . '?territoire=' . urlencode($territoire)" size="sm" color="gray">Réinitialiser</x-filament::button>
                @endif

                <div style="display:flex;gap:1.25rem;margin-left:auto;flex-wrap:wrap;">
                    <div><div style="font-size:.72rem;font-weight:700;opacity:.55;">📍 Sur la carte</div><div style="font-size:1.4rem;font-weight:900;">{{ $num($shownCount) }}</div></div>
                    <div><div style="font-size:.72rem;font-weight:700;opacity:.55;">🏠 Membres sur l'île</div><div style="font-size:1.4rem;font-weight:900;">{{ $num($totalMembers) }}</div></div>
                    @if($approx > 0)
                        <div><div style="font-size:.72rem;font-weight:700;opacity:.55;">📌 Ville approximative</div><div style="font-size:1.4rem;font-weight:900;color:#f59e0b;">{{ $num($approx) }}</div></div>
                    @endif
                </div>
            </form>
            @if($approx > 0)
                <p style="margin-top:.6rem;font-size:.78rem;opacity:.55;">📌 {{ $num($approx) }} membre(s) avec une ville non reconnue sont placés au centre de l'île (point orange).</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <div id="swp-users-map" style="height:560px;width:100%;border-radius:.75rem;overflow:hidden;z-index:0;background:rgba(148,163,184,.1);"></div>
            <p id="swp-map-fallback" style="display:none;margin-top:.75rem;font-size:.85rem;opacity:.6;">
                ⚠️ La carte n'a pas pu se charger (connexion à OpenStreetMap bloquée). Les compteurs restent affichés ci-dessus.
            </p>
            @if($shownCount === 0)
                <p style="margin-top:.75rem;font-size:.9rem;opacity:.65;">Aucun membre géolocalisé pour ce filtre. Les membres doivent renseigner leur ville pour apparaître ici.</p>
            @endif
        </x-filament::section>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var points = @json($points);
            var center = @json($center);
            var zoom = @json($zoom);

            function init() {
                if (typeof L === 'undefined') {
                    document.getElementById('swp-map-fallback').style.display = 'block';
                    return;
                }
                var el = document.getElementById('swp-users-map');
                if (!el || el._leaflet_id) return;

                var map = L.map(el).setView(center, zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                var bounds = [];
                points.forEach(function (p) {
                    var color = p.approx ? '#f59e0b' : '#0d9488';
                    var fill = p.approx ? '#fbbf24' : '#14b8a6';
                    var marker = L.circleMarker([p.lat, p.lng], {
                        radius: 7, color: color, weight: 2, fillColor: fill, fillOpacity: 0.75
                    }).addTo(map);
                    var html = '<strong>' + (p.name || 'Membre') + '</strong><br>'
                        + (p.city ? p.city + (p.approx ? ' <span style="color:#f59e0b">(approx.)</span>' : '') + '<br>' : '')
                        + (p.email ? '<span style="opacity:.7">' + p.email + '</span><br>' : '')
                        + '<a href="' + p.url + '" style="color:#0d9488;font-weight:700;">Voir la fiche →</a>';
                    marker.bindPopup(html);
                    bounds.push([p.lat, p.lng]);
                });

                if (bounds.length > 1) {
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
                } else if (bounds.length === 1) {
                    map.setView(bounds[0], 13);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
</x-filament-panels::page>
