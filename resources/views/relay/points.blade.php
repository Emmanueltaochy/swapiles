@extends('layouts.app')

@section('title', 'Nos points relais — Swap\'Îles')
@section('meta_description', 'Les commerces points relais Swap\'Îles à ' . $ile . ' : déposez et retirez vos colis près de chez vous. Vous voulez un point relais plus proche ? Faites la demande.')

@section('content')
@php
    $drapeaux = ['La Réunion' => '🇷🇪', 'Guyane' => '🇬🇫', 'Martinique' => '🇲🇶', 'Guadeloupe' => '🇬🇵', 'Mayotte' => '🇾🇹'];
    $membre = auth()->user();
    $aLIle = ['La Réunion' => 'à La Réunion', 'Mayotte' => 'à Mayotte', 'Martinique' => 'en Martinique', 'Guadeloupe' => 'en Guadeloupe', 'Guyane' => 'en Guyane'][$ile] ?? 'sur votre île';
    $champ = 'w-full rounded-xl border px-4 py-3 text-base outline-none focus:ring-2 focus:ring-teal-100';
@endphp

{{-- En-tête --}}
<section class="bg-teal-900 text-white">
    <div class="max-w-5xl mx-auto px-4 py-10 sm:py-14">
        <p class="text-sm font-semibold text-teal-100">🏪 Retrait chez un commerçant</p>
        <h1 class="mt-2 text-3xl sm:text-4xl font-extrabold">Nos points relais</h1>
        <p class="mt-3 max-w-2xl text-teal-50/90">
            Le vendeur dépose l'article chez un commerçant partenaire, vous le récupérez quand vous voulez avec votre code de retrait.
            Simple, sûr, et près de chez vous.
        </p>
        <a href="#demande" class="mt-6 inline-flex w-full sm:w-auto justify-center rounded-2xl bg-white px-6 py-3 font-extrabold text-teal-800 hover:bg-teal-50">
            Demander un point relais près de chez moi
        </a>
    </div>
</section>

