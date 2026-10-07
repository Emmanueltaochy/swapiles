<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="google-site-verification" content="jl2dzZ3jQ5JfJg-QrS6qftgcitH7oS6oVXopqLDSW4U">
    <title>@yield('title', "Swap'Îles")</title>

    {{-- Navigation sans rechargement (Turbo, voir resources/js/app.js).
         « no-preview » : au retour arrière la page revient instantanément,
         mais un onglet déjà visité n'affiche jamais une version périmée avant
         la vraie.
         Pas de fondu pour les pages ouvertes par Turbo : mesuré, Turbo attend
         la fin de l'animation avant de rendre la page prête (535 ms au lieu de
         246). Le contenu étant remplacé d'un seul coup, sans écran blanc, le
         fondu n'apportait rien. Il reste actif pour les rechargements
         complets (règle @view-transition plus bas). --}}
    <meta name="turbo-cache-control" content="no-preview">

    {{-- SOCLE — doit passer avant tout autre script.
         Avec Turbo, la page n'est plus rechargée : seul son contenu est
         remplacé, et ses scripts sont réexécutés. Deux conséquences que ce
         socle règle pour tous les scripts du site, sans les réécrire :

         1. « DOMContentLoaded » ne se produit qu'une fois, au tout premier
            chargement. Un script qui attendait cet événement pour démarrer
            ne démarrerait plus jamais. On l'exécute donc aussitôt quand la
            page est déjà prête — exactement ce que faisait jQuery.

         2. Un écouteur posé sur document ou window survit au changement de
            page. Réexécuté à chaque visite, il s'empilerait (un clic traité
            dix fois après dix pages). Les scripts de page passent donc
            swpPage() en signal : leurs écouteurs sont retirés dès que la page
            suivante s'affiche. --}}
    <script>
        (function () {
            var controleur = new AbortController();

            window.swpPage = function () {
                return controleur.signal;
            };

            document.addEventListener('turbo:before-render', function () {
                controleur.abort();
                controleur = new AbortController();
            });

            function differer(cible, type, ecouteur) {
                setTimeout(function () {
                    var evenement = new Event(type);
                    if (typeof ecouteur === 'function') {
                        ecouteur.call(cible, evenement);
                    } else if (ecouteur && typeof ecouteur.handleEvent === 'function') {
                        ecouteur.handleEvent(evenement);
                    }
                }, 0);
            }

            var ajouterDocument = document.addEventListener;
            document.addEventListener = function (type, ecouteur, options) {
                if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                    return differer(document, type, ecouteur);
                }
                return ajouterDocument.call(document, type, ecouteur, options);
            };

            var ajouterFenetre = window.addEventListener;
            window.addEventListener = function (type, ecouteur, options) {
                if (type === 'load' && document.readyState === 'complete') {
                    return differer(window, type, ecouteur);
                }
                return ajouterFenetre.call(window, type, ecouteur, options);
            };
        })();
    </script>

    @vite(['resources/css/app.css','resources/js/app.js'])

    {{-- Scripts communs : chargés UNE fois, ils restent en mémoire d'une page
         à l'autre. Le « ?v= » change à chaque modification du fichier : Turbo
         voit alors la différence et recharge complètement la page, pour que
         personne ne garde l'ancienne version. --}}
    @foreach(['push', 'report', 'share', 'password-eye', 'form-draft', 'favorite', 'follow'] as $scriptCommun)
        <script defer data-turbo-track="reload"
                src="{{ asset('js/' . $scriptCommun . '.js') }}?v={{ @filemtime(public_path('js/' . $scriptCommun . '.js')) }}"></script>
    @endforeach

<style id="swapiles-mobile-fix">
/* « clip » et non « hidden » : hidden transforme le corps de page en conteneur
   de defilement, ce qui CASSE position: sticky. L'entete etait donc collante
   dans le code mais jamais a l'ecran. clip empeche le debordement horizontal
   sans creer de conteneur de defilement. */
html, body {
    max-width: 100%;
    overflow-x: clip;
}
@supports not (overflow: clip) {
    html, body {
        overflow-x: hidden;
    }
}
@media (max-width: 1023px) {
    header .max-w-7xl {
        max-width: 100%;
    }
    header img {
        max-width: 150px;
    }
    header form {
        min-width: 0;
    }
    main {
        max-width: 100vw;
        overflow-x: clip;
    }
}
/* Zones de sécurité (encoches / barres système) : en mode edge-to-edge sur
   Android 15 et iPhone, le contenu passe sous les barres. On décale l'entête
   sous la barre d'état et on remonte le menu du bas au-dessus des 3 boutons
   de navigation / de l'indicateur d'accueil. Sans effet sur le web (insets = 0). */
.swp-safe-top {
    padding-top: env(safe-area-inset-top);
}
.swp-safe-bottom {
    padding-bottom: env(safe-area-inset-bottom);
}

/* PASSAGE D'UNE PAGE A L'AUTRE SANS ECRAN BLANC.
   Dans l'application, chaque changement d'onglet effacait l'ecran puis
   redessinait tout : c'est ce « flash » qui trahissait le site derriere
   l'appli. Le navigateur garde maintenant l'ancienne page affichee jusqu'a ce
   que la nouvelle soit prete, puis fond l'une dans l'autre. L'entete et la
   barre du bas ne bougent pas du tout, comme dans une appli native.
   Android recent et iOS 18.2+ ; ailleurs, la regle est simplement ignoree. */
@view-transition {
    navigation: auto;
}
[data-entete] {
    view-transition-name: swp-entete;
}
[data-barre-bas] {
    view-transition-name: swp-barre-bas;
}
::view-transition-old(root),
::view-transition-new(root) {
    animation-duration: 140ms;
}
@media (prefers-reduced-motion: reduce) {
    ::view-transition-group(*),
    ::view-transition-old(*),
    ::view-transition-new(*) {
        animation: none !important;
    }
}

/* Barre de chargement : le toucher est acquitte A L'INSTANT, meme si le
   serveur met une demi-seconde a repondre. Sans elle, l'ecran restait fige et
   on ne savait pas si le doigt avait ete pris en compte. */
#swp-chargement {
    position: fixed;
    top: env(safe-area-inset-top);
    left: 0;
    height: 3px;
    width: 0;
    z-index: 10000;
    background: #0d9488;
    opacity: 0;
    pointer-events: none;
    transition: width 1.8s cubic-bezier(.1, .7, .3, 1), opacity .2s;
}
#swp-chargement.actif {
    width: 85%;
    opacity: 1;
}

