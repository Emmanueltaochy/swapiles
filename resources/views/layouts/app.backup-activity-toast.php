<!DOCTYPE html>
<html lang="fr" class="h-full bg-stone-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Swap\'Îles') — La marketplace seconde main des îles</title>
    <meta name="description" content="@yield('description', 'Achetez, vendez, échangez et donnez en seconde main à La Réunion et dans les territoires ultramarins.')">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --teal: #1A8378;
            --teal-dark: #0F5C54;
            --teal-light: #E6F2F0;
            --coral: #FF6B5A;
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-gray-900 bg-stone-50">

    {{-- Barre compte utilisateur --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-end gap-3 text-sm">
            @auth
                <span class="hidden sm:inline text-gray-500">
                    Connecté : <strong class="text-gray-900">{{ auth()->user()->name }}</strong>
                </span>

                <a href="{{ route('account.dashboard') }}" class="font-bold text-teal-700 hover:text-teal-900">
                    Mon compte
                </a>

                <a href="{{ route('account.messages.index') }}" class="relative font-bold text-gray-700 hover:text-teal-700">
                    Messages
                    @php
                        $unreadMessagesCount = auth()->user()->receivedMessages()->whereNull('read_at')->count();
                    @endphp

                    @if($unreadMessagesCount > 0)
                        <span class="absolute -top-2 -right-3 bg-red-600 text-white text-[10px] font-bold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                            {{ $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('account.wallet.index') }}" class="font-bold text-gray-700 hover:text-teal-700">
                    Wallet
                </a>

                <a href="{{ route('account.favorites.index') }}" class="relative font-bold text-gray-700 hover:text-teal-700">
                    Favoris
                    @php
                        $favoritesCount = auth()->user()->favorites()->count();
                    @endphp

                    @if($favoritesCount > 0)
                        <span class="absolute -top-2 -right-3 bg-teal-700 text-white text-[10px] font-bold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">
                            {{ $favoritesCount > 9 ? '9+' : $favoritesCount }}
                        </span>
                    @endif
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="font-semibold text-gray-500 hover:text-gray-900">
                        Déconnexion
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="font-bold text-teal-700 hover:text-teal-900">
                    Connexion
                </a>

                <a href="{{ route('register') }}" class="font-bold text-gray-700 hover:text-gray-900">
                    Inscription
                </a>
            @endauth
        </div>
    </div>


    <header class="sticky top-0 z-40 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                <a href="{{ route('home') }}" class="flex-shrink-0">
                    <img src="/images/logo.png" alt="Swap'Îles" class="h-10 w-auto">
                </a>

                <div class="hidden md:flex flex-1 max-w-2xl">
                    <div class="relative w-full">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="search" placeholder="Rechercher parmi les annonces..." class="w-full pl-12 pr-4 py-2.5 bg-gray-100 hover:bg-gray-200 focus:bg-white focus:ring-2 focus:ring-teal-600 border border-transparent rounded-full text-sm transition">
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    <a href="{{ route('account.listings.create') }}" class="hidden lg:inline-block text-sm font-medium text-gray-700 hover:text-teal-700">
                        S'inscrire
                    </a>
                    <a href="#" class="hidden lg:inline-block text-sm font-medium text-gray-700 hover:text-teal-700">
                        Se connecter
                    </a>
                    <a href="#" class="inline-flex items-center gap-1.5 bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold px-4 py-2 rounded-full transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                        Déposer une annonce
                    </a>
                </div>
            </div>

            <div class="md:hidden pb-3">
                <div class="relative">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="search" placeholder="Rechercher..." class="w-full pl-12 pr-4 py-2.5 bg-gray-100 rounded-full text-sm">
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
                <div class="col-span-2 md:col-span-1">
                    <img src="/images/logo.png" alt="Swap'Îles" class="h-12 w-auto mb-3">
                    <p class="text-gray-600 leading-relaxed">La marketplace seconde main pensée pour les territoires ultramarins.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">Plateforme</h4>
                    <ul class="space-y-2 text-gray-600">
                        <li><a href="#" class="hover:text-teal-700">Comment ça marche</a></li>
                        <li><a href="#" class="hover:text-teal-700">Catégories</a></li>
                        <li><a href="#" class="hover:text-teal-700">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">Territoires</h4>
                    <ul class="space-y-2 text-gray-600">
                        <li>🇷🇪 La Réunion</li>
                        <li>🇬🇫 Guyane</li>
                        <li>🇲🇶 Martinique</li>
                        <li>🇬🇵 Guadeloupe</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3">Légal</h4>
                    <ul class="space-y-2 text-gray-600">
                        <li><a href="#" class="hover:text-teal-700">CGU</a></li>
                        <li><a href="#" class="hover:text-teal-700">Confidentialité</a></li>
                        <li><a href="#" class="hover:text-teal-700">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-200 text-center text-sm text-gray-500">
                © {{ date('Y') }} Swap'Îles — Tous droits réservés
            </div>
        </div>
    </footer>

</body>
</html>
