@extends('layouts.app')

@section('title', 'Remise lot — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4">
        <a href="{{ route('account.settings') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Réglages</a>
        <h1 class="text-3xl font-extrabold text-gray-900 mt-3">🎁 Remise lot</h1>
        <p class="text-gray-500 mt-2">Donnez envie d’acheter plusieurs articles de votre dressing en une fois : une remise sur tout le lot, un seul envoi à préparer.</p>

        <form method="POST" action="{{ route('account.remise-lot.update') }}" class="mt-6 space-y-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
            @csrf
            @method('PUT')

            <fieldset>
                <legend class="text-sm font-bold text-gray-700">Remise</legend>
                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                    @foreach(\App\Support\Lots::REMISES as $pct)
                        <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-bold has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 has-[:checked]:text-teal-800">
                            <input type="radio" name="lot_remise_pct" value="{{ $pct }}" class="sr-only" @checked((int) old('lot_remise_pct', $user->lot_remise_pct) === $pct)>
                            {{ $pct === 0 ? 'Aucune' : '−' . $pct . ' %' }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-sm font-bold text-gray-700">À partir de</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach(\App\Support\Lots::MINIMUMS as $min)
                        <label class="cursor-pointer rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-bold has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 has-[:checked]:text-teal-800">
                            <input type="radio" name="lot_remise_min" value="{{ $min }}" class="sr-only" @checked((int) old('lot_remise_min', $user->lot_remise_min ?: 2) === $min)>
                            {{ $min }} articles
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <p class="rounded-2xl bg-gray-50 p-4 text-sm text-gray-600">
                Exemple avec −10 % : 3 articles à 20 + 15 + 25 € = <strong>54 €</strong> au lieu de 60 €.
                La remise s’affiche sur votre profil et vos annonces (« 🎁 −10 % dès 3 articles »). Vous recevez le prix remisé, toujours sans commission.
            </p>

            <button class="w-full rounded-2xl bg-teal-600 px-5 py-3 font-bold text-white hover:bg-teal-700">Enregistrer</button>
        </form>
    </div>
</section>
@endsection
