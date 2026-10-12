{{--
    Bandeau « Télécharger l'appli » — téléphones seulement, sur le site.

    Caché au départ ; public/js/app-banner.js l'affiche si :
      - on est sur un iPhone / iPad / Android (pas sur ordinateur) ;
      - on n'est PAS dans l'application elle-même ;
      - la personne ne l'a pas fermé ces 30 derniers jours.
    Un membre connecté qui utilise déjà l'appli ne le reçoit même pas.
    Le bouton mène à l'App Store sur iPhone, à Google Play sur Android.
--}}
@unless(auth()->check() && auth()->user()->utiliseLAppli())
    <div data-bandeau-appli hidden
         data-lien-ios="{{ config('deeplinks.stores.ios') }}"
         data-lien-android="{{ config('deeplinks.stores.android') }}"
         class="border-b border-teal-100 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5">
            <button type="button" data-bandeau-fermer aria-label="Fermer"
                    class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">×</button>
            <img src="{{ asset('images/app-icon.png') }}" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-xl shadow-sm">
            <div class="min-w-0 flex-1 leading-tight">
                <p class="truncate text-sm font-bold text-gray-900">Swap'Îles</p>
                <p class="truncate text-xs text-gray-500">Appli gratuite, plus rapide</p>
            </div>
            <a href="#" data-bandeau-lien rel="noopener"
               class="shrink-0 rounded-full bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">Installer</a>
        </div>
    </div>
@endunless
