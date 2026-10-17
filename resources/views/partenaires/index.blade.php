@extends('layouts.app')

@section('title', 'Enseignes partenaires du réemploi — Swap’Îles')
@section('meta_description', 'Enseignes et magasins des Outre-mer : devenez point relais Swap’Îles. Plus de passages en magasin, une action RSE concrète et mesurée chaque mois, une image locale positive.')

@section('content')
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
@endphp

<section class="bg-gradient-to-br from-teal-900 to-emerald-800 text-white">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-20 text-center">
        <span class="inline-flex rounded-full bg-white/15 px-4 py-1.5 text-sm font-semibold">🤝 Enseignes & grands magasins</span>
        <h1 class="mt-5 text-3xl sm:text-5xl font-extrabold leading-tight">Faites de votre magasin un acteur du réemploi local</h1>
        <p class="mt-4 mx-auto max-w-2xl text-base sm:text-lg text-teal-50/90">
            Devenez point relais Swap’Îles, la marketplace seconde main des Outre-mer. Plus de clients dans vos allées,
            et une action RSE concrète, mesurée chaque mois.
        </p>
        <a href="{{ route('relay.partner') }}#contact" class="mt-8 inline-flex rounded-2xl bg-white px-7 py-3.5 font-extrabold text-teal-800 shadow-lg hover:bg-teal-50">Devenir partenaire</a>
    </div>
</section>

<section class="max-w-5xl mx-auto px-4 py-12">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach([
            ['🚶', 'Du passage en magasin', 'Chaque colis, c’est un vendeur qui le dépose et un acheteur qui vient le retirer : deux visites dans votre magasin.'],
            ['📊', 'Une action RSE mesurée', 'Chaque mois, un rapport d’impact à votre nom : colis, articles réemployés, déchets et CO₂ évités. Prêt pour votre reporting.'],
            ['🌴', 'Une image locale positive', 'Votre enseigne mise en avant dans l’appli comme « point relais partenaire », avec votre logo.'],
        ] as [$emoji, $titre, $texte])
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <p class="text-3xl">{{ $emoji }}</p>
                <h2 class="mt-3 font-extrabold text-gray-900">{{ $titre }}</h2>
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $texte }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-10 rounded-3xl bg-teal-50 p-6 sm:p-8">
        <h2 class="text-xl font-extrabold text-teal-900">Comment ça marche</h2>
        <ol class="mt-4 space-y-3 text-sm text-teal-900">
            <li><strong>1. Un coin à l’accueil suffit</strong> : quelques étagères pour garder les colis, sans investissement.</li>
            <li><strong>2. Le vendeur dépose, l’acheteur retire</strong> avec un code : votre équipe valide en un geste.</li>
            <li><strong>3. Chaque mois</strong>, vous recevez votre rapport d’impact, avec vos chiffres et votre logo.</li>
            <li><strong>4. Pour aller plus loin</strong> : devenez « Parrain du réemploi » de votre île (campagnes communes, visibilité dans l’appli).</li>
        </ol>
    </div>

    @if($impact['articles'] > 0)
        <div class="mt-10 text-center">
            <p class="text-sm font-bold uppercase tracking-wide text-gray-500">Depuis le lancement sur Swap’Îles</p>
            <div class="mt-4 grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-2xl font-extrabold text-gray-900">{{ $n($impact['articles']) }}</p><p class="text-xs text-gray-500">articles réemployés</p></div>
                <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-2xl font-extrabold text-gray-900">{{ $kg($impact['kg']) }}</p><p class="text-xs text-gray-500">de déchets évités</p></div>
                <div class="rounded-2xl bg-white p-4 shadow-sm"><p class="text-2xl font-extrabold text-gray-900">{{ $kg($impact['co2']) }}</p><p class="text-xs text-gray-500">de CO₂ évités</p></div>
            </div>
            <p class="mt-2 text-xs text-gray-400">Estimations moyennes par catégorie d’article.</p>
        </div>
    @endif

    @if($partenaires->isNotEmpty())
        <div class="mt-10">
            <h2 class="text-center text-lg font-extrabold text-gray-900">Ils sont partenaires</h2>
            <div class="mt-4 flex flex-wrap justify-center gap-4">
                @foreach($partenaires->unique('partenaire_enseigne') as $p)
                    <div class="flex items-center gap-2 rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                        @if($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="" class="h-8 w-8 rounded-lg object-contain">@endif
                        <span class="font-bold text-gray-800">{{ $p->partenaire_enseigne }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-12 text-center">
        <a href="{{ route('relay.partner') }}#contact" class="inline-flex rounded-2xl bg-teal-700 px-7 py-3.5 font-extrabold text-white hover:bg-teal-800">Nous contacter</a>
        <p class="mt-2 text-sm text-gray-500">Déjà {{ $n($nbRelais) }} point{{ $nbRelais > 1 ? 's' : '' }} relais dans les îles.</p>
    </div>
</section>
@endsection
