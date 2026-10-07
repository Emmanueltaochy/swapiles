/**
 * Favoris : réaction immédiate, sans rechargement.
 *
 * Le bouton rechargeait la page entière à chaque clic. Sur une connexion
 * mobile, cela veut dire une à deux secondes d'attente, la position de défilement
 * perdue, et l'impression d'un site plutôt que d'une application.
 *
 * Le cœur change maintenant instantanément, et l'appel part en arrière-plan.
 * Si le serveur refuse, on remet l'état précédent : on ne laisse jamais un
 * affichage qui ment.
 */
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function peindre(bouton, favori) {
        bouton.textContent = favori ? '❤️' : '🤍';
        bouton.setAttribute('aria-pressed', favori ? 'true' : 'false');
        bouton.setAttribute('aria-label', favori ? 'Retirer des favoris' : 'Ajouter aux favoris');
        bouton.dataset.favori = favori ? '1' : '0';
    }

    // Visiteur non connecté : le cœur mène à la connexion. Il est dans le
    // lien de la carte : sans preventDefault, le clic ouvrirait l'annonce.
    document.addEventListener('click', function (e) {
        var connexion = e.target.closest('[data-favori-connexion]');
        if (!connexion) return;

        e.preventDefault();
        e.stopPropagation();
        window.location.href = connexion.getAttribute('data-favori-connexion');
    });

    document.addEventListener('click', function (e) {
        var bouton = e.target.closest('[data-favori-url]');
        if (!bouton) return;

        e.preventDefault();
        e.stopPropagation();

        if (bouton.dataset.enCours === '1') return;
        bouton.dataset.enCours = '1';

        var avant = bouton.dataset.favori === '1';

        // On peint tout de suite : c'est ce qui donne la sensation d'instantané.
        peindre(bouton, !avant);
        bouton.classList.add('scale-110');
        setTimeout(function () { bouton.classList.remove('scale-110'); }, 180);

        function envoyer() {
            return fetch(bouton.dataset.favoriUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });
        }

        envoyer()
            .then(function (r) {
                // Jeton périmé (page ouverte depuis des jours) : le serveur en
                // renvoie un neuf. On le pose et on réessaie une seule fois.
                if (r.status !== 419) return r;

                return r.json().then(function (data) {
                    if (!data || !data.jeton) return r;
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.setAttribute('content', data.jeton);

                    return envoyer();
                });
            })
            .then(function (r) {
                if (!r.ok) throw new Error('refus');
                return r.json();
            })
            .then(function (data) {
                // On s'aligne sur la vérité du serveur.
                peindre(bouton, !!data.favorited);

                var compteur = document.querySelector(bouton.dataset.favoriCompteur || '\0');
                if (compteur && typeof data.count !== 'undefined') {
                    compteur.textContent = data.count;
                }
            })
            .catch(function () {
                // Échec : on remet l'état précédent plutôt que de mentir.
                peindre(bouton, avant);
            })
            .finally(function () {
                bouton.dataset.enCours = '0';
            });
    });
})();
