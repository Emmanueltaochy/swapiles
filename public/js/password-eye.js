/**
 * Bouton « afficher / masquer » sur tous les champs de mot de passe.
 *
 * Saisir un mot de passe à l'aveugle sur un téléphone est la première cause
 * d'échec à la connexion et à l'inscription : on se trompe, on recommence, on
 * abandonne. Le champ reste masqué par défaut ; l'utilisateur décide.
 *
 * Appliqué automatiquement à TOUT champ de type mot de passe, y compris ceux
 * ajoutés plus tard : rien à penser au moment d'écrire un formulaire.
 */
(function () {
    'use strict';

    var OEIL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">'
        + '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

    var OEIL_BARRE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">'
        + '<path d="M2 12s3.5-7 10-7c2 0 3.8.7 5.2 1.6M22 12s-3.5 7-10 7c-2 0-3.8-.7-5.2-1.6"/>'
        + '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M3 3l18 18"/></svg>';

    function equiper(champ) {
        if (!champ || champ.dataset.oeilPose === '1') {
            return;
        }
        champ.dataset.oeilPose = '1';

        // Conteneur positionné, pour placer le bouton dans le champ.
        var conteneur = document.createElement('div');
        conteneur.style.position = 'relative';
        conteneur.className = champ.dataset.oeilWrapClass || '';

        champ.parentNode.insertBefore(conteneur, champ);
        conteneur.appendChild(champ);

        // Place pour le bouton, sans recouvrir le texte saisi.
        champ.style.paddingRight = '2.75rem';

        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.innerHTML = OEIL;
        bouton.setAttribute('aria-label', 'Afficher le mot de passe');
        bouton.setAttribute('aria-pressed', 'false');
        bouton.style.cssText = [
            'position:absolute', 'top:50%', 'right:0.5rem', 'transform:translateY(-50%)',
            'display:flex', 'align-items:center', 'justify-content:center',
            'width:2rem', 'height:2rem', 'padding:0', 'border:0', 'background:transparent',
            'color:#9ca3af', 'cursor:pointer', 'border-radius:9999px',
        ].join(';');

        bouton.addEventListener('mouseenter', function () { bouton.style.color = '#0f766e'; });
        bouton.addEventListener('mouseleave', function () { bouton.style.color = '#9ca3af'; });

        bouton.addEventListener('click', function () {
            var visible = champ.type === 'text';

            // Sur iOS, changer le type fait perdre le curseur : on le remet.
            var debut = champ.selectionStart;
            var fin = champ.selectionEnd;

            champ.type = visible ? 'password' : 'text';
            bouton.innerHTML = visible ? OEIL : OEIL_BARRE;
            bouton.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
            bouton.setAttribute('aria-pressed', visible ? 'false' : 'true');

            champ.focus();
            try {
                champ.setSelectionRange(debut, fin);
            } catch (e) { /* certains navigateurs refusent sur un champ mot de passe */ }
        });

        conteneur.appendChild(bouton);
    }

    function equiperTout(racine) {
        (racine || document).querySelectorAll('input[type="password"]').forEach(equiper);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { equiperTout(); });
    } else {
        equiperTout();
    }

    // Champs ajoutés après coup (fenêtres, contenu chargé dynamiquement).
    if (typeof MutationObserver === 'function') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (noeud) {
                    if (noeud.nodeType !== 1) return;
                    if (noeud.matches && noeud.matches('input[type="password"]')) equiper(noeud);
                    if (noeud.querySelectorAll) equiperTout(noeud);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
