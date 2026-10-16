@extends('layouts.app')

@section('title', 'Mode vacances — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4">
        <div class="mb-6">
            <a href="{{ route('account.settings') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Réglages</a>
            <h1 class="text-3xl font-extrabold text-gray-900 mt-3">🌴 Mode vacances</h1>
            <p class="text-gray-500 mt-2">Vous partez ? Mettez votre dressing en pause : vos annonces sont masquées et personne ne peut les acheter. Elles reviennent toutes seules le jour de votre retour.</p>
        </div>

        @if($enVacances)
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-lg font-extrabold text-amber-950">Votre dressing est en pause</p>
                <p class="mt-1 text-amber-900">De retour le <strong>{{ \App\Support\Vacances::retour($user) }}</strong>.</p>
                @if($user->vacances_message)
                    <p class="mt-2 text-sm italic text-amber-900">« {{ $user->vacances_message }} »</p>
                @endif
                <form method="POST" action="{{ route('account.vacances.terminer') }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <button class="w-full rounded-2xl bg-teal-600 px-5 py-3 font-bold text-white hover:bg-teal-700 sm:w-auto">Je suis de retour : remettre mes annonces en ligne</button>
                </form>
            </div>
        @else
            @if($aExpedier > 0)
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <strong>⚠️ {{ $aExpedier }} vente{{ $aExpedier > 1 ? 's' : '' }} payée{{ $aExpedier > 1 ? 's' : '' }} à expédier.</strong>
                    Envoyez-la{{ $aExpedier > 1 ? 's' : '' }} avant de partir : l’acheteur a déjà payé.
                    <a href="{{ route('account.transactions.index') }}" class="font-bold underline">Voir mes ventes</a>
                </div>
            @endif

            <form method="POST" action="{{ route('account.vacances.activer') }}" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-4">
                @csrf
                <div>
                    <label for="retour" class="text-sm font-bold text-gray-700">Date de retour</label>
                    <input id="retour" type="date" name="retour" required
                           value="{{ old('retour') }}"
                           min="{{ today()->addDay()->toDateString() }}"
                           max="{{ today()->addDays(\App\Support\Vacances::JOURS_MAX)->toDateString() }}"
                           class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
                    <p class="mt-1 text-xs text-gray-500">{{ \App\Support\Vacances::JOURS_MAX }} jours au maximum. Vous pourrez revenir plus tôt.</p>
                    @error('retour')<p class="mt-1 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="message" class="text-sm font-bold text-gray-700">Petit mot pour les acheteurs <span class="font-normal text-gray-400">(facultatif)</span></label>
                    <input id="message" name="message" maxlength="140" value="{{ old('message') }}" placeholder="Ex : Je suis en métropole, je reviens vite !"
                           class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
                </div>

                <ul class="space-y-1 rounded-2xl bg-gray-50 p-4 text-sm text-gray-600">
                    <li>🙈 Vos annonces en ligne sont masquées (recherche, accueil, stories).</li>
                    <li>🛑 Personne ne peut acheter ni faire d’offre.</li>
                    <li>💬 Votre profil affiche votre date de retour, les messages restent possibles.</li>
                    <li>🔁 Le jour du retour, tout repart automatiquement.</li>
                </ul>

                <button class="w-full rounded-2xl bg-amber-500 px-5 py-3 font-bold text-white hover:bg-amber-600">Mettre mon dressing en pause</button>
            </form>
        @endif
    </div>
</section>
@endsection
