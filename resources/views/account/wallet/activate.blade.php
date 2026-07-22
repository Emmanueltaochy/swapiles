@extends('layouts.app')

@section('title', 'Activer mon portefeuille — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6">

        <div class="mb-6">
            <a href="{{ route('account.wallet.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Retour à mon wallet</a>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-6 sm:p-8 shadow-sm">
            <h1 class="text-2xl font-extrabold text-gray-900">Activer mon portefeuille</h1>
            <p class="mt-2 text-gray-500">
                Ajoutez votre IBAN, votre adresse de facturation et vos informations de contact pour recevoir l'argent de vos ventes.
                Pour votre sécurité, une <span class="font-semibold text-gray-700">fenêtre sécurisée de notre partenaire Stripe</span> s'ouvrira
                pour saisir vos coordonnées bancaires — vos données ne transitent jamais par Swap'Îles.
            </p>

            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-teal-50 px-3 py-1 text-teal-700">🔒 Sécurisé</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-600">💳 IBAN &amp; virements</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-600">⏱️ Environ 3 minutes</span>
            </div>

            <div id="stripe-onboarding"
                 class="mt-6"
                 data-pk="{{ env('STRIPE_KEY') }}"
                 data-session-url="{{ route('stripe.connect.account-session') }}"
                 data-return-url="{{ route('stripe.connect.activated') }}"
                 data-csrf="{{ csrf_token() }}">

                <div id="onboarding-loading" class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-5 text-gray-500">
                    <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-teal-500 border-t-transparent"></span>
                    Chargement de l’activation sécurisée…
                </div>

                <div id="onboarding-mount"></div>
            </div>

            <p class="mt-6 text-xs text-gray-400">
                Swap'Îles ne stocke jamais votre IBAN ni vos documents d'identité : ils sont transmis de façon chiffrée à Stripe,
                notre prestataire de paiement agréé.
            </p>

            <p class="mt-3 text-xs text-gray-400">
                Un souci avec le formulaire ci-dessus ?
                <a href="{{ route('stripe.connect.onboarding') }}" class="font-semibold text-teal-700 hover:text-teal-900">Continuer via la page sécurisée Stripe →</a>
            </p>
        </div>
    </div>

    @vite('resources/js/stripe-connect-onboarding.js')
</section>
@endsection
