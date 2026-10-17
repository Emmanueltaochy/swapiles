@extends('layouts.app')

@section('title', 'Noter l’appli Swap’Îles')
@section('robots', 'noindex, follow')

@section('content')
<section class="bg-gradient-to-br from-teal-900 to-emerald-800 min-h-[60vh] text-white">
    <div class="max-w-xl mx-auto px-4 py-16 text-center">
        <p class="text-5xl" aria-hidden="true">⭐⭐⭐⭐⭐</p>
        <h1 class="mt-5 text-3xl font-extrabold">Merci de noter Swap’Îles</h1>
        <p class="mt-3 text-teal-50/90">Votre avis aide d’autres habitants des îles à découvrir l’appli.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ $ios }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-black px-6 py-3.5 font-bold text-white shadow-lg">🍎 Noter sur l’App Store</a>
            <a href="{{ $android }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-3.5 font-bold text-gray-900 shadow-lg">▶ Noter sur Google Play</a>
        </div>
    </div>
</section>
@endsection
