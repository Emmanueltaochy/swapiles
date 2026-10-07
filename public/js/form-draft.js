/**
 * Sauvegarde locale d'un formulaire long, pendant la saisie.
 *
 * Sur une connexion mobile instable, perdre son formulaire de dépôt après dix
 * minutes de saisie et cinq photos, c'est une annonce qui ne sera jamais
 * publiée — et souvent un vendeur qui ne revient pas.
 *
 * Le brouillon reste dans le navigateur de la personne : rien n'est envoyé au
 * serveur tant qu'elle ne publie pas. Il est effacé dès la publication réussie.
 *
 * Usage : ajouter data-brouillon="une-cle" sur le <form>.
 * Les champs sensibles (mot de passe, fichiers, jetons) ne sont jamais gardés.
 */
(function () {
    'use strict';

    var PREFIXE = 'swapiles:brouillon:';
    var EXPIRATION_MS = 7 * 24 * 60 * 60 * 1000; // une semaine

    function stockageDispo() {
        try {
            window.localStorage.setItem('swapiles:test', '1');
            window.localStorage.removeItem('swapiles:test');
            return true;
        } catch (e) {
            // Navigation privée, stockage bloqué : on n'insiste pas.
            return false;
        }
    }

    function champConservable(champ) {
        if (!champ.name) return false;
        if (champ.type === 'password' || champ.type === 'file' || champ.type === 'hidden') return false;
        if (champ.name === '_token' || champ.name === '_method') return false;
        if (champ.name === 'submission_token') return false;   // jeton anti-doublon
        return true;
    }

    function lire(form) {
        var donnees = {};

        form.querySelectorAll('input, select, textarea').forEach(function (champ) {
            if (!champConservable(champ)) return;

            if (champ.type === 'checkbox' || champ.type === 'radio') {
                if (champ.checked) {
                    donnees[champ.name] = donnees[champ.name] || [];
                    donnees[champ.name].push(champ.value);
                }
                return;
            }

            if (champ.value !== '') {
                donnees[champ.name] = champ.value;
            }
        });

        return donnees;
    }

    function restaurer(form, donnees) {
        form.querySelectorAll('input, select, textarea').forEach(function (champ) {
            if (!champConservable(champ)) return;
            if (!(champ.name in donnees)) return;

            var valeur = donnees[champ.name];

            if (champ.type === 'checkbox' || champ.type === 'radio') {
                if (Array.isArray(valeur)) {
                    champ.checked = valeur.indexOf(champ.value) !== -1;
                }
                return;
            }

            // On n'écrase jamais une valeur déjà présente (« old » après erreur
            // de validation, ou édition d'une annonce existante).
            if (champ.value === '') {
                champ.value = valeur;
            }
        });

        // Les champs dépendants (catégories en cascade) doivent se recalculer.
        form.querySelectorAll('select').forEach(function (select) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function initialiser(form) {
        // Une seule fois par formulaire : avec la navigation sans rechargement,
        // l'initialisation est relancée à chaque page affichée.
        if (form.dataset.brouillonPret) return;
        form.dataset.brouillonPret = '1';

        var cle = PREFIXE + form.dataset.brouillon;
        var minuteur = null;

        // --- Restauration -----------------------------------------------------
        try {
            var brut = window.localStorage.getItem(cle);
            if (brut) {
                var paquet = JSON.parse(brut);

                if (paquet && paquet.enregistreLe && (Date.now() - paquet.enregistreLe) < EXPIRATION_MS) {
                    restaurer(form, paquet.donnees || {});
                    annoncer(form);
                } else {
                    window.localStorage.removeItem(cle);
                }
            }
        } catch (e) {
            try { window.localStorage.removeItem(cle); } catch (e2) {}
        }

        // --- Sauvegarde, au fil de la saisie ----------------------------------
        function sauvegarder() {
            try {
                window.localStorage.setItem(cle, JSON.stringify({
                    enregistreLe: Date.now(),
                    donnees: lire(form),
                }));
            } catch (e) { /* quota plein : sans gravité */ }
        }

        form.addEventListener('input', function () {
            clearTimeout(minuteur);
            minuteur = setTimeout(sauvegarder, 600);
        });
        form.addEventListener('change', sauvegarder);

        // Le formulaire part : le brouillon n'a plus de raison d'être.
        form.addEventListener('submit', function () {
            try { window.localStorage.removeItem(cle); } catch (e) {}
        });
    }

    /** Signale discrètement que la saisie précédente a été retrouvée. */
    function annoncer(form) {
        if (form.querySelector('[data-brouillon-avis]')) return;

        var avis = document.createElement('p');
        avis.setAttribute('data-brouillon-avis', '');
        avis.textContent = '✍️ Nous avons retrouvé votre saisie précédente.';
        avis.style.cssText = 'margin:0 0 12px;padding:10px 14px;border-radius:12px;'
            + 'background:#ecfdf5;color:#065f46;font-size:14px;';

        form.insertBefore(avis, form.firstChild);
        setTimeout(function () { avis.remove(); }, 6000);
    }

    if (!stockageDispo()) {
        return;
    }

    function demarrer() {
        document.querySelectorAll('form[data-brouillon]').forEach(initialiser);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }

    // Pages ouvertes sans rechargement (Turbo) : ce script reste en mémoire,
    // on équipe les formulaires de chaque nouvelle page.
    document.addEventListener('turbo:load', demarrer);
})();
