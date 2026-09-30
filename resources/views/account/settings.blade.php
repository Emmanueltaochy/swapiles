@extends('layouts.app')

@section('title', 'Réglages — Swap\'Îles')

@section('content')
{{-- SOMMAIRE DES REGLAGES.
     Identite, adresse d'expedition, mot de passe, points relais, preferences
     de notification et suppression de compte vivaient tous dans une seule
     page « Modifier mon profil », sur plus de deux ecrans de defilement.
     Chaque reglage a maintenant son entree nommee, avec son etat visible :
     on voit d'un coup d'oeil ce qui manque. --}}
@php
    $stripePret = $user->stripe_account_id && $user->stripe_payouts_enabled;

    $groupes = [
        [
            'titre' => 'Mon profil',
            'entrees' => [
                [
                    'url' => route('account.profile.edit'),
                    'icone' => '👤',
                    'label' => 'Identité et photo',
                    'aide' => 'Nom, photo, téléphone, île',
                ],
                [
                    'url' => route('account.profile.edit') . '#mot-de-passe',
                    'icone' => '🔒',
                    'label' => 'Mot de passe',
                    'aide' => 'Changer mon mot de passe',
                ],
            ],
        ],
        [
            'titre' => 'Mes alertes',
            'entrees' => [
                [
                    'url' => route('account.notifications.preferences'),
                    'icone' => '🔔',
                    'label' => 'Préférences de notification',
                    'aide' => 'Choisir ce que je reçois, sur mobile et par e-mail',
                ],
                [
                    'url' => route('account.notifications.index'),
                    'icone' => '📨',
                    'label' => 'Mes notifications reçues',
                    'aide' => 'Historique de mes alertes',
                ],
            ],
        ],
        [
            'titre' => 'Acheter',
            'entrees' => [
                [
                    'url' => route('account.addresses.edit'),
                    'icone' => '📮',
                    'label' => 'Mes adresses de livraison',
                    'aide' => 'Où je me fais livrer mes achats',
                ],
            ],
        ],
        [
            'titre' => 'Vendre',
            'entrees' => array_values(array_filter([
                [
                    'url' => route('account.profile.edit') . '#expedition',
                    'icone' => '📦',
                    'label' => 'Adresse d\'expédition',
                    'aide' => 'Sert à générer mes bordereaux Colissimo',
                    'alerte' => $adresseComplete ? null : 'À compléter',
                ],
                config('features.relay_points') ? [
                    'url' => route('account.profile.edit') . '#points-relais',
                    'icone' => '🏪',
                    'label' => 'Mes points relais de dépôt',
                    'aide' => $relaisChoisis > 0
                        ? $relaisChoisis . ' point' . ($relaisChoisis > 1 ? 's' : '') . ' choisi' . ($relaisChoisis > 1 ? 's' : '')
                        : 'Tous les points relais de mon île sont proposés',
                ] : null,
                [
                    'url' => $stripePret ? route('account.wallet.index') : route('stripe.connect.activate'),
                    'icone' => '💳',
                    'label' => 'Recevoir mes paiements',
                    'aide' => $stripePret ? 'Virements activés' : 'Nécessaire pour être payé par carte',
                    'alerte' => $stripePret ? null : 'À activer',
                ],
            ])),
        ],
    ];
@endphp

<div class="min-h-screen bg-gray-50 pb-16">
    <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">

        <a href="{{ route('account.dashboard') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Mon compte</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">Réglages</h1>

        @if(session('status'))
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @foreach($groupes as $groupe)
            <h2 class="mt-6 px-1 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $groupe['titre'] }}</h2>

            <div class="mt-2 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                @foreach($groupe['entrees'] as $entree)
                    <a href="{{ $entree['url'] }}"
                       class="flex items-center gap-3 border-gray-100 px-4 py-4 transition hover:bg-gray-50 @if(! $loop->first) border-t @endif">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-50 text-xl" aria-hidden="true">{{ $entree['icone'] }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900">{{ $entree['label'] }}</span>
                                @if($entree['alerte'] ?? null)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">{{ $entree['alerte'] }}</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block text-sm text-gray-500">{{ $entree['aide'] }}</span>
                        </span>

                        <span class="shrink-0 text-gray-300" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        <h2 class="mt-6 px-1 text-xs font-bold uppercase tracking-wide text-gray-400">Mon compte</h2>

        <div class="mt-2 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 px-4 py-4 text-left transition hover:bg-gray-50">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-50 text-xl" aria-hidden="true">🚪</span>
                    <span class="flex-1 font-semibold text-gray-900">Se déconnecter</span>
                </button>
            </form>

            <a href="{{ route('account.deletion.info') }}"
               class="flex items-center gap-3 border-t border-gray-100 px-4 py-4 transition hover:bg-gray-50">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-red-50 text-xl" aria-hidden="true">🗑️</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-red-600">Supprimer mon compte</span>
                    <span class="mt-0.5 block text-sm text-gray-500">Effacer définitivement mes données</span>
                </span>
                <span class="shrink-0 text-gray-300" aria-hidden="true">›</span>
            </a>
        </div>

    </div>
</div>
@endsection
