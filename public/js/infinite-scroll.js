/**
 * Défilement infini des listes d'articles (recherche, accueil, îles,
 * catégories, dressings).
 *
 * Les numéros de page en bas de liste coupaient l'élan : il fallait viser un
 * petit chiffre pour voir la suite. Désormais, la page suivante arrive toute
 * seule à l'approche du bas, comme sur Vinted ou Instagram.
 *
 * La pagination classique reste dans la page (moteurs de recherche, et
 * navigateur sans JavaScript) : elle est seulement masquée. Après quelques
 * pages chargées d'elles-mêmes, un bouton « Voir plus d'articles » prend le
 * relais, pour que le bas de page (liens utiles) reste atteignable.
 *
 * Grille concernée : [data-defilement-infini], avec l'adresse de la page
 * suivante dans data-page-suivante. Pagination : [data-pagination].
 */
(function () {
    'use strict';

    // Pages chargées automatiquement avant de passer au bouton.
    var AUTO_MAX = 8;
    // On anticipe : la suite est demandée bien avant d'atteindre le bas.
    var MARGE = 900;

    var STYLE_BOUTON = 'appearance:none;border:1px solid #d1d5db;background:#fff;color:#0f766e;'
        + 'font-weight:700;font-size:15px;border-radius:9999px;padding:12px 28px;cursor:pointer';
    var STYLE_ROND = 'width:28px;height:28px;border:3px solid #ccfbf1;border-top-color:#0d9488;'
        + 'border-radius:50%;animation:swp-infini-tourne .8s linear infinite';

    function ajouterAnimation() {
        if (document.getElementById('swp-infini-style')) return;
        var style = document.createElement('style');
        style.id = 'swp-infini-style';
        style.textContent = '@keyframes swp-infini-tourne{to{transform:rotate(360deg)}}';
        document.head.appendChild(style);
    }

    function cleCarte(element) {
        var lien = element.matches && element.matches('a[href]') ? element : element.querySelector && element.querySelector('a[href]');
        return lien ? lien.getAttribute('href') : null;
    }

    function preparer(grille) {
        // Déjà préparée sur cette page (DOMContentLoaded puis turbo:load).
        if (grille._swpInfini) return;
        grille._swpInfini = true;

        // Page restaurée par le retour arrière : on repart de l'état gardé
        // (articles déjà chargés), sans les commandes de la visite précédente.
        var ancien = grille.parentNode.querySelector('[data-infini-controle]');
        if (ancien) ancien.remove();

        if (!grille.getAttribute('data-page-suivante')) return;

        var pagination = document.querySelector('[data-pagination]');
        if (pagination) pagination.hidden = true;

        ajouterAnimation();

        var controle = document.createElement('div');
        controle.setAttribute('data-infini-controle', '');
        controle.style.cssText = 'display:flex;justify-content:center;padding:32px 0 8px;min-height:60px';

        var rond = document.createElement('div');
        rond.setAttribute('role', 'status');
        rond.setAttribute('aria-label', 'Chargement des articles suivants');
        rond.style.cssText = STYLE_ROND;
        rond.hidden = true;

        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.setAttribute('data-infini-plus', '');
        bouton.textContent = 'Voir plus d’articles';
        bouton.style.cssText = STYLE_BOUTON;
        bouton.hidden = true;

        controle.appendChild(rond);
        controle.appendChild(bouton);
        grille.insertAdjacentElement('afterend', controle);

        var pages = parseInt(grille.getAttribute('data-pages-chargees') || '0', 10);
        var enCours = false;
        var actif = true;
        // Après un échec (réseau coupé), on n'insiste pas à chaque mouvement
        // de doigt : on attend le bouton, ou le retour du réseau.
        var enPause = false;

        function terminer() {
            rond.hidden = true;
            bouton.hidden = true;
            actif = false;
        }

        function procheDuBas() {
            return controle.getBoundingClientRect().top < window.innerHeight + MARGE;
        }

        function charger() {
            var adresse = grille.getAttribute('data-page-suivante');
            if (!adresse || enCours) return;

            enCours = true;
            rond.hidden = false;
            bouton.hidden = true;

            fetch(adresse, {
                credentials: 'same-origin',
                headers: { 'Accept': 'text/html', 'X-Swp-Infini': '1' },
            })
                .then(function (reponse) {
                    if (!reponse.ok) throw new Error('page ' + reponse.status);
                    return reponse.text();
                })
                .then(function (html) {
                    var page = new DOMParser().parseFromString(html, 'text/html');
                    var suite = page.querySelector('[data-defilement-infini]');
                    if (!suite) throw new Error('grille absente');

                    // Un article publié entre-temps décale les pages : on
                    // n'affiche jamais deux fois le même.
                    var deja = {};
                    Array.prototype.forEach.call(grille.children, function (carte) {
                        var cle = cleCarte(carte);
                        if (cle) deja[cle] = true;
                    });

                    Array.prototype.slice.call(suite.children).forEach(function (carte) {
                        var cle = cleCarte(carte);
                        if (!cle || deja[cle]) return;
                        deja[cle] = true;
                        grille.appendChild(document.importNode(carte, true));
                    });

                    pages++;
                    grille.setAttribute('data-pages-chargees', String(pages));
                    grille.setAttribute('data-page-suivante', suite.getAttribute('data-page-suivante') || '');
                })
                .then(function () {
                    enCours = false;
                    rond.hidden = true;

                    if (!grille.getAttribute('data-page-suivante')) {
                        terminer();
                        return;
                    }

                    if (pages >= AUTO_MAX) {
                        bouton.hidden = false;
                    } else if (procheDuBas()) {
                        // Écran encore loin d'être rempli : on enchaîne.
                        charger();
                    }
                })
                .catch(function () {
                    // Réseau coupé ou page en erreur : on laisse la main.
                    enCours = false;
                    enPause = true;
                    rond.hidden = true;
                    bouton.textContent = 'Voir plus d’articles';
                    bouton.hidden = false;
                });
        }

        bouton.addEventListener('click', function () {
            enPause = false;
            charger();
        });

        // On regarde la position à chaque défilement (une fois par image
        // affichée). Pas d'IntersectionObserver : un défilement rapide qui
        // saute par-dessus le bas de la liste ne le déclenche pas, et la
        // suite n'arrivait jamais.
        var prevu = false;
        function surveiller() {
            if (prevu) return;
            prevu = true;
            window.requestAnimationFrame(function () {
                prevu = false;
                if (actif && !enPause && pages < AUTO_MAX && procheDuBas()) charger();
            });
        }

        // Quitter la page (Turbo) : on arrête de surveiller.
        var options = { passive: true };
        if (typeof window.swpPage === 'function') options.signal = window.swpPage();
        window.addEventListener('scroll', surveiller, options);
        window.addEventListener('resize', surveiller, options);
        window.addEventListener('online', function () {
            enPause = false;
            surveiller();
        }, options);

        if (pages >= AUTO_MAX) {
            bouton.hidden = false;
        } else {
            surveiller();
        }
    }

    function initialiser() {
        document.querySelectorAll('[data-defilement-infini]').forEach(preparer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }

    // Pages ouvertes sans rechargement (Turbo), y compris au retour arrière.
    document.addEventListener('turbo:load', initialiser);
})();
