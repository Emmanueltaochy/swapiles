@extends('layouts.app')

@section('title', 'Mon compte — Swap\'Îles')

@section('content')
@php
    $listingsCollection = $listings ?? collect();
    $activeListingsCount = $listingsCollection->where('status', 'published')->count();
    $draftListingsCount  = $listingsCollection->where('status', 'draft')->count();
    $soldListingsCount   = $listingsCollection->where('status', 'sold')->count();

    $salesToShipCount = ($sales ?? collect())
        ->where('status', 'paid')->where('shipping_status', 'pending')->count();
    $purchasesToConfirmCount = ($purchases ?? collect())
        ->where('status', 'paid')->where('shipping_status', 'shipped')->count();

    $stripeReady    = $user->stripe_account_id && $user->stripe_payouts_enabled;
    $walletPending  = (float) ($pendingSalesAmount ?? 0);
    $walletAvailable = (float) ($availableAmount ?? 0);

    $todoCount = $salesToShipCount + $purchasesToConfirmCount + ($stripeReady ? 0 : 1);

    // Raccourcis de navigation du compte
    $shortcuts = [
        ['route' => 'account.transactions.index',      'icon' => '📦', 'label' => 'Ventes & achats'],
        ['route' => 'account.messages.index',          'icon' => '💬', 'label' => 'Messages'],
        ['route' => 'account.favorites.index',         'icon' => '❤️', 'label' => 'Favoris'],
        ['route' => 'account.followed-sellers.index',  'icon' => '👥', 'label' => 'Vendeurs suivis'],
        ['route' => 'account.followers.index',         'icon' => '🙋', 'label' => 'Mes abonnés'],
        ['route' => 'account.notifications.index',     'icon' => '🔔', 'label' => 'Notifications'],
    ];
@endphp

