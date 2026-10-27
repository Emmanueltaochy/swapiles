/**
 * Recherche par photo : le bouton 📷 de la barre de recherche ouvre l'appareil
 * photo (ou la galerie), envoie la photo, et l'IA la transforme en recherche
 * classique (mots-clés + catégorie). Bouton : [data-recherche-photo="adresse"].
 */
(function () {
    'use strict';

    var champ = null;
    var boutonActif = null;

    function fichierChoisi() {
        var photo = champ.files && champ.files[0];
        if (!photo || !boutonActif) return;

        var bouton = boutonActif;
        var texte = bouton.innerHTML;
        bouton.disabled = true;
        bouton.innerHTML = '⏳';
        bouton.setAttribute('aria-label', 'Je regarde ta photo…');

        var donnees = new FormData();
        donnees.append('photo', photo);
        var jeton = document.querySelector('meta[name="csrf-token"]');

        fetch(bouton.getAttribute('data-recherche-photo'), {
            method: 'POST',
            body: donnees,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': jeton ? jeton.getAttribute('content') : '', 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j.url) throw new Error(res.j.message || 'La recherche par photo n’a pas marché. Tape ta recherche.');
                if (window.Turbo && typeof window.Turbo.visit === 'function') {
                    window.Turbo.visit(res.j.url);
                } else {
                    window.location.href = res.j.url;
                }
            })
            .catch(function (e) { window.alert(e.message); })
            .finally(function () {
                bouton.disabled = false;
                bouton.innerHTML = texte;
                champ.value = '';
            });
    }

    document.addEventListener('click', function (e) {
        var bouton = e.target.closest && e.target.closest('[data-recherche-photo]');
        if (!bouton) return;
        e.preventDefault();

        if (!champ) {
            champ = document.createElement('input');
            champ.type = 'file';
            champ.accept = 'image/*';
            champ.hidden = true;
            champ.addEventListener('change', fichierChoisi);
            document.body.appendChild(champ);
        }
        // Turbo remplace le <body> d'une page à l'autre : on rattache le champ si besoin.
        if (!document.body.contains(champ)) document.body.appendChild(champ);

        boutonActif = bouton;
        champ.click();
    });
})();