/* Barre qui reste collee JUSTE SOUS l'entete. La hauteur de l'entete varie
   (encoche, logo, largeur d'ecran), donc on ne peut pas l'ecrire en dur : un
   petit script la mesure et la publie dans --swp-entete. La valeur de repli
   sert le temps que le script tourne, et si le JavaScript est coupe. */
.swp-sous-entete {
    position: sticky;
    top: var(--swp-entete, 64px);
    z-index: 40;
}

/* Rangees qui defilent horizontalement (pastilles de categories, carrousels) :
   on masque la barre de defilement, qui sur ordinateur ajoute une bande grise
   sous chaque rangee. La regle vivait seulement dans l'accueil ; les autres
   pages utilisaient la classe sans effet. */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
    <meta name="description" content="@yield('meta_description', 'Swap’Îles, la marketplace seconde main des îles : achetez, vendez, échangez et donnez près de chez vous à La Réunion, en Martinique, Guadeloupe, Guyane et Mayotte.')">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:title" content="@yield('title', 'Swap’Îles')">
    <meta property="og:description" content="@yield('meta_description', 'La marketplace seconde main des îles.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/logo.png'))">
    <meta property="og:image:secure_url" content="@yield('og_image', asset('images/logo.png'))">
    <meta property="og:image:alt" content="@yield('title', 'Swap’Îles')">
    <meta property="og:site_name" content="Swap'Îles">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Swap’Îles')">
    <meta name="twitter:description" content="@yield('meta_description', 'La marketplace seconde main des îles.')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/logo.png'))">

    {{-- Données structurées site-wide : entité de marque + boîte de recherche sitelinks (Schema.org) --}}
    <script type="application/ld+json">
    @php
        echo json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/') . '/#organization',
                    'name' => "Swap'Îles",
                    'url' => url('/'),
                    'logo' => asset('images/logo.png'),
                    'description' => "Marketplace de seconde main dédiée aux îles françaises (La Réunion, Martinique, Guadeloupe, Guyane, Mayotte).",
                    'areaServed' => ['La Réunion', 'Martinique', 'Guadeloupe', 'Guyane', 'Mayotte'],
                    'email' => 'contact@swapiles.com',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/') . '/#website',
                    'name' => "Swap'Îles",
                    'url' => url('/'),
                    'inLanguage' => 'fr-FR',
                    'publisher' => ['@id' => url('/') . '/#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => route('search') . '?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    </script>
    @stack('structured_data')
    @stack('head')

    {{-- Suivi publicitaire, conditionné au consentement cookies (RGPD) --}}
    @php
        $metaPixelId = env('META_PIXEL_ID', '2716674522082712');
        $googleTagId = env('GOOGLE_TAG_ID', 'G-KH96S3FP4X');
        $pixelEvent = session('pixel_event');
        $gaEvent = session('ga_event');
    @endphp
    <script>
    // Avec Turbo, ce script n'est réexécuté que si son contenu change (un
    // événement en attente, par exemple). Les balises Google et Meta sont
    // alors DÉJÀ chargées : on ne les recharge pas — ce qui compterait la
    // page deux fois —, on rejoue seulement l'événement en attente.
    var swpDejaCharge = !!(window.SWP && window.SWP.loaded);
    window.SWP = {
        metaId: @json($metaPixelId),
        gaId: @json($googleTagId),
        pending: @json($pixelEvent),
        pendingGa: @json($gaEvent),
        loaded: false,
        queue: [],
        load: function () {
            if (this.loaded) return; this.loaded = true;
            if (this.metaId) {
                !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', this.metaId);
                fbq('track', 'PageView');
            }
            if (this.gaId) {
                var g = document.createElement('script'); g.async = true;
                g.src = 'https://www.googletagmanager.com/gtag/js?id=' + this.gaId;
                document.head.appendChild(g);
                window.dataLayer = window.dataLayer || [];
                window.gtag = function () { dataLayer.push(arguments); };
                gtag('js', new Date());
                // page_view (session_start) explicite AVANT tout autre événement.
                gtag('config', this.gaId);
            }
            if (this.pending) { this.track(this.pending.event, this.pending.params || {}); }
            if (this.pendingGa) { this.ga4(this.pendingGa.event, this.pendingGa.params || {}); }
            // Rejoue les événements GA4 mis en file avant le consentement.
            var q = this.queue; this.queue = [];
            for (var i = 0; i < q.length; i++) { this.ga4(q[i][0], q[i][1]); }
        },
        track: function (event, params) {
            if (window.fbq) { fbq('track', event, params || {}); }
            if (window.gtag) { gtag('event', event, params || {}); }
        },
        // Événement GA4 uniquement (nommage e-commerce GA4). Mis en file si la
        // balise n'est pas encore chargée (consentement pas encore donné).
        ga4: function (event, params) {
            if (window.gtag) { gtag('event', event, params || {}); }
            else if (!this.loaded) { this.queue.push([event, params || {}]); }
        },
        meta: function (event, params) {
            if (window.fbq) { fbq('track', event, params || {}); }
        }
    };
    (function () {
        // DANS L'APPLICATION MOBILE : aucun traceur publicitaire.
        //
        // Le pixel Meta relie les données à des fins publicitaires : sur iOS,
        // Apple exige alors le consentement via son propre écran système
        // (App Tracking Transparency), et INTERDIT de le demander avec une
        // fenêtre maison — notre bandeau cookies en était une. C'est le motif
        // du refus 5.1.2(i).
        //
        // On ne suit donc personne depuis l'application : pas de pixel, pas de
        // mesure d'audience, et pas de bandeau. Le site web, lui, ne change pas.
        var cap = window.Capacitor;
        if (cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform()) {
            window.SWP.metaId = null;
            window.SWP.gaId = null;
            window.SWP.load = function () {};
            window.SWP.track = function () {};
            window.SWP.ga4 = function () {};
            window.SWP.meta = function () {};
            document.documentElement.setAttribute('data-sans-traceurs', '1');

            return;
        }

        if (swpDejaCharge) {
            window.SWP.loaded = true;
            if (window.SWP.pending) { window.SWP.track(window.SWP.pending.event, window.SWP.pending.params || {}); }
            if (window.SWP.pendingGa) { window.SWP.ga4(window.SWP.pendingGa.event, window.SWP.pendingGa.params || {}); }

            return;
        }

        var m = document.cookie.match(/(?:^|; )swapiles_cookie_consent=([^;]+)/);
        if (m && decodeURIComponent(m[1]) === 'accepted') { window.SWP.load(); }
    })();
    </script>

<!-- SWAPILES_COLISSIMO_BANNER_FIX_START -->
<style>
    img[src*="colissimo" i],
    img[alt*="colissimo" i] {
        width: 100% !important;
        height: auto !important;
        max-height: 120px !important;
        object-fit: contain !important;
        display: block !important;
    }

    .swapiles-colissimo-banner-fixed {
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        padding-top: 12px !important;
        padding-bottom: 12px !important;
        display: block !important;
    }
</style>

<script>
// « turbo:load » : au premier chargement ET à chaque page ouverte par Turbo.
// Ce script de l'en-tête ne s'exécute qu'une fois ; c'est l'écouteur qui
// refait le travail sur chaque nouvelle page.
document.addEventListener('turbo:load', function () {
    const imgs = Array.from(document.querySelectorAll('img')).filter(function (img) {
        const src = (img.getAttribute('src') || '').toLowerCase();
        const alt = (img.getAttribute('alt') || '').toLowerCase();
        return src.includes('colissimo') || alt.includes('colissimo');
    });

    imgs.forEach(function (img) {
        img.style.width = '100%';
        img.style.height = 'auto';
        img.style.maxHeight = '120px';
        img.style.objectFit = 'contain';
        img.style.display = 'block';

        let parent = img.parentElement;
        let limit = 0;

        while (parent && parent !== document.body && limit < 5) {
            const rect = parent.getBoundingClientRect();

            if (rect.height > 220) {
                parent.classList.add('swapiles-colissimo-banner-fixed');
                parent.style.height = 'auto';
                parent.style.minHeight = '0';
                parent.style.paddingTop = '12px';
                parent.style.paddingBottom = '12px';
            }

            parent = parent.parentElement;
            limit++;
        }
    });
});
</script>
<!-- SWAPILES_COLISSIMO_BANNER_FIX_END -->

</head>


@php
    $favoriteAlertCount = auth()->check()
        ? \App\Models\FavoriteAlert::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;

    $unreadMessagesCount = auth()->check()
        ? \App\Models\Message::where('receiver_id', auth()->id())->whereNull('read_at')->count()
        : 0;

    $unreadNotificationsCount = auth()->check()
        ? \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;
@endphp


<body class="bg-gray-50 text-gray-900 antialiased overflow-x-hidden">
<div id="swp-chargement" aria-hidden="true"></div>
    <header data-entete class="swp-safe-top sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="shrink-0 flex items-center">
                <img src="{{ asset('images/logo.png') }}" alt="Swap'Îles" class="h-9 sm:h-10 w-auto">
            </a>

            <form method="GET" action="{{ route('search') }}" data-turbo="true" class="flex-1 relative" id="header-search-form">
                <input
                    type="search"
                    name="q"
                    id="header-live-search"
                    autocomplete="off"
                    placeholder="Rechercher..."
                    class="w-full rounded-full bg-gray-100 border-0 px-4 py-3 text-sm focus:ring-2 focus:ring-teal-600"
                >

                <div id="header-search-results"
                     class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-3xl border border-gray-100 shadow-2xl overflow-hidden z-[9999] max-h-[420px] overflow-y-auto">
                </div>
            </form>

            <a href="/favoris" class="mobile-favorite-heart lg:hidden relative shrink-0 w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center text-xl">
                🤍
                @if(($favoriteAlertCount ?? 0) > 0)
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-extrabold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                        {{ $favoriteAlertCount }}
                    </span>
                @endif
            </a>

            {{-- Menu mobile. La barre du bas couvre deja Accueil, Produits,
                 Deposer, Messages et Compte : ce menu porte le reste, qui
                 n'etait accessible nulle part sur telephone. --}}
            <button type="button"
                    data-menu-ouvrir
                    aria-label="Ouvrir le menu"
                    aria-expanded="false"
                    aria-controls="menu-mobile"
                    class="lg:hidden relative shrink-0 w-11 h-11 rounded-full bg-gray-100 flex flex-col items-center justify-center gap-[5px]">
                <span class="block h-0.5 w-5 rounded-full bg-gray-700"></span>
                <span class="block h-0.5 w-5 rounded-full bg-gray-700"></span>
                <span class="block h-0.5 w-5 rounded-full bg-gray-700"></span>
                @auth
                    @if(($unreadNotificationsCount ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-red-600 ring-2 ring-white"></span>
                    @endif
                @endauth
            </button>

            <nav class="hidden lg:flex items-center gap-4 text-sm font-bold">
                <a href="{{ route('dressings.top') }}" class="hover:text-teal-700 {{ request()->routeIs('dressings.top') ? 'text-teal-700' : 'text-gray-700' }}" title="Meilleurs dressings">
                    🏆 Classement
                </a>
                @auth
                    <a href="{{ route('account.notifications.index') }}" class="relative text-xl hover:text-teal-700" aria-label="Notifications" title="Notifications">
                        🔔
                        @if($unreadNotificationsCount > 0)
                            <span class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('account.messages.index') }}" class="relative text-xl hover:text-teal-700" aria-label="Messages" title="Messages">
                        💬
                        @if($unreadMessagesCount > 0)
                            <span class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount }}</span>
                        @endif
                    </a>

                    <a href="/favoris" class="relative text-xl hover:text-teal-700" aria-label="Favoris" title="Favoris">
                        🤍
                        @if(($favoriteAlertCount ?? 0) > 0)
                            <span class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $favoriteAlertCount }}</span>
                        @endif
                    </a>

                    <details class="account-menu relative">
                        <summary class="flex cursor-pointer list-none items-center gap-1 hover:text-teal-700">Mon compte <span class="text-xs">▾</span></summary>
                        <div class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-lg">
                            <a href="{{ route('account.dashboard') }}" class="block px-4 py-2 hover:bg-gray-50">Tableau de bord</a>
                            <a href="{{ route('account.transactions.index') }}" class="block px-4 py-2 hover:bg-gray-50">Transactions</a>
                            <a href="/mon-wallet" class="block px-4 py-2 hover:bg-gray-50">Wallet</a>
                            @if(auth()->user()->managesAnyRelay())
                                <a href="{{ route('account.relay.dashboard') }}" class="block px-4 py-2 hover:bg-gray-50">🏪 Mon espace relais</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">
                                @csrf
                                <button class="block w-full px-4 py-2 text-left text-red-600 hover:bg-gray-50">Déconnexion</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('register') }}" class="hover:text-teal-700">S'inscrire</a>
                    <a href="{{ route('login') }}" class="hover:text-teal-700">Se connecter</a>
                @endauth

                <a href="{{ route('search') }}" class="rounded-full border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50">
                    Tous les produits
                </a>

                <a href="/deposer-une-annonce" class="rounded-full bg-teal-700 px-4 py-2 text-white hover:bg-teal-800">
                    Déposer une annonce
                </a>
            </nav>
        </div>
    </div>
