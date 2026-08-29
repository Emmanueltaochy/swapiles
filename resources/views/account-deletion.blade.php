@extends('layouts.app')

@section('title', 'Supprimer mon compte — Swap\'Îles')
@section('meta_description', 'Procédure de suppression de votre compte Swap\'Îles et des données associées.')

@section('content')
<section class="bg-gray-50 min-h-screen">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 sm:py-14">

        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Supprimer mon compte Swap'Îles</h1>
        <p class="mt-3 text-gray-600">
            Vous pouvez demander à tout moment la suppression de votre compte Swap'Îles
            (application éditée par Taochy Consulting) et des données associées.
        </p>

        {{-- Procédure --}}
        <div class="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900">Comment demander la suppression</h2>
            <ol class="mt-4 list-decimal space-y-2 pl-5 text-gray-700">
                <li>Connectez-vous à votre compte Swap'Îles.</li>
                <li>Revenez sur cette page <span class="text-gray-500">(swapiles.com/suppression-compte)</span>.</li>
                <li>Confirmez la suppression avec votre mot de passe dans le formulaire ci-dessous.</li>
            </ol>
            <p class="mt-4 text-sm text-gray-500">
                Vous pouvez aussi nous écrire à
                <a href="mailto:cabinet@taochyconsulting.fr" class="font-semibold text-teal-700 hover:text-teal-900">cabinet@taochyconsulting.fr</a>
                depuis l'adresse e-mail de votre compte pour demander sa suppression.
            </p>
        </div>

        {{-- Ce qui est supprimé / conservé --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-gray-900">🗑️ Données supprimées</h3>
                <ul class="mt-3 space-y-1.5 text-sm text-gray-700">
                    <li>• Profil (nom, e-mail, téléphone, adresse, photo)</li>
                    <li>• Vos annonces et leurs photos</li>
                    <li>• Vos messages et pièces jointes</li>
                    <li>• Vos favoris, abonnements et alertes</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-gray-900">📁 Données conservées</h3>
                <ul class="mt-3 space-y-1.5 text-sm text-gray-700">
                    <li>• Les <strong>enregistrements de transactions</strong> (ventes/achats
                        déjà réalisés) sont conservés de façon <strong>anonymisée</strong>, sans
                        votre identité.</li>
                    <li>• Durée de conservation : jusqu'à <strong>10 ans</strong>, uniquement pour
                        respecter nos obligations légales et comptables.</li>
                </ul>
            </div>
        </div>

        {{-- Statut / formulaire --}}
        @if(session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @auth
            <div class="mt-6 rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-red-700">Supprimer définitivement mon compte</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Cette action est <strong>irréversible</strong>. Pour confirmer, tapez
                    <strong>SUPPRIMER</strong> et saisissez votre mot de passe.
                </p>

                <form method="POST" action="{{ route('account.delete') }}" class="mt-4 space-y-4"
                      onsubmit="return confirm('Confirmer la suppression définitive de votre compte ?');">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="confirmation" class="block text-sm font-semibold text-gray-700">Tapez SUPPRIMER</label>
                        <input id="confirmation" name="confirmation" type="text" autocomplete="off"
                               class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2.5 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100"
                               placeholder="SUPPRIMER">
                        @error('confirmation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700">Votre mot de passe</label>
                        <input id="password" name="password" type="password" autocomplete="current-password"
                               class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-2.5 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <button class="w-full rounded-xl bg-red-600 px-5 py-3 font-semibold text-white transition hover:bg-red-700">
                        Supprimer définitivement mon compte
                    </button>
                </form>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm">
                <p class="text-gray-700">Connectez-vous pour supprimer votre compte.</p>
                <a href="{{ route('login') }}" class="mt-3 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-700">Se connecter</a>
            </div>
        @endauth

    </div>
</section>
@endsection