<div class="bg-gray-50 min-h-screen pb-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">

        @if(session('status'))
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        {{-- 1. En-tête --}}
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Mon compte</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">Bonjour {{ $user->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('profiles.show', $user) }}"
                   class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Voir mon profil
                </a>
                <a href="{{ route('account.listings.create') }}"
                   class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                    ➕ Déposer une annonce
                </a>
            </div>
        </header>

        {{-- Confirmation d'e-mail (non bloquant) --}}
        @if(is_null($user->email_verified_at))
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-2xl" aria-hidden="true">✉️</span>
                    <div class="min-w-0">
                        <p class="font-semibold text-sky-900">Confirmez votre adresse e-mail</p>
                        <p class="text-sm text-sky-700">Un e-mail vous a été envoyé. Cliquez sur le lien pour sécuriser votre compte.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                    @csrf
                    <button class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                        Renvoyer l'e-mail
                    </button>
                </form>
            </div>
        @endif

        {{-- 2. À traiter (uniquement s'il y a une action requise) --}}
        @if($todoCount > 0)
            <section aria-labelledby="todo-title" class="space-y-3">
                <h2 id="todo-title" class="text-sm font-semibold text-gray-500">À traiter</h2>

                @unless($stripeReady)
                    <a href="{{ route('stripe.connect.activate') }}"
                       class="flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 hover:bg-amber-100/70">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-2xl" aria-hidden="true">🏦</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-amber-900">Activez vos paiements</p>
                                <p class="text-sm text-amber-700">Ajoutez votre IBAN pour recevoir l'argent de vos ventes.</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-amber-800">Configurer →</span>
                    </a>
                @endunless

                @if($salesToShipCount > 0 || $purchasesToConfirmCount > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if($salesToShipCount > 0)
                            <a href="{{ route('account.transactions.index') }}"
                               class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm hover:bg-gray-50">
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-50 text-lg" aria-hidden="true">📮</span>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $salesToShipCount }} à expédier</p>
                                    <p class="text-sm text-gray-500">Ventes payées à envoyer</p>
                                </div>
                            </a>
                        @endif
                        @if($purchasesToConfirmCount > 0)
                            <a href="{{ route('account.transactions.index') }}"
                               class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm hover:bg-gray-50">
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-50 text-lg" aria-hidden="true">✅</span>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $purchasesToConfirmCount }} à confirmer</p>
                                    <p class="text-sm text-gray-500">Colis reçus à valider</p>
                                </div>
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        @endif

        {{-- 3. Mon argent --}}
        <section aria-labelledby="wallet-title" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h2 id="wallet-title" class="font-semibold text-gray-900">💰 Mon argent</h2>
                <a href="{{ route('account.wallet.index') }}" class="text-sm font-semibold text-teal-600 hover:text-teal-700">Détail →</a>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-amber-50 p-4">
                    <p class="text-2xl font-bold text-amber-900">{{ number_format($walletPending, 2, ',', ' ') }} €</p>
                    <p class="mt-0.5 text-xs font-medium text-amber-700">⏳ En cours</p>
                </div>
                <div class="rounded-xl bg-emerald-50 p-4">
                    <p class="text-2xl font-bold text-emerald-900">{{ number_format($walletAvailable, 2, ',', ' ') }} €</p>
                    <p class="mt-0.5 text-xs font-medium text-emerald-700">✅ Disponible</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-400">Montants nets, commission Swap'Îles déjà déduite.</p>
        </section>

        {{-- 4. Raccourcis --}}
        <section aria-label="Raccourcis" class="grid grid-cols-3 sm:grid-cols-6 gap-3">
            @foreach($shortcuts as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex flex-col items-center gap-2 rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm hover:bg-gray-50">
                    <span class="text-2xl" aria-hidden="true">{{ $item['icon'] }}</span>
                    <span class="text-xs font-semibold text-gray-700 leading-tight">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </section>

        {{-- 5. Mes annonces --}}
        <section id="mes-annonces" class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5 border-b border-gray-100">
                <div>
                    <h2 class="font-semibold text-gray-900">Mes annonces</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ $activeListingsCount }} active{{ $activeListingsCount > 1 ? 's' : '' }}
                        · {{ $draftListingsCount }} brouillon{{ $draftListingsCount > 1 ? 's' : '' }}
                        · {{ $soldListingsCount }} vendue{{ $soldListingsCount > 1 ? 's' : '' }}
                    </p>
                </div>
                <a href="{{ route('account.listings.create') }}"
                   class="rounded-xl bg-teal-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-teal-700">
                    Ajouter une annonce
                </a>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($listings as $listing)
                    <div class="flex flex-col sm:flex-row gap-4 sm:items-center p-4">
                        <a href="{{ route('listings.show', $listing) }}"
                           class="block w-full sm:w-20 h-44 sm:h-20 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                            @if($listing->images->first())
                                <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}"
                                     class="h-full w-full object-cover" loading="lazy" decoding="async">
                            @else
                                <div class="grid h-full w-full place-items-center text-2xl text-gray-300" aria-hidden="true">📦</div>
                            @endif
                        </a>

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $listing->title }}</p>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $listing->price > 0 ? number_format($listing->price, 0, ',', ' ') . ' €' : 'Gratuit' }}
                                · 👀 {{ (int) $listing->views_count }}
                                · ❤️ {{ (int) ($listing->favorited_by_count ?? 0) }}
                            </p>
                            <span class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                @if($listing->status === 'published') bg-teal-50 text-teal-700
                                @elseif($listing->status === 'sold') bg-gray-100 text-gray-600
                                @else bg-amber-50 text-amber-700 @endif">
                                @if($listing->status === 'published') En ligne
                                @elseif($listing->status === 'sold') Vendue
                                @else Brouillon @endif
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <a href="{{ route('account.listings.edit', $listing) }}"
                               class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                                Modifier
                            </a>

                            @if($listing->status === 'published')
                                <form method="POST" action="{{ route('account.listings.hide', $listing) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded-lg bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-100">Masquer</button>
                                </form>
                                <form method="POST" action="{{ route('account.listings.sold', $listing) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 hover:bg-green-100">Vendue</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('account.listings.publish', $listing) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded-lg bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-100">Publier</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('account.listings.destroy', $listing) }}"
                                  onsubmit="return confirm('Supprimer cette annonce ?');">
                                @csrf @method('DELETE')
                                <button class="rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Supprimer</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center">
                        <div class="text-3xl" aria-hidden="true">🌴</div>
                        <h3 class="mt-3 text-lg font-semibold text-gray-900">Aucune annonce pour le moment</h3>
                        <p class="mt-1 text-gray-500">Déposez votre première annonce, c'est gratuit.</p>
                        <a href="{{ route('account.listings.create') }}"
                           class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
                            Déposer une annonce
                        </a>
                    </div>
                @endforelse
            </div>

            @if($listings->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $listings->links() }}
                </div>
            @endif
        </section>

        {{-- 6. Réglages --}}
        <section aria-label="Réglages" class="flex flex-wrap items-center gap-2">
            <a href="{{ route('account.profile.edit') }}"
               class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                ⚙️ Modifier mon profil
            </a>
            <a href="{{ route('account.addresses.edit') }}"
               class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                📮 Mes adresses
            </a>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                @csrf
                <button class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-500 hover:bg-gray-50">
                    Se déconnecter
                </button>
            </form>
        </section>

    </div>
</div>
@endsection