</header>

{{-- Panneau de navigation mobile --}}
<div id="menu-mobile" data-menu-panneau hidden class="lg:hidden fixed inset-0 z-[9998]">
    <div data-menu-fond class="absolute inset-0 bg-black/50"></div>

    <div class="swp-safe-top absolute right-0 top-0 h-full w-[85%] max-w-sm overflow-y-auto bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <span class="text-base font-bold text-gray-900">Menu</span>
            <button type="button" data-menu-fermer aria-label="Fermer le menu"
                    class="grid h-9 w-9 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">✕</button>
        </div>

        @auth
            <a href="{{ route('account.dashboard') }}" class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-50 text-xl">👤</span>
                <span class="min-w-0">
                    <span class="block truncate font-bold text-gray-900">{{ auth()->user()->name }}</span>
                    <span class="block text-sm text-gray-500">Voir mon compte</span>
                </span>
            </a>
        @else
            <div class="flex gap-2 border-b border-gray-100 px-5 py-4">
                <a href="{{ route('login') }}" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-gray-700">Se connecter</a>
                <a href="{{ route('register') }}" class="flex-1 rounded-xl bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white">S'inscrire</a>
            </div>
        @endauth

        @php
            $menuLien = 'flex items-center justify-between gap-3 px-5 py-3.5 text-[15px] text-gray-800 active:bg-gray-50';
            $menuTitre = 'px-5 pt-5 pb-1 text-xs font-bold uppercase tracking-wide text-gray-400';
        @endphp

        {{-- Parcourir : chaque categorie deplie ses sous-categories.
             Elles n'existaient que dans le formulaire de depot et sur la page
             de recherche une fois une categorie choisie : depuis le menu, on
             ne pouvait viser qu'une categorie entiere. « details » suffit, pas
             de JavaScript, et le clavier fonctionne tout seul.
             « Accessoires » quitte cette liste : ce n'est pas une categorie
             mais une sous-categorie, presente sous chacune des trois. On la
             trouve maintenant a sa vraie place, dans chaque sous-menu. --}}
        <p class="{{ $menuTitre }}">Parcourir</p>
        <a href="{{ route('search') }}" class="{{ $menuLien }}"><span>🔍 Tous les produits</span></a>

        @foreach(\App\Support\Categories::ARBRE as $cleCategorie => $categorie)
            <details class="group border-b border-gray-50 last:border-b-0">
                <summary class="{{ $menuLien }} cursor-pointer list-none marker:content-['']">
                    <span>{{ $categorie['emoji'] }} {{ $categorie['label'] }}</span>
                    <span class="text-gray-300 transition-transform group-open:rotate-90" aria-hidden="true">›</span>
                </summary>

                <div class="bg-gray-50/70 pb-2">
                    <a href="{{ route('search', ['category' => $cleCategorie]) }}"
                       class="block px-5 py-2.5 pl-12 text-[15px] font-semibold text-teal-700 active:bg-gray-100">
                        Tout {{ $categorie['label'] }}
                    </a>

                    @foreach($categorie['enfants'] as $cleSous => $sousCategorie)
                        <a href="{{ route('search', ['category' => $cleCategorie, 'category_level2' => $cleSous]) }}"
                           class="block px-5 py-2.5 pl-12 text-[15px] text-gray-700 active:bg-gray-100">
                            {{ $sousCategorie['label'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach

        @auth
            <p class="{{ $menuTitre }}">Mon compte</p>
            <a href="{{ route('account.notifications.index') }}" class="{{ $menuLien }}">
                <span>🔔 Notifications</span>
                @if(($unreadNotificationsCount ?? 0) > 0)
                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                @endif
            </a>
            <a href="/favoris" class="{{ $menuLien }}">
                <span>🤍 Mes favoris</span>
                @if(($favoriteAlertCount ?? 0) > 0)
                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ $favoriteAlertCount }}</span>
                @endif
            </a>
            <a href="{{ route('account.transactions.index') }}" class="{{ $menuLien }}"><span>📦 Mes transactions</span></a>
            <a href="/mon-wallet" class="{{ $menuLien }}"><span>💶 Mon wallet</span></a>
            <a href="{{ route('account.notifications.preferences') }}" class="{{ $menuLien }}"><span>🔇 Préférences de notification</span></a>
            <a href="{{ route('account.settings') }}" class="{{ $menuLien }}"><span>⚙️ Réglages</span></a>
            @if(auth()->user()->managesAnyRelay())
                <a href="{{ route('account.relay.dashboard') }}" class="{{ $menuLien }}"><span>🏪 Mon espace relais</span></a>
            @endif
        @endauth

        <p class="{{ $menuTitre }}">Swap'Îles</p>
        <a href="{{ route('dressings.top') }}" class="{{ $menuLien }}"><span>🏆 Classement des dressings</span></a>
        <a href="{{ route('home') }}#comment-ca-marche" class="{{ $menuLien }}"><span>❓ Comment ça marche</span></a>
        <a href="{{ route('relay.partner') }}" class="{{ $menuLien }}"><span>🏪 Devenir point relais</span></a>
        {{-- Conditions generales et Confidentialite ne sont plus dans le menu :
             il servait a naviguer, pas a lire des pages juridiques. Les deux
             pages restent liees depuis le pied de page, ce qu'Apple et Google
             exigent. --}}

        @auth
            <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-gray-100">
                @csrf
                <button class="w-full px-5 py-4 text-left text-[15px] font-semibold text-red-600 active:bg-gray-50">Se déconnecter</button>
            </form>
        @endauth

        <div class="h-24"></div>
    </div>
</div>

    <script>
        // Hauteur reelle de l'entete, publiee dans --swp-entete : les barres
        // « swp-sous-entete » viennent se coller juste en dessous au lieu de
        // glisser dessous et de disparaitre. On remesure au redimensionnement
        // et a la rotation, ou l'entete change de hauteur.
        (function () {
            var entete = document.querySelector('[data-entete]');
            if (!entete) return;

            function mesurer() {
                document.documentElement.style.setProperty(
                    '--swp-entete', Math.round(entete.getBoundingClientRect().height) + 'px'
                );
            }

            mesurer();
            // swpPage() : retirés au changement de page (voir le socle).
            window.addEventListener('resize', mesurer, { signal: window.swpPage() });
            window.addEventListener('orientationchange', mesurer, { signal: window.swpPage() });

            // Les polices web changent la hauteur une fois chargees.
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(mesurer);
            }
        })();

        // Menu mobile : ouverture, fermeture, et verrouillage du defilement
        // derriere le panneau.
        (function () {
            var panneau = document.getElementById('menu-mobile');
            if (!panneau) return;

            var bouton = document.querySelector('[data-menu-ouvrir]');

            function ouvrir() {
                panneau.hidden = false;
                document.body.style.overflow = 'hidden';
                if (bouton) bouton.setAttribute('aria-expanded', 'true');
            }

            function fermer() {
                panneau.hidden = true;
                document.body.style.overflow = '';
                if (bouton) bouton.setAttribute('aria-expanded', 'false');
            }

            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-menu-ouvrir]')) { e.preventDefault(); ouvrir(); return; }
                if (e.target.closest('[data-menu-fermer]') || e.target.matches('[data-menu-fond]')) { fermer(); }
            }, { signal: window.swpPage() });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !panneau.hidden) fermer();
            }, { signal: window.swpPage() });
        })();

        // Ferme le menu "Mon compte" du header quand on clique en dehors
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.account-menu[open]').forEach(function (d) {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        }, { signal: window.swpPage() });
    </script>

    {{-- Message ponctuel valable sur toutes les pages (jeton périmé…). --}}
    @if(session('swp_info'))
        <div role="status" class="mx-auto mt-3 max-w-3xl px-4">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                {{ session('swp_info') }}
            </div>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <nav data-barre-bas class="swp-safe-bottom lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-t border-gray-200">
        <div class="grid grid-cols-5 h-[74px] text-[11px] font-bold">
            <a href="{{ route('home') }}" data-onglet class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs('home') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">🏠</span><span>Accueil</span>
            </a>

            <a href="{{ route('search') }}" data-onglet class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs('search') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">🔎</span><span>Produits</span>
            </a>

            <a href="/deposer-une-annonce" class="flex flex-col items-center justify-center -mt-6">
                <span class="w-16 h-16 rounded-full bg-teal-700 text-white flex items-center justify-center shadow-xl border-4 border-white text-3xl">+</span>
                <span class="text-teal-800 mt-1">Déposer</span>
            </a>

            <a href="{{ route('account.messages.index') }}" data-onglet class="relative flex flex-col items-center justify-center gap-1 {{ request()->routeIs('account.messages.*') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">💬</span><span>Messages</span>
                @if($unreadMessagesCount > 0)
                    <span class="absolute top-2 right-5 bg-red-600 text-white text-[10px] font-extrabold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                        {{ $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('account.dashboard') }}" data-onglet class="relative flex flex-col items-center justify-center gap-1 {{ request()->routeIs('account.*') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">👤</span><span>Compte</span>
                @if($unreadNotificationsCount > 0)
                    <span class="absolute top-2 right-5 bg-red-600 text-white text-[10px] font-extrabold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                        {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                    </span>
                @endif
            </a>
        </div>
    </nav>

    <footer class="bg-white border-t border-gray-100 mt-16 pb-24 lg:pb-0">
        <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 md:grid-cols-4 gap-8 text-sm">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center">
                    <img src="{{ asset('images/logo.png') }}" alt="Swap'Îles" class="h-10 w-auto">
                </a>
                <p class="text-gray-500 mt-3">La marketplace seconde main pensée pour les territoires ultramarins.</p>
            </div>

            <div>
                <p class="font-extrabold mb-3">Plateforme</p>
                <a href="{{ route('home') }}#comment-ca-marche" class="block text-gray-500 hover:text-teal-700">Comment ça marche</a>
                <a href="{{ route('search') }}" class="block text-gray-500 hover:text-teal-700">Catégories</a>
                <a href="{{ route('faq') }}" class="block text-gray-500 hover:text-teal-700">FAQ</a>
                <a href="{{ route('dressings.top') }}" class="block text-gray-500 hover:text-teal-700">🏆 Meilleurs dressings</a>
                <a href="{{ route('relay.partner') }}" class="block text-gray-500 hover:text-teal-700">🏪 Devenir point relais</a>
            </div>

            <div>
                <p class="font-extrabold mb-3">Territoires</p>
                <a href="{{ route('catalog.territoire', 'la-reunion') }}" class="block text-gray-500 hover:text-teal-700">🇷🇪 La Réunion</a>
                <a href="{{ route('catalog.territoire', 'guyane') }}" class="block text-gray-500 hover:text-teal-700">🇬🇫 Guyane</a>
                <a href="{{ route('catalog.territoire', 'martinique') }}" class="block text-gray-500 hover:text-teal-700">🇲🇶 Martinique</a>
                <a href="{{ route('catalog.territoire', 'guadeloupe') }}" class="block text-gray-500 hover:text-teal-700">🇬🇵 Guadeloupe</a>
                <a href="{{ route('catalog.territoire', 'mayotte') }}" class="block text-gray-500 hover:text-teal-700">🇾🇹 Mayotte</a>
            </div>

            <div>
                <p class="font-extrabold mb-3">Légal</p>
                <a href="{{ route('legal.cgu') }}" class="block text-gray-500 hover:text-teal-700">CGU</a>
                <a href="{{ route('legal.cgv') }}" class="block text-gray-500 hover:text-teal-700">CGV</a>
                <a href="{{ route('legal.privacy') }}" class="block text-gray-500 hover:text-teal-700">Confidentialité</a>
                <a href="{{ route('legal.mentions') }}" class="block text-gray-500 hover:text-teal-700">Mentions légales</a>
                <a href="{{ route('account.deletion.info') }}" class="block text-gray-500 hover:text-teal-700">Supprimer mon compte</a>
                <a href="mailto:contact@swapiles.com" class="block text-gray-500 hover:text-teal-700">Contact</a>
            </div>
        </div>
    </footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('header-live-search');
    const results = document.getElementById('header-search-results');

    if (!input || !results) return;

    let timer = null;

    input.addEventListener('input', function () {
        const q = input.value.trim();

        clearTimeout(timer);

        if (q.length < 2) {
            results.classList.add('hidden');
            results.innerHTML = '';
            return;
        }

        timer = setTimeout(async function () {
            try {
                const res = await fetch(`/recherche/live?q=${encodeURIComponent(q)}`);
                const html = await res.text();

                results.innerHTML = html;
                results.classList.remove('hidden');
            } catch (e) {
                results.classList.add('hidden');
            }
        }, 200);
    });

    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== input) {
            results.classList.add('hidden');
        }
    }, { signal: window.swpPage() });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const priceInput =
        document.querySelector('input[name="price"]') ||
        document.querySelector('input[name="prix"]');

    if (!priceInput) return;

    const bodyText = document.body.innerText || '';
    const isListingForm =
        bodyText.includes('Colissimo') &&
        (
            bodyText.includes('Déposer')
            || bodyText.includes('Modifier')
            || bodyText.includes('annonce')
        );

    if (!isListingForm) return;

    const colissimoTextElement = [...document.querySelectorAll('label, div, p, span')]
        .find(el => (el.textContent || '').includes('Colissimo'));

    if (!colissimoTextElement) return;

    let warning = document.getElementById('seller-low-price-colissimo-warning');

    if (!warning) {
        warning = document.createElement('div');
        warning.id = 'seller-low-price-colissimo-warning';
        warning.className = 'hidden mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900';
        warning.innerHTML = `
            <p class="font-extrabold">💡 Conseil pour les petits prix</p>
            <p class="mt-1 text-sm leading-relaxed">
                Pour les articles à moins de 10 €, les frais Colissimo se situent souvent autour de 7 à 9 €.
                Vous pouvez laisser Colissimo, mais nous vous conseillons aussi d’activer la remise en main propre
                pour augmenter vos chances de vendre.
            </p>
        `;

        const parent = colissimoTextElement.closest('div') || colissimoTextElement;
        parent.insertAdjacentElement('afterend', warning);
    }

    function refreshWarning() {
        const price = parseFloat(String(priceInput.value || '').replace(',', '.')) || 0;

        if (price > 0 && price < 10) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }

    priceInput.addEventListener('input', refreshWarning);
    priceInput.addEventListener('change', refreshWarning);

    refreshWarning();
});
</script>

