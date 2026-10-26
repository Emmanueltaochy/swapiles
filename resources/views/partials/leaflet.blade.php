{{--
    Chargeur de cartes (Leaflet). Avec la navigation rapide du site, le script
    d'une carte peut s'exécuter avant que Leaflet soit chargé : la carte restait
    alors grise. swpLeaflet(fn) attend que Leaflet soit prêt, puis lance fn.
--}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script>
    window.swpLeaflet = window.swpLeaflet || function (lancer) {
        if (window.L && window.L.map) { lancer(); return; }
        var attente = window.swpLeafletAttente = window.swpLeafletAttente || [];
        attente.push(lancer);
        if (document.getElementById('swp-leaflet-js')) return;
        var s = document.createElement('script');
        s.id = 'swp-leaflet-js';
        s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        s.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        s.crossOrigin = '';
        s.onload = function () {
            (window.swpLeafletAttente || []).splice(0).forEach(function (f) {
                try { f(); } catch (e) { console.error(e); }
            });
        };
        s.onerror = function () { document.getElementById('swp-leaflet-js')?.remove(); };
        document.head.appendChild(s);
    };
</script>
