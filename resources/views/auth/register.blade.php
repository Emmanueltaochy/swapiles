@extends('layouts.app')

@section('title', 'Inscription — Swap\'Îles')

@section('content')
<section class="min-h-[70vh] bg-gray-50 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-gray-100 p-6 sm:p-8">
        <h1 class="text-3xl font-extrabold text-gray-900">Créer un compte</h1>
        <p class="text-gray-500 mt-2">Rejoignez la marketplace seconde main des îles.</p>

        @if($errors->any())
            <div class="mt-5 bg-red-50 text-red-700 text-sm rounded-2xl p-4">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nom</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-2xl px-5 py-3 transition">
                Créer mon compte
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Déjà inscrit ?
            <a href="{{ route('login') }}" class="font-bold text-teal-700 hover:text-teal-900">Se connecter</a>
        </p>
    </div>
</section>
@endsection
