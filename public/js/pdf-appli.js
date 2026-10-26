/**
 * PDF dans l'application.
 *
 * Pourquoi ce fichier : dans l'appli (coque Capacitor), ouvrir un PDF le
 * met à la place de l'écran, sans barre ni bouton retour : impossible d'en
 * sortir. Dans l'appli, un lien `data-apercu-appli` ouvre donc une page
 * d'aperçu Swap'Îles (avec « Retour »), et le bouton `data-partager-pdf` y
 * passe le fichier au menu de partage du téléphone (Fichiers, Mail…).
 * Sur le site (navigateur), rien ne change : le lien télécharge le PDF.
 */
(function () {
    'use strict';

    function estDansApp() {
        var cap = window.Capacitor;
        return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
    }

    // Lien vers un PDF : dans l'appli, on va sur la page d'aperçu.
    document.addEventListener('click', function (e) {
        var lien = e.target.closest && e.target.closest('a[data-apercu-appli]');
        if (!lien || !estDansApp()) return;
        e.preventDefault();
        e.stopPropagation();
        window.location.href = lien.getAttribute('data-apercu-appli');
    }, true);

    // Bouton « Enregistrer / partager le PDF » de la page d'aperçu.
    document.addEventListener('click', function (e) {
        var bouton = e.target.closest && e.target.closest('[data-partager-pdf]');
        if (!bouton) return;
        e.preventDefault();

        var url = bouton.getAttribute('data-partager-pdf');
        var nom = bouton.getAttribute('data-fichier') || 'swapiles.pdf';
        var texte = bouton.innerHTML;

        // Hors appli : téléchargement classique.
        if (!estDansApp()) { window.location.href = url; return; }

        bouton.innerHTML = '⏳ Préparation…';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('pdf'); return r.blob(); })
            .then(function (blob) {
                var fichier = new File([blob], nom, { type: 'application/pdf' });
                if (typeof navigator.share === 'function' && navigator.canShare && navigator.canShare({ files: [fichier] })) {
                    return navigator.share({ files: [fichier], title: nom });
                }
                throw new Error('partage');
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return; // partage annulé par le membre
                window.alert('Le partage n’est pas disponible sur ce téléphone. Ouvrez swapiles.com dans votre navigateur pour télécharger le PDF.');
            })
            .then(function () { bouton.innerHTML = texte; });
    });

    // Aperçu A4 : mis à la largeur de l'écran.
    function ajuster() {
        document.querySelectorAll('[data-apercu-a4]').forEach(function (cadre) {
            var iframe = cadre.querySelector('iframe');
            if (!iframe) return;
            var echelle = Math.min(1, cadre.clientWidth / 794);
            iframe.style.transform = 'scale(' + echelle + ')';
            cadre.style.height = Math.ceil(1123 * echelle) + 'px';
        });
    }
    window.addEventListener('resize', ajuster);
    document.addEventListener('turbo:load', ajuster);
    document.addEventListener('DOMContentLoaded', ajuster);
    ajuster();
})();
