@extends('layouts.app')

@section('title', 'Mot de passe oublié — Swap\'Îles')

@section('content')
<section class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
        <h1 class="text-2xl font-extrabold text-gray-900">Mot de passe oublié</h1>
        <p class="text-gray-500 mt-2 text-sm">Entrez votre email pour recevoir un lien de réinitialisation.</p>

        @if(session('status'))
            <div class="mt-4 bg-teal-50 text-teal-800 rounded-2xl p-3 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        @error('email')
            <div class="mt-4 bg-red-50 text-red-700 rounded-2xl p-3 text-sm font-semibold">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf

            <input autocomplete="email" id="email" type="email" name="email" value="{{ old('email') }}" required
                   
 placeholder="Votre adresse e-mail"
        class="w-full mt-2 rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('email') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror">
<p class="mt-2 text-sm text-gray-500">
    Saisissez l’adresse e-mail liée à votre compte Swap’Îles.
</p>

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold rounded-2xl px-5 py-3 transition">
                Recevoir le lien
            </button>
        </form>

        <div class="mt-5 text-center">
            <a href="{{ route('login') }}" class="text-sm font-bold text-teal-700 hover:underline">
                Retour à la connexion
            </a>
        </div>
    </div>
</section>
@endsection
