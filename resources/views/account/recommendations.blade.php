@extends('layouts.app')

@section('title', 'Pour vous — Swap\'Îles')

@section('content')
{{-- « Recommandé pour vous » : d'après les articles que le membre a ouverts
     et mis en favori ces dernières semaines (App\Support\Recommandations). --}}
<div class="min-h-screen bg-gray-50 pb-16">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">✨ Pour vous</h1>
        <p class="mt-2 max-w-2xl text-gray-500">
            Des articles choisis d'après ce que vous regardez et ce que vous aimez
            @if($selectedTerritoire) sur {{ $selectedTerritoire }}@endif.
            Plus vous ajoutez de favoris, plus la sélection vous ressemble.
        </p>

        @if($articles->isNotEmpty())
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6" data-recommandations>
                @foreach($articles as $listing)
                    @include('partials.listing-card', ['listing' => $listing])
                @endforeach
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center" data-recommandations-vide>
                <div class="text-5xl" aria-hidden="true">🌴</div>
                <h2 class="mt-3 text-lg font-bold text-gray-900">Votre sélection arrive bientôt</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                    Ouvrez quelques articles qui vous plaisent et ajoutez-les à vos favoris 🤍 :
                    nous vous proposerons de temps en temps des articles dans le même style.
                </p>
                <a href="{{ route('search') }}" class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Parcourir les articles</a>
            </div>
        @endif

        <p class="mt-8 text-sm text-gray-500">
            Vous ne voulez plus de ces suggestions ?
            <a href="{{ route('account.notifications.preferences') }}" class="font-semibold text-teal-700 hover:text-teal-900">Réglez vos notifications</a>.
        </p>
    </div>
</div>
@endsection
