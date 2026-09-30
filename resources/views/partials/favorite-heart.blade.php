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
    <a href="{{ route('login') }}"
       aria-label="Se connecter pour ajouter aux favoris"
       onclick="event.stopPropagation();"
       class="absolute right-2 top-2 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-lg text-gray-500 shadow transition">♡</a>
@endauth
