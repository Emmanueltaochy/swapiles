{{-- CHAMPS ADAPTÉS AU RAYON, EN DIRECT.
     Le formulaire était pensé pour les vêtements. Quand le vendeur change de
     rayon, on adapte sans recharger : « Taille » devient « Pointure »,
     « Dimensions », « Capacité » ou « Contenance », ou disparaît ; « Marque »
     devient « Auteur » pour un livre ; « Neuf avec étiquette » devient « Neuf,
     sous emballage » pour un objet ; « Location vêtement » n'est proposé que
     pour l'habillement. Les fiches viennent de App\Support\Categories::fiche,
     déjà utilisées pour l'affichage initial. --}}
<script>
(function () {
    var FICHES = @json(\App\Support\Categories::fichesPourJavascript());
    var ETATS = @json(\App\Support\Etat::choixFormulaire());

    var niveau1 = document.getElementById('category_level1');
    var niveau2 = document.getElementById('category_level2');
    if (!niveau1) return;

    function ficheCourante() {
        var n1 = (niveau1.value || '').toLowerCase();
        var n2 = niveau2 ? (niveau2.value || '').toLowerCase() : '';

        return FICHES[n1 + '/' + n2] || FICHES[n1] || FICHES[''];
    }

    function adapterChamp(nom, reglage) {
        var bloc = document.querySelector('[data-champ="' + nom + '"]');
        if (!bloc) return;

        var champ = bloc.querySelector('input, select, textarea');
        var etiquette = bloc.querySelector('[data-champ-label]');

        if (!reglage) {
            // Caché ET désactivé : un champ masqué n'est pas envoyé, il ne
            // laisse donc pas une « taille M » sur un frigo.
            bloc.hidden = true;
            if (champ) champ.disabled = true;
            return;
        }

        bloc.hidden = false;
        if (champ) {
            champ.disabled = false;
            champ.placeholder = reglage.exemple;
        }
        if (etiquette) etiquette.textContent = reglage.label;
    }

    function adapterEtat(type) {
        var liste = document.querySelector('[data-champ-etat]');
        if (!liste || !ETATS[type]) return;

        Array.prototype.forEach.call(liste.options, function (option) {
            if (option.value && ETATS[type][option.value]) {
                option.textContent = ETATS[type][option.value];
            }
        });
    }

    function adapterLocation(autorisee) {
        var option = document.querySelector('[data-option-location]');
        if (!option) return;

        option.hidden = !autorisee;
        option.disabled = !autorisee;

        // La location était choisie, puis le vendeur passe sur un frigo :
        // on revient sur « Vente » et on prévient le reste du formulaire.
        var liste = option.parentNode;
        if (!autorisee && liste && liste.value === option.value) {
            liste.value = 'achat';
            liste.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function appliquer() {
        var fiche = ficheCourante();

        adapterChamp('taille', fiche.taille);
        adapterChamp('marque', fiche.marque);
        adapterEtat(fiche.etat);
        adapterLocation(fiche.location);

        var titre = document.querySelector('[data-champ-titre]');
        if (titre) titre.placeholder = fiche.titre;
    }

    niveau1.addEventListener('change', function () {
        // Les sous-catégories sont reconstruites par le script du formulaire
        // sur ce même événement : on attend qu'il ait fini.
        setTimeout(appliquer, 0);
    });
    if (niveau2) niveau2.addEventListener('change', appliquer);

    appliquer();
})();
</script>