{{-- Choix de l'île --}}
<section class="bg-white border-b border-gray-100">
    <nav class="max-w-5xl mx-auto px-4 py-3 flex gap-2 overflow-x-auto" aria-label="Choisir une île">
        @foreach($iles as $nomIle)
            <a href="{{ route('relay.points', ['ile' => $nomIle]) }}"
               @class([
                   'shrink-0 rounded-full px-4 py-2 text-sm font-semibold border transition',
                   'bg-teal-700 text-white border-teal-700' => $nomIle === $ile,
                   'bg-white text-gray-700 border-gray-200 hover:border-teal-400' => $nomIle !== $ile,
               ])
               @if($nomIle === $ile) aria-current="page" @endif>
                {{ $drapeaux[$nomIle] ?? '' }} {{ $nomIle }}
                <span @class(['ml-1', 'text-teal-100' => $nomIle === $ile, 'text-gray-400' => $nomIle !== $ile])>{{ (int) ($parIle[$nomIle] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>
</section>

{{-- Liste --}}
<section class="bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 py-8 sm:py-10">
        @if($points->isEmpty())
            <div class="rounded-3xl border border-dashed border-gray-300 bg-white p-8 text-center">
                <p class="text-4xl">🏝️</p>
                <p class="mt-3 text-lg font-extrabold text-gray-900">Pas encore de point relais {{ $aLIle }}</p>
                <p class="mt-1 text-gray-500">Dites-nous où vous en voudriez un : nous démarchons les commerces les plus demandés en premier.</p>
                <a href="#demande" class="mt-5 inline-flex rounded-2xl bg-teal-700 px-6 py-3 font-bold text-white hover:bg-teal-800">Faire la demande</a>
            </div>
        @else
            <p class="text-sm text-gray-500">{{ $points->count() }} point{{ $points->count() > 1 ? 's' : '' }} relais {{ $aLIle }}</p>

            @foreach($villes as $ville => $pointsVille)
                <h2 class="mt-6 mb-3 text-lg font-extrabold text-gray-900">{{ $ville }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($pointsVille as $point)
                        @php
                            $coords = $point->latitude !== null && $point->longitude !== null ? $point->latitude . ',' . $point->longitude : null;
                            $itineraire = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($coords ?: $point->name . ' ' . $point->fullAddress());
                        @endphp
                        <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm" data-point-relais>
                            <div class="flex items-center gap-3">
                                @if($point->logoUrl())
                                    <img src="{{ $point->logoUrl() }}" alt="{{ $point->partenaire_enseigne }}" class="h-10 w-10 shrink-0 rounded-xl border border-gray-100 object-contain" loading="lazy">
                                @endif
                                <div class="min-w-0">
                                    <p class="font-extrabold text-gray-900">🏪 {{ $point->name }}</p>
                                    @if($point->estPartenaire())
                                        <span class="mt-0.5 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">🤝 Partenaire {{ $point->partenaire_enseigne }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($point->fullAddress())
                                <p class="mt-1 text-sm text-gray-600">{{ $point->fullAddress() }}</p>
                            @endif
                            @if($point->opening_hours)
                                <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-700">Horaires :</span> {{ $point->opening_hours }}</p>
                            @endif
                            <a href="{{ $itineraire }}" target="_blank" rel="noopener"
                               class="mt-4 inline-flex items-center gap-1 rounded-xl border border-teal-600 px-4 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50">
                                📍 Itinéraire
                            </a>
                        </article>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</section>

{{-- Demande d'un point relais plus proche --}}
<section id="demande" class="bg-white scroll-mt-24">
    <div class="max-w-3xl mx-auto px-4 py-10 sm:py-14">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Vous souhaitez un point relais plus proche de chez vous ?</h2>
        <p class="mt-2 text-gray-600">
            Faites la demande : dites-nous où, et si vous connaissez un commerce qui pourrait le devenir (épicerie, tabac, boutique…).
            Nous le contactons et vous prévenons dès qu'il ouvre.
        </p>

        @if(session('relay_request_status'))
            <div class="mt-5 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800" role="status">
                {{ session('relay_request_status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('relay.points.request') }}" class="mt-6 space-y-5" data-form-demande-relais>
            @csrf
            {{-- Honeypot anti-spam (caché aux humains) --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="rq-territoire" class="mb-1 block text-sm font-semibold text-gray-700">Île <span class="text-red-600">*</span></label>
                    <select id="rq-territoire" name="territoire" class="{{ $champ }} border-gray-200 bg-white focus:border-teal-500">
                        @foreach($iles as $nomIle)
                            <option value="{{ $nomIle }}" @selected(old('territoire', $ile) === $nomIle)>{{ $nomIle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rq-city" class="mb-1 block text-sm font-semibold text-gray-700">Ville ou quartier <span class="text-red-600">*</span></label>
                    <input id="rq-city" name="city" value="{{ old('city', $membre?->city) }}" required maxlength="120" placeholder="Ex : Saint-Leu, La Saline…"
                           class="{{ $champ }} @error('city') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                    @error('city')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="rounded-2xl bg-gray-50 p-4 sm:p-5 space-y-4">
                <p class="text-sm font-bold text-gray-800">Un commerce à nous proposer ? <span class="font-normal text-gray-500">(facultatif)</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="rq-business" class="mb-1 block text-sm font-semibold text-gray-700">Nom du commerce</label>
                        <input id="rq-business" name="business_name" value="{{ old('business_name') }}" maxlength="160" placeholder="Ex : Épicerie du Port"
                               class="{{ $champ }} border-gray-200 bg-white focus:border-teal-500">
                    </div>
                    <div>
                        <label for="rq-address" class="mb-1 block text-sm font-semibold text-gray-700">Adresse ou repère</label>
                        <input id="rq-address" name="business_address" value="{{ old('business_address') }}" maxlength="255" placeholder="Ex : face à la mairie"
                               class="{{ $champ }} border-gray-200 bg-white focus:border-teal-500">
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-gray-700">Ce commerce fait-il déjà point relais pour d'autres ?</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\RelayPointRequest::DEJA_RELAIS as $valeur => $libelle)
                            <label class="cursor-pointer">
                                <input type="radio" name="already_relay" value="{{ $valeur }}" class="peer sr-only" @checked(old('already_relay', 'ne_sait_pas') === $valeur)>
                                <span class="inline-block rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 peer-checked:border-teal-600 peer-checked:bg-teal-50 peer-checked:text-teal-800 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-300">{{ $libelle }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-gray-700">Si oui, avec qui ?</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\RelayPointRequest::RESEAUX as $valeur => $libelle)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="networks[]" value="{{ $valeur }}" class="peer sr-only" @checked(in_array($valeur, old('networks', []), true))>
                                <span class="inline-block rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 peer-checked:border-teal-600 peer-checked:bg-teal-50 peer-checked:font-semibold peer-checked:text-teal-800 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-300">{{ $libelle }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="rq-name" class="mb-1 block text-sm font-semibold text-gray-700">Votre prénom <span class="text-red-600">*</span></label>
                    <input id="rq-name" name="name" value="{{ old('name', $membre?->name) }}" required maxlength="120" autocomplete="given-name"
                           class="{{ $champ }} @error('name') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                    @error('name')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="rq-email" class="mb-1 block text-sm font-semibold text-gray-700">E-mail <span class="text-red-600">*</span></label>
                    <input id="rq-email" name="email" type="email" value="{{ old('email', $membre?->email) }}" required maxlength="191" autocomplete="email"
                           class="{{ $champ }} @error('email') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                    @error('email')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="rq-phone" class="mb-1 block text-sm font-semibold text-gray-700">Téléphone <span class="font-normal text-gray-400">(facultatif)</span></label>
                <input id="rq-phone" name="phone" value="{{ old('phone', $membre?->phone) }}" maxlength="40" inputmode="tel" autocomplete="tel"
                       class="{{ $champ }} border-gray-200 focus:border-teal-500">
            </div>

            <div>
                <label for="rq-message" class="mb-1 block text-sm font-semibold text-gray-700">Un détail à ajouter ? <span class="font-normal text-gray-400">(facultatif)</span></label>
                <textarea id="rq-message" name="message" rows="3" maxlength="2000"
                          class="{{ $champ }} resize-none border-gray-200 focus:border-teal-500">{{ old('message') }}</textarea>
            </div>

            <button class="w-full rounded-2xl bg-teal-700 px-6 py-4 font-extrabold text-white hover:bg-teal-800 transition">
                Envoyer ma demande
            </button>
        </form>

        <div class="mt-10 rounded-2xl border border-gray-100 bg-gray-50 p-5 sm:flex sm:items-center sm:justify-between gap-4">
            <div>
                <p class="font-extrabold text-gray-900">Vous êtes commerçant ?</p>
                <p class="text-sm text-gray-600">Gagnez de l'argent à chaque colis et faites venir de nouveaux clients.</p>
            </div>
            <a href="{{ route('relay.partner') }}" class="mt-3 sm:mt-0 inline-flex shrink-0 rounded-xl bg-white border border-teal-600 px-5 py-2.5 text-sm font-bold text-teal-700 hover:bg-teal-50">
                Devenir point relais →
            </a>
        </div>
    </div>
</section>
@endsection
