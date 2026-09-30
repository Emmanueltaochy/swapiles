@extends('layouts.app')

@section('title', 'Préférences de notification — Swap\'Îles')

@section('content')
{{-- Ces reglages vivaient tout en bas de « Modifier mon profil », apres le
     mot de passe et les points relais. Qui voulait simplement arreter de
     recevoir un type de message ne les trouvait pas, et le seul recours
     etait de supprimer son compte. --}}
<div class="min-h-screen bg-gray-50 pb-16">
    <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-8">

        <a href="{{ route('account.settings') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Réglages</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">🔔 Préférences de notification</h1>
        <p class="mt-2 text-gray-500">
            Choisissez ce que vous souhaitez recevoir. Les messages liés à vos
            ventes et à vos achats vous sont toujours envoyés.
        </p>

        @if(session('status'))
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.profile.update') }}"
              class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            @csrf
            @method('PUT')
            {{-- Le formulaire de profil est partage : « name » est obligatoire,
                 et ce drapeau dit au controleur de ne toucher qu'aux reglages
                 de notification. --}}
            <input type="hidden" name="name" value="{{ $user->name }}">
            <input type="hidden" name="notification_prefs_submitted" value="1">

            @php $prefs = $user->notification_prefs ?? []; @endphp

            <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-gray-500">
                <span>Type d'alerte</span>
                <span class="flex shrink-0 gap-4">
                    <span class="w-14 text-center">Mobile</span>
                    <span class="w-14 text-center">E-mail</span>
                </span>
            </div>

            @foreach(\App\Support\NotificationPreferences::CATEGORIES as $cle => $categorie)
                <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $categorie['label'] }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">{{ $categorie['description'] }}</p>
                    </div>
                    <div class="flex shrink-0 gap-4">
                        <label class="flex w-14 justify-center" title="Notification sur mobile — {{ $categorie['label'] }}">
                            <span class="sr-only">Notification mobile : {{ $categorie['label'] }}</span>
                            <input type="checkbox" name="notification_prefs[{{ $cle }}][push]" value="1"
                                   @checked($prefs[$cle]['push'] ?? true)
                                   class="h-6 w-6 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        </label>
                        <label class="flex w-14 justify-center" title="E-mail — {{ $categorie['label'] }}">
                            <span class="sr-only">E-mail : {{ $categorie['label'] }}</span>
                            <input type="checkbox" name="notification_prefs[{{ $cle }}][email]" value="1"
                                   @checked($prefs[$cle]['email'] ?? true)
                                   class="h-6 w-6 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        </label>
                    </div>
                </div>
            @endforeach

            <div class="border-t border-gray-100 p-4">
                <button class="w-full rounded-2xl bg-teal-700 px-6 py-4 font-bold text-white transition hover:bg-teal-800">
                    Enregistrer mes préférences
                </button>
            </div>
        </form>

        <p class="mt-4 px-1 text-sm text-gray-500">
            Aucune notification mobile ne part entre 22 h et 8 h, heure de votre île :
            elle attend le matin.
        </p>

    </div>
</div>
@endsection