{{-- Bandeau cookies (RGPD) --}}
<div id="cookie-banner" class="fixed inset-x-0 bottom-0 z-[95] hidden">
    <div class="mx-auto max-w-4xl m-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-xl sm:flex sm:items-center sm:gap-4">
        <p class="text-sm text-gray-600 flex-1">
            🍪 Nous utilisons des cookies pour le bon fonctionnement du site et, avec votre accord, pour la mesure d'audience et la publicité.
            <a href="{{ route('legal.privacy') }}" class="font-semibold text-teal-700 hover:underline">En savoir plus</a>.
        </p>
        <div class="mt-3 flex gap-2 sm:mt-0 shrink-0">
            <button id="cookie-refuse" type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Refuser</button>
            <button id="cookie-accept" type="button" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Accepter</button>
        </div>
    </div>
</div>
<script>
(function () {
    var banner = document.getElementById('cookie-banner');
    if (!banner) return;

    // Dans l'application mobile, aucun traceur n'est chargé : il n'y a donc
    // rien à consentir, et Apple interdit une fenêtre maison demandant
    // l'autorisation de suivi (refus 5.1.2(i)). On retire le bandeau.
    if (document.documentElement.getAttribute('data-sans-traceurs') === '1') {
        banner.remove();

        return;
    }

    var has = document.cookie.match(/(?:^|; )swapiles_cookie_consent=/);
    if (!has) { banner.classList.remove('hidden'); }

    function setConsent(value) {
        var d = new Date(); d.setFullYear(d.getFullYear() + 1);
        document.cookie = 'swapiles_cookie_consent=' + value + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
        banner.classList.add('hidden');
        if (value === 'accepted' && window.SWP) { window.SWP.load(); }
    }

    var accept = document.getElementById('cookie-accept');
    var refuse = document.getElementById('cookie-refuse');
    if (accept) accept.addEventListener('click', function () { setConsent('accepted'); });
    if (refuse) refuse.addEventListener('click', function () { setConsent('refused'); });
})();
</script>

