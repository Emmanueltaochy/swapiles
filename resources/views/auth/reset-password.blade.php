@extends('layouts.app')

@section('title', 'Nouveau mot de passe — Swap\'Îles')

@section('content')
<section class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Créer un nouveau mot de passe</h1>

        @if($errors->any())
            <div class="mt-4 bg-red-50 text-red-700 rounded-2xl p-3 text-sm font-semibold">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <input type="email" name="email" value="{{ old('email', request('email')) }}" required
                   placeholder="Votre email"
                   class="w-full rounded-2xl border-gray-200 focus:border-teal-600 focus:ring-teal-600">

            <label class="block mt-5 mb-2 text-sm font-extrabold text-gray-800">
                    Nouveau mot de passe
                </label>
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nouveau mot de passe"
                    class="w-full rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror"
                >

            <label class="block mt-5 mb-2 text-sm font-extrabold text-gray-800">
                    Confirmer le mot de passe
                </label>
                <input
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirmer le mot de passe"
                    class="w-full rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password_confirmation') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror"
                >

            <p class="mt-3 text-sm text-gray-500 leading-relaxed">
                    Utilisez au moins 8 caractères. Les deux mots de passe doivent être identiques.
                </p>

                <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-2xl px-5 py-3 transition">
                Réinitialiser mon mot de passe
            </button>
        </form>
    </div>
</section>
@endsection
