@extends('layouts.app')

@section('title', 'Comment ça marche — Swap\'Îles')
@section('meta_description', 'Vendre, acheter, échanger ou donner sur Swap’Îles : dépôt gratuit, paiement protégé, main propre, point relais ou Colissimo.')

@php
    $e = fn ($v) => number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, ',', ' ') . ' €';
    $taux = (int) round((float) config('pricing.protection_rate', 0.10) * 100);
    $etape = 'rounded-3xl border border-gray-100 bg-white p-5 shadow-sm';
@endphp

@section('content')
<section class="bg-gray-950 text-white">
    <div class="max-w-4xl mx-auto px-4 py-10 sm:py-14">
        <p class="text-sm font-bold uppercase tracking-wide text-emerald-300">Simple et rapide</p>
        <h1 class="mt-2 text-3xl sm:text-4xl font-extrabold">Comment ça marche ?</h1>
        <p class="mt-3 text-white/70">Swap’Îles simplifie la seconde main dans les îles, du premier message jusqu’à la remise de l’article.</p>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('account.listings.create') }}" class="rounded-2xl bg-emerald-500 px-5 py-3 text-center font-bold text-gray-950">Déposer une annonce</a>
            <a href="{{ route('search') }}" class="rounded-2xl border border-white/20 px-5 py-3 text-center font-bold">Voir les articles</a>
        </div>
    </div>
</section>

<section class="max-w-4xl mx-auto px-4 py-10 space-y-10">

    <div>
        <h2 class="text-2xl font-extrabold text-gray-900">🛍️ Pour vendre</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="{{ $etape }}">
                <div class="text-4xl">📸</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 1</p>
                <h3 class="font-bold text-gray-900">Publiez gratuitement</h3>
                <p class="mt-1 text-sm text-gray-600">Quelques photos, un prix, votre île : l’annonce est en ligne. Le dépôt est gratuit et vous gardez le prix que vous fixez.</p>
            </div>
            <div class="{{ $etape }}">
                <div class="text-4xl">💬</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 2</p>
                <h3 class="font-bold text-gray-900">Discutez avec les acheteurs</h3>
                <p class="mt-1 text-sm text-gray-600">Questions, offres, lots avec remise : tout se passe dans la messagerie Swap’Îles.</p>
            </div>
            <div class="{{ $etape }}">
                <div class="text-4xl">💶</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 3</p>
                <h3 class="font-bold text-gray-900">Remettez et soyez payé</h3>
                <p class="mt-1 text-sm text-gray-600">Avec le paiement en ligne, vous êtes payé dès que l’acheteur a reçu ou retiré l’article.</p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-2xl font-extrabold text-gray-900">🛒 Pour acheter</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="{{ $etape }}">
                <div class="text-4xl">🔎</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 1</p>
                <h3 class="font-bold text-gray-900">Trouvez près de chez vous</h3>
                <p class="mt-1 text-sm text-gray-600">Les annonces sont sur votre île : pas besoin d’attendre un colis de métropole.</p>
            </div>
            <div class="{{ $etape }}">
                <div class="text-4xl">🛡️</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 2</p>
                <h3 class="font-bold text-gray-900">Payez en confiance</h3>
                <p class="mt-1 text-sm text-gray-600">Votre argent reste bloqué jusqu’à ce que vous ayez l’article. S’il n’arrive pas ou ne correspond pas à l’annonce, vous êtes remboursé.</p>
            </div>
            <div class="{{ $etape }}">
                <div class="text-4xl">📦</div>
                <p class="mt-3 text-sm font-bold text-teal-700">Étape 3</p>
                <h3 class="font-bold text-gray-900">Récupérez votre article</h3>
                <p class="mt-1 text-sm text-gray-600">En main propre, chez un commerçant point relais, ou par Colissimo. Confirmez la réception : le vendeur est payé.</p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-2xl font-extrabold text-gray-900">🚚 Les façons de remettre un article</h2>
        <div class="mt-4 divide-y divide-gray-100 rounded-3xl border border-gray-100 bg-white">
            <div class="p-5">
                <p class="font-bold text-gray-900">🤝 Main propre · gratuit</p>
                <p class="mt-1 text-sm text-gray-600">Vous vous retrouvez sur votre île. Paiement en ligne, en espèces, échange ou don : c’est vous qui choisissez.</p>
            </div>
            <div class="p-5">
                <p class="font-bold text-gray-900">🏪 Point relais · {{ $e(config('pricing.relay_fee', 3)) }}</p>
                <p class="mt-1 text-sm text-gray-600">Le vendeur dépose le colis chez un commerçant partenaire, l’acheteur le retire avec son code, sous {{ (int) config('relais.delai_garde_jours', 10) }} jours. <a href="{{ route('relay.points') }}" class="font-semibold text-teal-700 underline">Voir les points relais</a></p>
            </div>
            <div class="p-5">
                <p class="font-bold text-gray-900">📮 Colissimo · selon le poids</p>
                <p class="mt-1 text-sm text-gray-600">Sur votre île, vers une autre île ou vers la métropole. Le prix exact s’affiche avant de payer.</p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-2xl font-extrabold text-gray-900">💡 Ce que ça coûte</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="{{ $etape }}">
                <p class="font-bold text-gray-900">Pour le vendeur</p>
                <p class="mt-1 text-sm text-gray-600"><strong>Rien.</strong> Dépôt d’annonce gratuit, aucune commission : vous gardez le prix que vous fixez.</p>
            </div>
            <div class="{{ $etape }}">
                <p class="font-bold text-gray-900">Pour l’acheteur, en paiement en ligne</p>
                <p class="mt-1 text-sm text-gray-600">La <strong>protection acheteur</strong> : {{ $taux }} % du prix ({{ $e(config('pricing.protection_floor', 0.5)) }} minimum, {{ $e(config('pricing.protection_cap', 15)) }} maximum), plus la livraison choisie. Tout est détaillé avant de payer.</p>
            </div>
        </div>
    </div>

    <div class="rounded-3xl bg-teal-50 p-6">
        <h2 class="text-xl font-extrabold text-teal-900">🔄 Échanger ou donner</h2>
        <p class="mt-2 text-sm text-teal-900/80">Un article peut aussi s’échanger contre un autre, ou se donner. Proposez un échange depuis l’annonce, ou publiez votre article en don.</p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('account.listings.create') }}" class="rounded-2xl bg-teal-700 px-5 py-3 text-center font-bold text-white">Déposer une annonce</a>
        <a href="{{ route('faq') }}" class="rounded-2xl border border-gray-200 bg-white px-5 py-3 text-center font-bold text-gray-800">Questions fréquentes</a>
    </div>
</section>
@endsection
