@extends('layouts.app')

@section('title', 'Télécharger l’appli Swap’Îles')
@section('meta_description', 'Téléchargez gratuitement l’appli Swap’Îles sur iPhone et Android : la seconde main des îles dans votre poche.')

@section('content')
<section class="bg-gradient-to-br from-teal-900 to-emerald-800 min-h-[70vh] text-white">
    <div class="max-w-xl mx-auto px-4 py-16 text-center">
        <img src="{{ asset('images/app-icon.png') }}" alt="Swap’Îles" class="mx-auto h-20 w-20 rounded-3xl shadow-lg">
        <h1 class="mt-6 text-3xl font-extrabold">L’appli Swap’Îles</h1>
        <p class="mt-3 text-teal-50/90">Gratuite, plus rapide, avec les notifications de vos dressings préférés et les ventes en direct.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ $ios }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-black px-6 py-3.5 font-bold text-white shadow-lg hover:bg-gray-900">
                <span class="text-xl" aria-hidden="true">🍎</span> App Store (iPhone)
            </a>
            <a href="{{ $android }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-3.5 font-bold text-gray-900 shadow-lg hover:bg-gray-100">
                <span class="text-xl" aria-hidden="true">▶</span> Google Play (Android)
            </a>
        </div>
        <p class="mt-6 text-sm text-teal-100/80">Sur ordinateur ? Ouvrez <strong>swapiles.com/app</strong> sur votre téléphone : vous arriverez directement sur le bon store.</p>
    </div>
</section>
@endsection
