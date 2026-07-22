@extends('layouts.app')

@section('title', 'Page introuvable — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-[70vh] grid place-items-center px-4 py-16">
    <div class="max-w-md text-center">
        <div class="text-6xl" aria-hidden="true">🌴</div>
        <h1 class="mt-4 text-2xl font-extrabold text-gray-900">Oups, cette page a disparu</h1>
        <p class="mt-2 text-gray-500">
            L'article que vous cherchez n'est plus disponible (il a peut-être été vendu ou retiré),
            ou le lien n'est plus valide.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ route('search') }}" class="w-full sm:w-auto rounded-2xl bg-teal-600 px-6 py-3 font-semibold text-white hover:bg-teal-700 transition">
                🔍 Explorer les annonces
            </a>
            <a href="{{ url('/') }}" class="w-full sm:w-auto rounded-2xl border border-gray-200 bg-white px-6 py-3 font-semibold text-gray-700 hover:bg-gray-50 transition">
                Retour à l'accueil
            </a>
        </div>
    </div>
</section>
@endsection