<script>
(function () {
    // iOS/Safari : au retour arrière (swipe du pouce ou bouton) la page est
    // restaurée depuis le bfcache, mais des images en loading="lazy" peuvent
    // rester vides. On force leur (re)chargement à la restauration.
    var page = { signal: window.swpPage() };

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) return;

        document.querySelectorAll('img[loading="lazy"]').forEach(function (img) {
            if (img.complete && img.naturalWidth > 0) return;

            var src = img.getAttribute('src');
            if (!src) return;

            img.loading = 'eager';
            img.src = src;
        });
    }, page);

    // Image qui échoue (scroll rapide qui avorte la requête, ou fichier absent) :
    // on réessaie une fois, puis on affiche un placeholder propre (📦) au lieu de
    // l'icône « image cassée ». Écouteur en capture car l'événement error ne bulle pas.
    var PLACEHOLDER = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="250">' +
        '<rect width="100%" height="100%" fill="#f3f4f6"/>' +
        '<text x="50%" y="52%" font-size="64" text-anchor="middle" dominant-baseline="middle">📦</text></svg>'
    );

    document.addEventListener('error', function (e) {
        var img = e.target;
        if (!(img instanceof HTMLImageElement)) return;

        var src = img.getAttribute('src');
        if (!src || src.indexOf('data:') === 0) return;

        var tries = parseInt(img.getAttribute('data-imgretry') || '0', 10);

        if (tries >= 1) {
            img.setAttribute('data-imgretry', '2');
            img.src = PLACEHOLDER;
            return;
        }

        img.setAttribute('data-imgretry', String(tries + 1));
        var clean = src.split('#')[0];
        setTimeout(function () {
            img.src = clean + (clean.indexOf('?') > -1 ? '&' : '?') + '_r=' + Date.now();
        }, 500);
    }, { capture: true, signal: page.signal });
})();
</script>

