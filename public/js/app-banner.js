/**
 * Bandeau « Télécharger l'appli Swap'Îles » sur le site.
 *
 * Affiché seulement sur téléphone (iPhone, iPad, Android), jamais dans
 * l'application elle-même, et plus du tout pendant 30 jours après qu'on l'a
 * fermé. Le bouton mène au bon store selon le téléphone.
 * (Les membres connectés qui utilisent déjà l'appli ne le reçoivent pas :
 * voir partials/app-banner.blade.php.)
 */
(function () {
    'use strict';

    var CLE = 'swp_bandeau_appli_ferme';
    var DUREE = 30 * 24 * 3600 * 1000;

    function dansLAppli() {
        var cap = window.Capacitor;
        return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
    }

    function plateforme() {
        var ua = navigator.userAgent || '';
        if (/android/i.test(ua)) return 'android';
        if (/iPhone|iPad|iPod/i.test(ua)) return 'ios';
        // iPad récent : il se présente comme un Mac, mais il est tactile.
        if (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1) return 'ios';
        return null;
    }

    function fermeRecemment() {
        try {
            var t = parseInt(localStorage.getItem(CLE) || '0', 10);
            return t > 0 && Date.now() - t < DUREE;
        } catch (e) {
            return false;
        }
    }

    function initialiser() {
        var bandeau = document.querySelector('[data-bandeau-appli]');
        if (!bandeau) return;

        var os = plateforme();
        if (dansLAppli() || !os || fermeRecemment()) {
            bandeau.remove();
            return;
        }

        var lien = bandeau.querySelector('[data-bandeau-lien]');
        lien.href = bandeau.getAttribute(os === 'ios' ? 'data-lien-ios' : 'data-lien-android');
        lien.textContent = os === 'ios' ? 'Obtenir' : 'Installer';
        // Le store s'ouvre hors de la navigation du site.
        lien.setAttribute('data-turbo', 'false');

        bandeau.querySelector('[data-bandeau-fermer]').addEventListener('click', function () {
            try { localStorage.setItem(CLE, String(Date.now())); } catch (e) { /* navigation privée */ }
            bandeau.remove();
        });

        bandeau.hidden = false;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }

    // Pages ouvertes sans rechargement (Turbo) : le bandeau est rendu à chaque page.
    document.addEventListener('turbo:load', initialiser);
})();
