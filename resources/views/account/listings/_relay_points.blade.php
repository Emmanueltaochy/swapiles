{{-- Points relais acceptés pour cette annonce (surcharge le défaut du compte).
     Réservé aux annonces CB. Vide = on utilise les relais par défaut du vendeur. --}}
@if(($relayPoints ?? collect())->isNotEmpty())
    <div id="relay_box" class="rounded-xl border border-teal-100 bg-teal-50/50 p-4 transition">
        <p class="font-semibold text-teal-950">🏪 Points relais acceptés <span class="font-normal text-teal-700">(facultatif)</span></p>
        <p id="relay_cb_hint" class="mt-1 hidden rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
            🔒 Active le <strong>paiement CB sécurisé</strong> ci-dessus pour proposer le retrait en point relais (+ de ventes, colis protégé).
        </p>
        <p class="mt-1 mb-3 text-sm text-teal-800">
            Coche les commerçants où tu acceptes de déposer ce colis. L'acheteur choisira le plus proche de chez lui.
            Laisse vide pour utiliser tes points relais par défaut (réglés dans ton profil).
        </p>

        @php
            $relayGeo = $relayPoints->map(function ($rp) {
                $c = $rp->coordinates();
                return $c ? ['id' => $rp->id, 'name' => $rp->name, 'address' => $rp->fullAddress(), 'lat' => $c[0], 'lng' => $c[1]] : null;
            })->filter()->values();
        @endphp
        @if($relayGeo->isNotEmpty())
            <div id="relay_box_map" class="mb-3 h-52 w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
                 data-points='@json($relayGeo)'></div>
            <p class="mb-3 text-xs text-gray-500">Astuce : clique un repère sur la carte pour cocher/décocher ce point relais.</p>
        @endif

        <div class="space-y-2">
            @foreach($relayPoints as $rp)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50">
                    <input type="checkbox" name="relay_point_ids[]" value="{{ $rp->id }}"
                           class="mt-1 rounded text-teal-600 focus:ring-teal-500"
                           @checked(in_array($rp->id, old('relay_point_ids', $selectedRelayIds ?? [])))>
                    <span class="min-w-0">
                        <span class="block font-semibold text-gray-900">{{ $rp->name }}</span>
                        @if($rp->fullAddress())
                            <span class="block text-sm text-gray-500">{{ $rp->fullAddress() }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    @if($relayGeo->isNotEmpty())
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (function () {
                var el = document.getElementById('relay_box_map');
                if (!el || el._leaflet_id || typeof L === 'undefined') return;

                var points;
                try { points = JSON.parse(el.dataset.points); } catch (e) { return; }
                if (!points || !points.length) return;

                function boxFor(id) {
                    return document.querySelector('input[name="relay_point_ids[]"][value="' + id + '"]');
                }

                var map = L.map(el, { scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18, attribution: '© OpenStreetMap'
                }).addTo(map);

                var markers = {}, bounds = [];

                function style(id) {
                    var box = boxFor(id);
                    var on = box && box.checked;
                    return {
                        radius: 9, weight: 2,
                        color: on ? '#0d9488' : '#9ca3af',
                        fillColor: on ? '#14b8a6' : '#d1d5db',
                        fillOpacity: on ? 0.9 : 0.6
                    };
                }

                points.forEach(function (p) {
                    var mk = L.circleMarker([p.lat, p.lng], style(p.id)).addTo(map);
                    mk.bindTooltip(p.name);
                    mk.on('click', function () {
                        var box = boxFor(p.id);
                        if (!box || box.disabled) return;   // CB non activée : non modifiable
                        box.checked = !box.checked;
                        box.dispatchEvent(new Event('change', { bubbles: true }));
                        mk.setStyle(style(p.id));
                    });
                    markers[p.id] = mk;
                    bounds.push([p.lat, p.lng]);
                });

                // Coche/décoche depuis la liste -> met à jour la couleur du repère.
                points.forEach(function (p) {
                    var box = boxFor(p.id);
                    if (box) box.addEventListener('change', function () { markers[p.id].setStyle(style(p.id)); });
                });

                if (bounds.length === 1) { map.setView(bounds[0], 14); }
                else { map.fitBounds(bounds, { padding: [30, 30] }); }

                setTimeout(function () { map.invalidateSize(); }, 60);
            })();
        </script>
    @endif
@endif
