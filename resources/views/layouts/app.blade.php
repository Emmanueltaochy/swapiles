<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', "Swap'Îles")</title>
    @vite(['resources/css/app.css','resources/js/app.js'])

<style id="swapiles-mobile-fix">
html, body {
    max-width: 100%;
    overflow-x: hidden;
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
        overflow-x: hidden;
    }
}
</style>
    <meta name="description" content="@yield('meta_description', 'Swap’Îles, la marketplace seconde main des îles : achetez, vendez, échangez et donnez près de chez vous à La Réunion, en Martinique, Guadeloupe, Guyane et Mayotte.')">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:title" content="@yield('title', 'Swap’Îles')">
    <meta property="og:description" content="@yield('meta_description', 'La marketplace seconde main des îles.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/logo.png'))">
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

    {{-- Suivi publicitaire, conditionné au consentement cookies (RGPD) --}}
    @php
        $metaPixelId = env('META_PIXEL_ID', '2716674522082712');
        $googleTagId = env('GOOGLE_TAG_ID', 'G-KH96S3FP4X');
        $pixelEvent = session('pixel_event');
    @endphp
    <script>
    window.SWP = {
        metaId: @json($metaPixelId),
        gaId: @json($googleTagId),
        pending: @json($pixelEvent),
        loaded: false,
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
                gtag('config', this.gaId);
            }
            if (this.pending) { this.track(this.pending.event, this.pending.params || {}); }
        },
        track: function (event, params) {
            if (window.fbq) { fbq('track', event, params || {}); }
            if (window.gtag) { gtag('event', event, params || {}); }
        }
    };
    (function () {
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
document.addEventListener('DOMContentLoaded', function () {
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
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="shrink-0 flex items-center">
                <img src="{{ asset('images/logo.png') }}" alt="Swap'Îles" class="h-9 sm:h-10 w-auto">
            </a>

            <form method="GET" action="{{ route('search') }}" class="flex-1 relative" id="header-search-form">
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

            <nav class="hidden lg:flex items-center gap-4 text-sm font-bold">
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

    <script>
        // Ferme le menu "Mon compte" du header quand on clique en dehors
        document.addEventListener('click', function (e) {
            document.querySelectorAll('details.account-menu[open]').forEach(function (d) {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
    </script>

    <main>
        @yield('content')
    </main>

    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-t border-gray-200">
        <div class="grid grid-cols-5 h-[74px] text-[11px] font-bold">
            <a href="{{ route('home') }}" class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs('home') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">🏠</span><span>Accueil</span>
            </a>

            <a href="{{ route('search') }}" class="flex flex-col items-center justify-center gap-1 {{ request()->routeIs('search') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">🔎</span><span>Produits</span>
            </a>

            <a href="/deposer-une-annonce" class="flex flex-col items-center justify-center -mt-6">
                <span class="w-16 h-16 rounded-full bg-teal-700 text-white flex items-center justify-center shadow-xl border-4 border-white text-3xl">+</span>
                <span class="text-teal-800 mt-1">Déposer</span>
            </a>

            <a href="{{ route('account.messages.index') }}" class="relative flex flex-col items-center justify-center gap-1 {{ request()->routeIs('account.messages.*') ? 'text-teal-700' : 'text-gray-500' }}">
                <span class="text-xl">💬</span><span>Messages</span>
                @if($unreadMessagesCount > 0)
                    <span class="absolute top-2 right-5 bg-red-600 text-white text-[10px] font-extrabold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                        {{ $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('account.dashboard') }}" class="relative flex flex-col items-center justify-center gap-1 {{ request()->routeIs('account.*') ? 'text-teal-700' : 'text-gray-500' }}">
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
                <p class="text-gray-500">Comment ça marche</p>
                <p class="text-gray-500">Catégories</p>
                <p class="text-gray-500">FAQ</p>
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
    });
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

</body>
</html>