{{-- RÉPONSE IMMÉDIATE AU TOUCHER — liens ouverts par rechargement complet.
     Les liens suivis par Turbo sont acquittés dans resources/js/app.js ; ce
     script-ci couvre ceux qui rechargent la page (paiement, messagerie…),
     pour que le geste soit pris en compte à l'instant, quel que soit le lien.
     Le préchargement au toucher est désormais assuré par Turbo (app.js). --}}
<script>
    (function () {
        var page = { signal: window.swpPage() };
        var filet;

        function barre(active) {
            var element = document.getElementById('swp-chargement');
            if (element) element.classList.toggle('actif', active);
        }

        function demarrer() {
            barre(true);
            // Filet : téléchargement, navigation annulée… la barre ne doit
            // jamais rester affichée indéfiniment.
            clearTimeout(filet);
            filet = setTimeout(function () { barre(false); }, 10000);
        }

        document.addEventListener('click', function (e) {
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            var lien = e.target.closest && e.target.closest('a[href]');
            if (!lien || lien.target === '_blank' || lien.hasAttribute('download')) return;

            var cible;
            try { cible = new URL(lien.href, location.href); } catch (err) { return; }
            if (cible.origin !== location.origin) return;
            if (cible.pathname === location.pathname && cible.search === location.search && cible.hash) return;

            // On attend la fin du clic : Turbo, favoris, partage ou
            // signalement l'interceptent (preventDefault) sans recharger.
            setTimeout(function () {
                if (!e.defaultPrevented) demarrer();
            }, 0);
        }, page);

        document.addEventListener('submit', function (e) {
            setTimeout(function () {
                if (!e.defaultPrevented && !e.target.hasAttribute('data-sans-chargement')) demarrer();
            }, 0);
        }, page);

        // Retour arrière (page restaurée) ou départ : jamais de barre figée.
        window.addEventListener('pageshow', function () { barre(false); }, page);
        window.addEventListener('pagehide', function () { barre(false); }, page);
    })();
</script>

</body>
</html>
