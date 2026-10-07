/**
 * Suivre un dressing : réaction immédiate, sans rechargement.
 *
 * Même principe que les favoris : le bouton change tout de suite, l'appel
 * part en arrière-plan, et si le serveur refuse on remet l'état précédent.
 * Le nombre d'abonnés affiché dans la page ([data-abonnes-de]) suit.
 */
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function peindre(bouton, suivi) {
        bouton.dataset.suivi = suivi ? '1' : '0';
        bouton.setAttribute('aria-pressed', suivi ? 'true' : 'false');
        var libelle = bouton.querySelector('[data-suivre-libelle]');
        if (libelle) libelle.textContent = suivi ? '✓ Abonné' : '➕ Suivre';
    }

    function compter(vendeur, nombre) {
        document.querySelectorAll('[data-abonnes-de="' + vendeur + '"]').forEach(function (el) {
            el.textContent = nombre + ' abonné' + (nombre > 1 ? 's' : '');
        });
    }

    function annoncer(texte) {
        var bulle = document.createElement('div');
        bulle.setAttribute('role', 'status');
        bulle.textContent = texte;
        bulle.style.cssText = 'position:fixed;left:50%;bottom:96px;transform:translateX(-50%);z-index:10001;'
            + 'max-width:min(92vw,420px);background:#111827;color:#fff;padding:12px 16px;border-radius:14px;'
            + 'font-size:14px;line-height:1.35;box-shadow:0 10px 30px rgba(0,0,0,.25);text-align:center';
        document.body.appendChild(bulle);
        setTimeout(function () { bulle.remove(); }, 3500);
    }

    document.addEventListener('click', function (e) {
        // Visiteur : le bouton mène à la connexion. Il peut se trouver dans un
        // lien (bloc vendeur) : sans preventDefault, le clic ouvrirait le profil.
        var connexion = e.target.closest('[data-suivre-connexion]');
        if (connexion) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = connexion.getAttribute('data-suivre-connexion');
            return;
        }

        var bouton = e.target.closest('[data-suivre-url]');
        if (!bouton) return;

        e.preventDefault();
        e.stopPropagation();

        if (bouton.dataset.enCours === '1') return;
        bouton.dataset.enCours = '1';

        var avant = bouton.dataset.suivi === '1';
        peindre(bouton, !avant);

        function envoyer() {
            return fetch(bouton.dataset.suivreUrl, {
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
                // Jeton périmé (page ouverte depuis longtemps) : on reprend le
                // jeton neuf renvoyé par le serveur et on réessaie une fois.
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
                var suivi = !!data.following;
                peindre(bouton, suivi);
                if (typeof data.count !== 'undefined') compter(bouton.dataset.suivreVendeur, data.count);

                if (suivi) {
                    annoncer('Vous suivez ' + (bouton.dataset.suivreNom || 'ce dressing')
                        + ' : vous serez prévenu de chaque nouvel article.');
                }
            })
            .catch(function () {
                peindre(bouton, avant);
            })
            .finally(function () {
                bouton.dataset.enCours = '0';
            });
    });
})();
