/**
 * Présence en direct (compteur « en ligne » de l'administration).
 *
 * Le serveur voit passer aussi des robots qui se font passer pour des
 * navigateurs, la vérification des publicités par Meta, les aperçus de liens…
 * Ici, c'est le navigateur qui confirme : la page est ouverte, à l'écran, et
 * quelqu'un l'a touchée (défilement, appui, clic, souris). Ensuite, un petit
 * signal toutes les 45 s tant que la page reste visible.
 *
 * Aucune donnée personnelle : le chemin de la page, appli ou non, et d'où
 * vient la visite (publicité Facebook, Google…).
 */
(function () {
    'use strict';

    var INTERVALLE = 45000;
    var CLE_SOURCE = 'swp_source_visite';
    var confirme = false;
    var dernier = 0;

    function dansLAppli() {
        var cap = window.Capacitor;
        return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
    }

    // Provenance lue UNE fois, sur la page d'arrivée (les pages suivantes
    // s'ouvrent sans rechargement et ne la connaissent plus).
    function provenance() {
        try {
            var gardee = sessionStorage.getItem(CLE_SOURCE);
            if (gardee) return JSON.parse(gardee);
        } catch (e) { /* navigation privée */ }

        var p = new URLSearchParams(window.location.search);
        var ref = document.referrer || '';
        try {
            if (ref && new URL(ref).host === window.location.host) ref = '';
        } catch (e) {
            ref = '';
        }

        var infos = {
            utm: p.get('utm_source')
                || (p.get('fbclid') ? 'fbclid' : '')
                || (p.get('gclid') ? 'gclid' : '')
                || (p.get('ttclid') ? 'ttclid' : ''),
            ref: ref.slice(0, 500),
        };

        try { sessionStorage.setItem(CLE_SOURCE, JSON.stringify(infos)); } catch (e) { /* idem */ }

        return infos;
    }

    var source = provenance();

    function battre(nouvellePage) {
        if (!confirme || document.visibilityState !== 'visible') return;

        dernier = Date.now();
        var jeton = document.querySelector('meta[name="csrf-token"]');

        fetch('/presence', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': jeton ? jeton.getAttribute('content') : '',
            },
            body: JSON.stringify({
                path: window.location.pathname,
                appli: dansLAppli(),
                utm: source.utm || null,
                ref: source.ref || null,
                page: !!nouvellePage,
            }),
        }).catch(function () { /* hors ligne : le prochain signal suffira */ });
    }

    function confirmer() {
        if (confirme) return;
        confirme = true;
        ['pointerdown', 'touchstart', 'scroll', 'keydown', 'mousemove'].forEach(function (evt) {
            window.removeEventListener(evt, confirmer, true);
        });
        battre(true);
        window.setInterval(function () { battre(false); }, INTERVALLE);
    }

    ['pointerdown', 'touchstart', 'scroll', 'keydown', 'mousemove'].forEach(function (evt) {
        window.addEventListener(evt, confirmer, { capture: true, passive: true });
    });

    // Changement de page sans rechargement (Turbo).
    document.addEventListener('turbo:load', function () {
        if (confirme) battre(true);
    });

    // Retour sur l'onglet ou dans l'appli : signal immédiat.
    document.addEventListener('visibilitychange', function () {
        if (confirme && document.visibilityState === 'visible' && Date.now() - dernier > 15000) battre(false);
    });
})();
