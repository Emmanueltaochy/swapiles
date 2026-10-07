{{--
    Cœur « favori » d'une carte d'annonce.

    Un seul composant pour toutes les pages : accueil, recherche, favoris.
    Le clic ne recharge pas la page (voir public/js/favorite.js) et l'état
    de départ vient d'une liste chargée une seule fois par page, pas d'une
    requête par vignette.

    Paramètre : $listing
--}}
@auth
    @php $dejaFavori = auth()->user()->aEnFavori($listing->id); @endphp
    <button type="button"
            data-favori-url="{{ route('account.favorites.toggle', $listing) }}"
            data-favori="{{ $dejaFavori ? '1' : '0' }}"
            aria-pressed="{{ $dejaFavori ? 'true' : 'false' }}"
            aria-label="{{ $dejaFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"
            class="absolute right-2 top-2 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-lg shadow transition">
        {{ $dejaFavori ? '❤️' : '🤍' }}
    </button>
@else
    {{-- Un BOUTON, jamais un lien : ce cœur est placé dans le lien de la
         carte, et un lien dans un lien est interdit en HTML. Le navigateur
         coupait alors la carte en deux — la photo dans une case de la grille,
         le texte dans la suivante — pour tous les visiteurs non connectés.
         L'envoi vers la connexion est fait par public/js/favorite.js. --}}
    <button type="button"
            data-favori-connexion="{{ route('login') }}"
            aria-label="Se connecter pour ajouter aux favoris"
            class="absolute right-2 top-2 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-lg text-gray-500 shadow transition">♡</button>
@endauth
