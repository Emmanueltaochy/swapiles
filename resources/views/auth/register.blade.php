@extends('layouts.app')

@section('title', 'Inscription — Swap\'Îles')

@section('content')
<section class="flex min-h-[80vh] items-center justify-center bg-gray-50 px-4 py-12">
    <div class="w-full max-w-md">

        <div class="mb-6 text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-xl font-bold text-gray-900">
                <span aria-hidden="true">🌴</span> Swap'Îles
            </a>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold text-gray-900">Créer un compte</h1>
            <p class="mt-1 text-gray-500">La marketplace seconde main des îles.</p>

            <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">✅ Gratuit</span>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">⚡ Vends en 2 minutes</span>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">🛡️ Paiement sécurisé</span>
            </div>

            @if($errors->any())
                <div class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="name" class="mb-1 block text-sm font-semibold text-gray-700">Nom</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100 @error('name') border-red-300 @enderror">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100 @error('email') border-red-300 @enderror">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-semibold text-gray-700">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100 @error('password') border-red-300 @enderror">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-semibold text-gray-700">Confirmer le mot de passe</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                </div>

                @php
                    $commentConnuOptions = [
                        'Réseaux sociaux (Instagram, TikTok, Facebook…)',
                        'Recherche Google',
                        'Bouche à oreille (ami, famille)',
                        'Publicité en ligne',
                        'Affiche / flyer / événement local',
                        'Presse / radio',
                        'Autre',
                    ];
                @endphp
                <div>
                    <label for="comment_connu" class="mb-1 block text-sm font-semibold text-gray-700">Comment nous avez-vous connu ?</label>
                    <select id="comment_connu" name="comment_connu"
                            onchange="document.getElementById('comment_connu_autre_wrap').style.display = (this.value === 'Autre' ? 'block' : 'none');"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                        <option value="">— Choisir (facultatif) —</option>
                        @foreach($commentConnuOptions as $opt)
                            <option value="{{ $opt }}" @selected(old('comment_connu') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="comment_connu_autre_wrap" style="display: {{ old('comment_connu') === 'Autre' ? 'block' : 'none' }};">
                    <label for="comment_connu_autre" class="mb-1 block text-sm font-semibold text-gray-700">Précisez</label>
                    <input id="comment_connu_autre" type="text" name="comment_connu_autre" value="{{ old('comment_connu_autre') }}" maxlength="255"
                           placeholder="Dites-nous comment"
                           class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                </div>

                <button class="w-full rounded-xl bg-teal-600 px-5 py-3 font-semibold text-white transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                    Créer mon compte
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-gray-500">
            Déjà inscrit ?
            <a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:text-teal-900">Se connecter</a>
        </p>
    </div>
</section>
@endsection
