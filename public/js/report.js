/**
 * Fenêtre de signalement (annonce ou membre).
 *
 * Déroulé attendu par les membres, identique aux autres plateformes :
 *   1. motif + détails → « Signaler »
 *   2. « Contenu signalé » → « Souhaitez-vous bloquer X ? » → « Bloquer »
 *
 * Le formulaire est envoyé en arrière-plan : la page ne se recharge pas, donc
 * la confirmation reste visible (avant, le message de succès disparaissait
 * avec le rechargement et personne ne le voyait).
 *
 * Sans JavaScript, le formulaire reste un formulaire POST classique qui
 * fonctionne normalement (redirection + message de succès).
 */
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function ouvrir(modal) {
        modal.hidden = false;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function fermer(modal) {
        modal.hidden = true;
        modal.style.display = '';
        document.body.style.overflow = '';
    }

    function etape(modal, nom) {
        modal.querySelectorAll('[data-report-step]').forEach(function (el) {
            el.hidden = el.getAttribute('data-report-step') !== nom;
        });
    }

    // Ouverture
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-report-open]');
        if (!trigger) return;

        var modal = document.getElementById(trigger.getAttribute('data-report-open'));
        if (!modal) return;

        e.preventDefault();
        etape(modal, 'form');
        ouvrir(modal);
    });

    // Fermeture : bouton, clic sur le fond, touche Échap
    document.addEventListener('click', function (e) {
        var close = e.target.closest('[data-report-close]');
        if (close) {
            var modal = close.closest('[data-report-modal]');
            if (modal) { e.preventDefault(); fermer(modal); }
            return;
        }

        if (e.target.matches('[data-report-modal]')) {
            fermer(e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-report-modal]').forEach(function (modal) {
            if (!modal.hidden) fermer(modal);
        });
    });

    // Envoi du signalement
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-report-form]');
        if (!form) return;

        e.preventDefault();

        var modal = form.closest('[data-report-modal]');
        var bouton = form.querySelector('[data-report-submit]');
        var erreur = form.querySelector('[data-report-error]');

        if (erreur) { erreur.hidden = true; erreur.textContent = ''; }
        if (bouton) { bouton.disabled = true; bouton.textContent = 'Envoi…'; }

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: new FormData(form)
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (bouton) { bouton.disabled = false; bouton.textContent = 'Signaler'; }

                if (!res.ok || !res.data.ok) {
                    if (erreur) {
                        erreur.textContent = res.data.message || 'Signalement impossible pour le moment.';
                        erreur.hidden = false;
                    }
                    return;
                }

                etape(modal, 'done');

                var zone = modal.querySelector('[data-report-block]');
                if (res.data.block && zone) {
                    modal.querySelector('[data-report-block-name]').textContent = res.data.block.name;
                    zone.setAttribute('data-block-url', res.data.block.url);
                    zone.hidden = false;
                }
            })
            .catch(function () {
                if (bouton) { bouton.disabled = false; bouton.textContent = 'Signaler'; }
                if (erreur) {
                    erreur.textContent = 'Connexion impossible. Réessayez dans un instant.';
                    erreur.hidden = false;
                }
            });
    });

    // Blocage proposé après le signalement
    document.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-report-block-btn]');
        if (!bouton) return;

        var zone = bouton.closest('[data-report-block]');
        var modal = bouton.closest('[data-report-modal]');
        var url = zone && zone.getAttribute('data-block-url');
        if (!url) return;

        e.preventDefault();
        bouton.disabled = true;
        bouton.textContent = 'Blocage…';

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                zone.hidden = true;
                var confirmation = modal.querySelector('[data-report-block-done]');
                if (confirmation) {
                    confirmation.textContent = data.message || 'Membre bloqué.';
                    confirmation.hidden = false;
                }
            })
            .catch(function () {
                bouton.disabled = false;
                bouton.textContent = 'Bloquer';
            });
    });
})();
