/**
 * Partage d'annonce.
 *
 * Pourquoi ce fichier : dans l'application (coque Capacitor), une page web ne
 * peut pas ouvrir WhatsApp ou Facebook dans un nouvel onglet. Les boutons par
 * réseau ne faisaient donc RIEN quand on les touchait depuis l'app — ils ne
 * fonctionnaient que sur le site ouvert dans un navigateur.
 *
 * Règle appliquée :
 *   • application, ou navigateur mobile sachant partager  -> bouton « Partager »
 *     qui ouvre le menu du téléphone (WhatsApp, Instagram, Messages, Facebook…)
 *   • dans l'application, les liens par réseau sont masqués : ils ne peuvent pas
 *     fonctionner, autant ne pas les proposer
 *   • partout, « Copier le lien » reste disponible
 */
(function () {
    'use strict';

    function estDansApp() {
        var cap = window.Capacitor;
        return !!(cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform());
    }

    function partageNatifDispo() {
        return typeof navigator !== 'undefined' && typeof navigator.share === 'function';
    }

    function confirmerCopie(bouton) {
        var original = bouton.innerHTML;
        bouton.innerHTML = '<span aria-hidden="true">✅</span> Lien copié';
        setTimeout(function () { bouton.innerHTML = original; }, 1800);
    }

    function copier(url, bouton) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function () { confirmerCopie(bouton); })
                .catch(function () { window.prompt('Copiez le lien :', url); });
            return;
        }

        window.prompt('Copiez le lien :', url);
    }

    function preparer(racine) {
        var natif = racine.querySelector('[data-share-native]');
        var web = racine.querySelector('[data-share-web]');
        var astuce = racine.querySelector('[data-share-hint]');

        if (natif && partageNatifDispo()) {
            natif.hidden = false;
        }

        // Dans l'app, les liens par réseau ne peuvent pas s'ouvrir : on ne les
        // affiche que si le partage natif a pu prendre le relais.
        if (web && estDansApp()) {
            if (partageNatifDispo()) {
                web.hidden = true;
            }
            if (astuce) {
                astuce.hidden = false;
            }
        }
    }

    function initialiser() {
        document.querySelectorAll('[data-share-root]').forEach(preparer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }

    // Le partage natif doit partir du geste de l'utilisateur : on l'appelle
    // directement dans le gestionnaire de clic, sans étape intermédiaire.
    document.addEventListener('click', function (e) {
        var natif = e.target.closest('[data-share-native]');
        if (natif) {
            e.preventDefault();
            var url = natif.getAttribute('data-share-url');
            var texte = natif.getAttribute('data-share-text') || '';

            if (partageNatifDispo()) {
                navigator.share({ title: "Swap'Îles", text: texte, url: url }).catch(function () {});
            } else {
                copier(url, natif);
            }
            return;
        }

        var copie = e.target.closest('[data-share-copy]');
        if (copie) {
            e.preventDefault();
            copier(copie.getAttribute('data-share-url'), copie);
        }
    });
})();
