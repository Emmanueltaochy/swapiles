@extends('layouts.app')

@section('title', 'Mon compte — Swap\'Îles')

@section('content')
@php
    $annoncesParEtat = $annoncesParEtat ?? collect();
    $activeListingsCount = (int) ($annoncesParEtat['published'] ?? 0);
    $draftListingsCount  = (int) ($annoncesParEtat['draft'] ?? 0);
    $soldListingsCount   = (int) ($annoncesParEtat['sold'] ?? 0);
    $totalAnnonces       = (int) $annoncesParEtat->sum();

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

        @if(\App\Support\Vacances::enVacances($user))
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-amber-900"><strong>🌴 Mode vacances :</strong> vos annonces sont en pause jusqu’au {{ \App\Support\Vacances::retour($user) }}.</p>
                <form method="POST" action="{{ route('account.vacances.terminer') }}">
                    @csrf
                    @method('DELETE')
                    <button class="w-full whitespace-nowrap rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700 sm:w-auto">Je suis de retour</button>
                </form>
            </div>
        @endif

        {{-- 1. En-tête : photo, prénom, bio (comme Instagram), puis les deux boutons. --}}
        <header class="space-y-4">
            <div class="flex items-start gap-4">
                {{-- Photo : on la touche, on choisit une photo, elle part aussitôt. --}}
                <form method="POST" action="{{ route('account.profile.photo') }}" enctype="multipart/form-data" class="shrink-0 pb-1.5">
                    @csrf
                    <label for="photo-profil" class="relative block cursor-pointer" title="{{ $user->avatar ? 'Modifier la photo de profil' : 'Mettre une photo de profil' }}">
                        <span class="grid h-16 w-16 sm:h-20 sm:w-20 place-items-center overflow-hidden rounded-full bg-teal-100 text-2xl font-extrabold text-teal-800 ring-4 ring-white shadow">
                            @if($user->avatar)
                                <img src="{{ $user->avatar }}" alt="Ma photo de profil" class="h-full w-full object-cover">
                            @else
                                {{ mb_strtoupper(mb_substr($user->name ?? '?', 0, 1)) }}
                            @endif
                        </span>
                        <span class="absolute -bottom-0.5 -right-0.5 grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm text-white ring-2 ring-white" aria-hidden="true">📷</span>
                    </label>
                    <input id="photo-profil" type="file" name="avatar" accept="image/*" class="sr-only"
                           onchange="if (this.files[0]) { var t = document.querySelector('[data-photo-texte]'); if (t) t.textContent = 'Envoi de la photo…'; this.form.submit(); }">
                </form>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Mon compte</p>
                    <h1 class="mt-0.5 truncate text-2xl sm:text-3xl font-bold text-gray-900">Bonjour {{ $user->name }}</h1>

                    @if($user->bio)
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-gray-700">{{ $user->bio }}</p>
                    @endif

                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-semibold">
                        <label for="photo-profil" class="cursor-pointer text-teal-700 hover:text-teal-900" data-photo-texte>
                            {{ $user->avatar ? '📷 Modifier la photo' : '📷 Mettre une photo de profil' }}
                        </label>
                        <button type="button" class="text-teal-700 hover:text-teal-900"
                                onclick="var f = document.getElementById('bio-form'); f.hidden = !f.hidden; if (!f.hidden) f.querySelector('textarea').focus();">
                            {{ $user->bio ? '✏️ Modifier la bio' : '✏️ Ajouter une bio' }}
                        </button>
                    </div>

                    @error('avatar')
                        <p class="mt-1 text-sm font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Bio : quelques mots qui s'affichent sous ton nom, sur ton profil. --}}
            <form id="bio-form" method="POST" action="{{ route('account.profile.bio') }}" @if(! $errors->has('bio')) hidden @endif
                  class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                @csrf
                @method('PATCH')
                <label for="bio" class="text-sm font-bold text-gray-900">Ma bio</label>
                <p class="text-xs text-gray-500">Elle s’affiche sous ton nom sur ton profil. Ex : « Maman de 3, je vide les placards 🌴 Envoi rapide »</p>
                <textarea id="bio" name="bio" rows="3" maxlength="{{ \App\Support\Bio::MAX }}"
                          class="mt-2 w-full rounded-xl border-0 bg-gray-100 px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-600"
                          oninput="this.form.querySelector('[data-bio-compteur]').textContent = this.value.length + ' / {{ \App\Support\Bio::MAX }}'">{{ old('bio', $user->bio) }}</textarea>
                @error('bio')
                    <p class="mt-1 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="text-xs text-gray-400" data-bio-compteur>{{ mb_strlen((string) old('bio', $user->bio)) }} / {{ \App\Support\Bio::MAX }}</span>
                    <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">Enregistrer</button>
                </div>
            </form>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-start">
                <a href="{{ route('profiles.show', $user) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border-2 border-teal-600 bg-white px-4 py-2.5 text-sm font-bold text-teal-700 hover:bg-teal-50">
                    👤 Voir mon profil
                </a>
                <a href="{{ route('account.listings.create') }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M10 4v12M4 10h12"/></svg> Déposer une annonce
                </a>
            </div>
        </header>

        {{-- Incitation à compléter le profil (tant qu'il n'est pas à 100 %) --}}
        @php $profilePct = $user->profileCompletion(); @endphp
        @if($profilePct < 100)
            <a href="{{ route('profiles.show', $user) }}"
               class="block rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5 transition hover:border-amber-300 hover:bg-amber-50">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-base font-extrabold text-gray-900">🏆 Complétez votre profil ({{ $profilePct }}%)</p>
                        <p class="mt-0.5 text-sm text-gray-600">
                            Finalisez votre profil pour débloquer le badge <strong>« Profil complet »</strong> et vendre plus vite.
                            <span class="font-semibold text-amber-700">Voir ce qu'il reste à faire →</span>
                        </p>
                    </div>
                    <span class="shrink-0 text-2xl font-black text-amber-600">{{ $profilePct }}%</span>
                </div>
                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-amber-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-yellow-500" style="width: {{ max(4, $profilePct) }}%;"></div>
                </div>
            </a>
        @endif

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
                                @if($walletPending > 0)
                                    <p class="font-semibold text-amber-900">💶 {{ number_format($walletPending, 2, ',', ' ') }} € t’attendent</p>
                                    <p class="text-sm text-amber-700">Ajoute ton IBAN pour les recevoir — 2 min, sans pièce d’identité.</p>
                                @else
                                    <p class="font-semibold text-amber-900">Activez vos paiements</p>
                                    <p class="text-sm text-amber-700">Ajoutez votre IBAN pour recevoir l'argent de vos ventes — 2 min, sans pièce d’identité.</p>
                                @endif
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

        {{-- Parrainage : mis en avant, avec la récompense en clair. --}}
        @if(\App\Support\ParrainageService::actifPour($user))
            @php
                $recompenseParrainage = (float) config('referral.reward_amount', 0);
                $mesFilleuls = \App\Models\Parrainage::where('parrain_id', $user->id)->count();
                $monCredit = \App\Support\Portefeuille::solde($user);
            @endphp
            <a href="{{ route('parrainage.index') }}"
               class="relative block overflow-hidden rounded-2xl bg-gradient-to-br from-teal-700 via-teal-600 to-emerald-500 p-5 text-white shadow-lg shadow-teal-900/10 transition hover:shadow-xl sm:p-6">
                <span class="pointer-events-none absolute -right-6 -top-8 text-[110px] leading-none opacity-15" aria-hidden="true">🎁</span>
                <span class="relative block">
                    <span class="inline-block rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide">Parrainage</span>
                    <span class="mt-2 block text-xl font-extrabold leading-tight sm:text-2xl">
                        @if($recompenseParrainage > 0)
                            Gagne {{ \App\Support\ParrainageService::montantCourt($recompenseParrainage) }} pour chaque ami invité 🌴
                        @else
                            Invite tes amis sur Swap’Îles 🌴
                        @endif
                    </span>
                    <span class="mt-1.5 block text-sm text-white/90">
                        Ton ami a sa <strong>protection acheteur offerte</strong> sur son 1er achat
                        @if($recompenseParrainage > 0), et toi tu reçois <strong>{{ number_format($recompenseParrainage, 2, ',', ' ') }} € de crédit</strong> dès qu’il a reçu son article.@else.@endif
                    </span>
                    <span class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="rounded-xl bg-white px-4 py-2.5 text-sm font-extrabold text-teal-800 shadow-sm">Inviter mes amis →</span>
                        @if($mesFilleuls > 0 || $monCredit > 0)
                            <span class="text-sm font-semibold text-white/90">
                                💚 {{ $mesFilleuls }} ami{{ $mesFilleuls > 1 ? 's' : '' }} invité{{ $mesFilleuls > 1 ? 's' : '' }}@if($monCredit > 0) · {{ number_format($monCredit / 100, 2, ',', ' ') }} € de crédit @endif
                            </span>
                        @endif
                    </span>
                </span>
            </a>
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
            <p class="mt-3 text-xs text-gray-600">
                💳 Uniquement tes <span class="font-semibold">ventes par carte (CB)</span>.
                Tes ventes en <span class="font-semibold">espèces</span>, <span class="font-semibold">dons</span> et <span class="font-semibold">échanges</span> ne sont pas comptées ici —
                retrouve-les dans <a href="{{ route('account.wallet.index') }}" class="font-semibold text-teal-600 hover:text-teal-700">« Toutes mes ventes »</a>.
            </p>
            <p class="mt-1 text-xs text-gray-400">Le vendeur reçoit 100 % du prix affiché (aucune commission).</p>
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
                @if($toutesLesAnnonces ?? false)
                    <a href="{{ route('account.dashboard') }}#mes-annonces" class="text-sm font-semibold text-teal-700 hover:text-teal-900">Réduire</a>
                @endif
            </div>

            @if(($toutesLesAnnonces ?? false) && $totalAnnonces > 0)
                {{-- Filtre par état, seulement dans la liste complète. --}}
                <div class="flex gap-2 overflow-x-auto border-b border-gray-100 px-3 py-2.5 sm:px-4">
                    @foreach([null => 'Toutes · ' . $totalAnnonces, 'published' => 'En ligne · ' . $activeListingsCount, 'draft' => 'Brouillons · ' . $draftListingsCount, 'sold' => 'Vendues · ' . $soldListingsCount] as $etat => $libelle)
                        <a href="{{ route('account.dashboard', array_filter(['annonces' => 'toutes', 'etat' => $etat ?: null])) }}#mes-annonces"
                           class="shrink-0 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold {{ ($filtreAnnonces ?? null) === ($etat ?: null) ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $libelle }}</a>
                    @endforeach
                </div>
            @endif

            @if($listings->isEmpty() && ($filtreAnnonces ?? null))
                <p class="p-8 text-center text-sm text-gray-500">Aucune annonce dans cet état.</p>
            @elseif($listings->isEmpty())
                <div class="p-10 text-center">
                    <div class="text-3xl" aria-hidden="true">🌴</div>
                    <h3 class="mt-3 text-lg font-semibold text-gray-900">Aucune annonce pour le moment</h3>
                    <p class="mt-1 text-gray-500">Déposez votre première annonce, c'est gratuit.</p>
                    <a href="{{ route('account.listings.create') }}"
                       class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
                        Déposer une annonce
                    </a>
                </div>
            @else
                {{-- Deux annonces par ligne sur téléphone, comme une vitrine. --}}
                <div class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-3 sm:gap-4 sm:p-4 lg:grid-cols-4">
                    @foreach($listings as $listing)
                        <div class="flex flex-col rounded-2xl border border-gray-100 bg-white shadow-sm">
                            <a href="{{ route('listings.show', $listing) }}" class="relative block aspect-[3/4] overflow-hidden rounded-t-2xl bg-gray-100">
                                @if($listing->images->first())
                                    <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}"
                                         class="h-full w-full object-cover" loading="lazy" decoding="async">
                                @else
                                    <div class="grid h-full w-full place-items-center text-3xl text-gray-300" aria-hidden="true">📦</div>
                                @endif
                                <span class="absolute left-2 top-2 rounded-full px-2 py-0.5 text-[11px] font-bold shadow-sm
                                    @if($listing->status === 'published') bg-teal-600 text-white
                                    @elseif($listing->status === 'sold') bg-gray-800 text-white
                                    @elseif($listing->status === 'vacances') bg-sky-500 text-white
                                    @else bg-amber-400 text-amber-950 @endif">
                                    @if($listing->status === 'published') En ligne
                                    @elseif($listing->status === 'sold') Vendue
                                    @elseif($listing->status === 'vacances') 🌴 En pause
                                    @else Brouillon @endif
                                </span>
                            </a>

                            <div class="flex flex-1 flex-col p-2.5">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $listing->title }}</p>
                                <p class="text-sm font-extrabold text-gray-900">{{ $listing->price > 0 ? number_format($listing->price, 0, ',', ' ') . ' €' : 'Gratuit' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">👀 {{ (int) $listing->views_count }} · ❤️ {{ (int) ($listing->favorited_by_count ?? 0) }}</p>

                                <div class="mt-2.5 flex items-center gap-1.5">
                                    <a href="{{ route('account.listings.edit', $listing) }}"
                                       class="flex-1 rounded-lg bg-gray-100 px-2 py-2 text-center text-xs font-bold text-gray-800 hover:bg-gray-200">Modifier</a>
                                    {{-- « account-menu » : se referme au toucher ailleurs (layout). --}}
                                    <details class="account-menu relative">
                                        <summary class="grid h-8 w-9 cursor-pointer list-none place-items-center rounded-lg bg-gray-100 text-sm font-bold text-gray-700 hover:bg-gray-200 [&::-webkit-details-marker]:hidden" aria-label="Plus d’actions">•••</summary>
                                        <div class="absolute bottom-10 right-0 z-20 w-40 overflow-hidden rounded-xl border border-gray-100 bg-white text-sm shadow-xl">
                                            @if($listing->status === 'published')
                                                {{-- Vendue : on passe par la fiche, qui demande à qui (sinon
                                                     impossible de distinguer une vente d'un simple retrait). --}}
                                                <a href="{{ route('listings.show', $listing) }}#fin-annonce" class="block px-3 py-2.5 font-semibold text-green-700 hover:bg-green-50">✅ Vendue</a>
                                                <form method="POST" action="{{ route('account.listings.hide', $listing) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="block w-full px-3 py-2.5 text-left font-semibold text-orange-700 hover:bg-orange-50">🙈 Masquer</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('account.listings.publish', $listing) }}">
                                                    @csrf @method('PATCH')
                                                    <button class="block w-full px-3 py-2.5 text-left font-semibold text-teal-700 hover:bg-teal-50">🚀 Publier</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('account.listings.destroy', $listing) }}"
                                                  onsubmit="return confirm('Supprimer cette annonce ?');">
                                                @csrf @method('DELETE')
                                                <button class="block w-full border-t border-gray-100 px-3 py-2.5 text-left font-semibold text-red-700 hover:bg-red-50">🗑 Supprimer</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(method_exists($listings, 'hasPages') && $listings->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $listings->links() }}
                </div>
            @elseif(! ($toutesLesAnnonces ?? false) && $totalAnnonces > $listings->count())
                <a href="{{ route('account.dashboard', ['annonces' => 'toutes']) }}#mes-annonces"
                   class="block border-t border-gray-100 px-5 py-3.5 text-center text-sm font-bold text-teal-700 hover:bg-teal-50">
                    Voir mes {{ $totalAnnonces }} annonces →
                </a>
            @endif
        </section>

        {{-- 6. Le concept.
             Les points relais, le classement, le fonctionnement de la
             protection acheteur : tout cela ne vit que dans le menu ou au
             moment de payer. Ce sont pourtant les trois choses qui font
             Swap'Iles. Elles ont leur place ici, apres les annonces, sans
             encombrer la grille de raccourcis reservee aux gestes du
             quotidien. --}}
        @php
            $mesAbonnes = $user->followers()->count();
            $conceptEntrees = array_values(array_filter([
                \App\Support\ParrainageService::actifPour($user) ? [
                    'url' => route('parrainage.index'),
                    'icone' => '💚',
                    'label' => 'Inviter des amis' . ((float) config('referral.reward_amount') > 0 ? ' · ' . \App\Support\ParrainageService::montantCourt((float) config('referral.reward_amount')) . ' par ami' : ''),
                    'aide' => 'Leur protection acheteur est offerte, et toi tu gagnes du crédit',
                ] : null,
                [
                    'url' => route('profiles.show', $user),
                    'icone' => '📣',
                    'label' => 'Mon dressing · ' . number_format($mesAbonnes, 0, ',', ' ') . ' abonné' . ($mesAbonnes > 1 ? 's' : ''),
                    'aide' => 'Partagez-le : vos abonnés sont prévenus de chaque nouvel article',
                ],
                $user->managesAnyRelay() ? [
                    'url' => route('account.relay.dashboard'),
                    'icone' => '🏪',
                    'label' => 'Mon espace relais',
                    'aide' => 'Colis à réceptionner et à remettre',
                ] : null,
                [
                    'url' => route('relay.partner'),
                    'icone' => '🏬',
                    'label' => 'Devenir point relais',
                    'aide' => 'Recevoir les colis des membres dans mon commerce',
                ],
                [
                    'url' => route('dressings.top'),
                    'icone' => '🏆',
                    'label' => 'Classement des dressings',
                    'aide' => 'Les vendeurs les plus actifs de mon île',
                ],
                [
                    'url' => route('home') . '#comment-ca-marche',
                    'icone' => '❓',
                    'label' => 'Comment ça marche',
                    'aide' => 'Paiement protégé, livraison, inter-îles',
                ],
            ]));
        @endphp

        <section aria-labelledby="concept-titre" class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <h2 id="concept-titre" class="border-b border-gray-100 px-5 py-4 font-semibold text-gray-900">Swap'Îles</h2>

            @foreach($conceptEntrees as $entree)
                <a href="{{ $entree['url'] }}"
                   class="flex items-center gap-3 border-gray-100 px-5 py-4 transition hover:bg-gray-50 @if(! $loop->first) border-t @endif">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-50 text-xl" aria-hidden="true">{{ $entree['icone'] }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-900">{{ $entree['label'] }}</span>
                        <span class="mt-0.5 block text-sm text-gray-500">{{ $entree['aide'] }}</span>
                    </span>
                    <span class="shrink-0 text-gray-300" aria-hidden="true">›</span>
                </a>
            @endforeach
        </section>

        {{-- 7. Reglages.
             Trois boutons poses cote a cote ne disaient pas ce qu'on trouvait
             derriere, et « Modifier mon profil » cachait en realite l'adresse
             d'expedition, le mot de passe, les points relais et les
             preferences de notification. Une seule porte, nommee. --}}
        <section aria-label="Réglages" class="space-y-2">
            <a href="{{ route('account.settings') }}"
               class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:bg-gray-50">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gray-50 text-xl" aria-hidden="true">⚙️</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-gray-900">Réglages</span>
                    <span class="mt-0.5 block text-sm text-gray-500">Profil, adresses, notifications, mot de passe</span>
                </span>
                <span class="shrink-0 text-gray-300" aria-hidden="true">›</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-500 transition hover:bg-gray-50 sm:w-auto">
                    Se déconnecter
                </button>
            </form>
        </section>

    </div>
</div>
@endsection
