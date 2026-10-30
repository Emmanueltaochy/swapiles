@extends('layouts.app')

@section('title', 'Désinscription — Swap\'Îles')

@section('content')
<section class="max-w-lg mx-auto px-4 py-16 text-center">
    <div class="text-5xl">✅</div>
    <h1 class="mt-4 text-2xl font-extrabold text-gray-900">C’est noté</h1>
    <p class="mt-3 text-gray-600">{{ $nom ? '« ' . $nom . ' » ne' : 'Vous ne' }} recevra plus de message de prospection de Swap’Îles. Merci et belle journée !</p>
    <a href="{{ route('home') }}" class="mt-8 inline-block rounded-2xl bg-teal-700 px-5 py-3 font-bold text-white">Découvrir Swap’Îles</a>
</section>
@endsection
