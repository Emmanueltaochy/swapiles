@extends('layouts.app')

@section('title', 'Connexion — Swap\'Îles')

@section('content')
<section class="flex min-h-[80vh] items-center justify-center bg-gray-50 px-4 py-12">
    <div class="w-full max-w-md">

        <div class="mb-6 text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-xl font-bold text-gray-900">
                <span aria-hidden="true">🌴</span> Swap'Îles
            </a>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-gray-900">Connexion</h1>
            <p class="mt-1 text-gray-500">Ravi de te revoir 👋</p>

            @if($errors->any())
                <div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100 @error('email') border-red-300 @enderror">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-semibold text-gray-700">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100 @error('password') border-red-300 @enderror">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        Se souvenir de moi
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">Mot de passe oublié ?</a>
                </div>

                <button class="w-full rounded-xl bg-teal-600 px-5 py-3 font-semibold text-white transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                    Se connecter
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-gray-500">
            Pas encore de compte ?
            <a href="{{ route('register') }}" class="font-semibold text-teal-700 hover:text-teal-900">Créer un compte</a>
        </p>
    </div>
</section>
@endsection
