@extends('layouts.app')

@section('title', 'Réemploi et pouvoir d’achat dans les Outre-mer — Swap’Îles')
@section('meta_description', 'Swap’Îles, plateforme locale de seconde main, de dons et d’échanges dans les Outre-mer : un outil au service des collectivités pour le réemploi, la réduction des déchets et le pouvoir d’achat des familles.')
{{-- Présentation envoyée aux collectivités et services de l'État : lien direct, hors Google. --}}
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $kg = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' t' : number_format((float) $v, 0, ',', ' ') . ' kg';
@endphp

{{-- Ouverture --}}
<section class="relative overflow-hidden bg-teal-950 text-white">
    <img src="{{ asset('images/pro/hero-montagnes.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
    <div class="absolute inset-0 bg-gradient-to-br from-teal-950/95 via-teal-900/85 to-emerald-950/85" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-5xl px-4 py-14 sm:py-20">
        <span class="inline-flex rounded-full bg-white/15 px-4 py-1.5 text-sm font-semibold">🏛️ Collectivités · Mairies · Services de l’État</span>
        <h1 class="mt-5 max-w-3xl text-3xl font-extrabold leading-tight sm:text-5xl">Le réemploi, au service du pouvoir d’achat et de nos îles</h1>
        <p class="mt-4 max-w-2xl text-base text-teal-50/90 sm:text-lg">
            Swap’Îles est une plateforme locale de seconde main, de dons et d’échanges entre habitants de La Réunion, de la Guadeloupe, de la Martinique, de la Guyane et de Mayotte. Un outil simple, déjà adopté par la population, que les collectivités peuvent mobiliser pour réduire les déchets et soutenir les familles.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="#contact" class="rounded-2xl bg-white px-7 py-3.5 text-center font-extrabold text-teal-800 shadow-lg hover:bg-teal-50">Construire un partenariat</a>
            <a href="#pistes" class="rounded-2xl border border-white/40 px-7 py-3.5 text-center font-bold text-white hover:bg-white/10">Voir les pistes d’action</a>
        </div>
    </div>
</section>

{{-- Chiffres en direct --}}
<section class="border-b border-gray-100 bg-white">
    <div class="mx-auto max-w-5xl px-4 py-10">
        <p class="text-center text-sm font-bold uppercase tracking-wide text-gray-500">Swap’Îles aujourd’hui</p>
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(0,1fr))]">
            <div class="rounded-2xl bg-gray-50 p-4 text-center"><p class="text-2xl font-extrabold text-teal-700 sm:text-3xl">{{ $n($membres) }}</p><p class="mt-1 text-xs font-semibold text-gray-500">habitants inscrits</p></div>
            <div class="rounded-2xl bg-gray-50 p-4 text-center"><p class="text-2xl font-extrabold text-teal-700 sm:text-3xl">{{ $n($annonces) }}</p><p class="mt-1 text-xs font-semibold text-gray-500">objets proposés</p></div>
            @if($dons > 0)
                <div class="rounded-2xl bg-gray-50 p-4 text-center"><p class="text-2xl font-extrabold text-teal-700 sm:text-3xl">{{ $n($dons) }}</p><p class="mt-1 text-xs font-semibold text-gray-500">objets à donner gratuitement</p></div>
            @endif
            @if($echanges > 0)
                <div class="rounded-2xl bg-gray-50 p-4 text-center"><p class="text-2xl font-extrabold text-teal-700 sm:text-3xl">{{ $n($echanges) }}</p><p class="mt-1 text-xs font-semibold text-gray-500">objets ouverts à l’échange</p></div>
            @endif
        </div>
        @if($impact['articles'] > 0)
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-emerald-50 p-4 text-center"><p class="text-xl font-extrabold text-emerald-800 sm:text-2xl">{{ $n($impact['articles']) }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">objets réemployés</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4 text-center"><p class="text-xl font-extrabold text-emerald-800 sm:text-2xl">{{ $kg($impact['kg']) }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">de déchets évités</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4 text-center"><p class="text-xl font-extrabold text-emerald-800 sm:text-2xl">{{ $kg($impact['co2']) }}</p><p class="mt-1 text-xs font-semibold text-emerald-700">de CO₂ évités</p></div>
            </div>
            <p class="mt-2 text-center text-xs text-gray-400">Réemploi : estimations moyennes par catégorie d’objet, hypothèse d’un achat neuf évité. Chiffres mis à jour automatiquement.</p>
        @endif
    </div>
</section>

{{-- Les enjeux --}}
<section class="bg-gray-50">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Trois enjeux propres à nos territoires</h2>
        <div class="mt-10 grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">🛒</p>
                <h3 class="mt-3 font-extrabold text-gray-900">La vie chère</h3>
                <p class="mt-1 text-sm text-gray-600">Les écarts de prix avec l’Hexagone pèsent sur les familles. Vêtements d’enfants, puériculture, électroménager, fournitures : la seconde main locale permet de s’équiper pour beaucoup moins cher, sans frais de port.</p>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">♻️</p>
                <h3 class="mt-3 font-extrabold text-gray-900">Des déchets sur une île</h3>
                <p class="mt-1 text-sm text-gray-600">Sur un territoire insulaire, chaque objet jeté doit être traité sur place ou exporté. Faire circuler les objets entre habitants, c’est autant de tonnes qui n’arrivent pas en déchetterie.</p>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">🚢</p>
                <h3 class="mt-3 font-extrabold text-gray-900">La dépendance aux importations</h3>
                <p class="mt-1 text-sm text-gray-600">Ce qui est déjà sur l’île a déjà voyagé. Le réemploi local valorise ce stock, réduit les besoins d’importation et garde la valeur dans l’économie du territoire.</p>
            </div>
        </div>
        <p class="mx-auto mt-6 max-w-3xl text-center text-sm text-gray-500">Le réemploi est un objectif fixé par la loi anti-gaspillage pour une économie circulaire (AGEC) du 10 février 2020, et un axe des programmes locaux de prévention des déchets.</p>
    </div>
</section>

{{-- Ce que Swap'Îles apporte aux habitants --}}
<section class="bg-white">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Ce que Swap’Îles apporte aux habitants</h2>
        <div class="mt-10 grid gap-3 sm:grid-cols-2">
            @foreach([
                ['🎁', 'Donner et recevoir gratuitement', 'Une rubrique de dons : ce qui ne sert plus chez l’un équipe gratuitement une autre famille, près de chez elle.'],
                ['🔄', 'Échanger sans argent', 'Un objet contre un autre : idéal pour les vêtements d’enfants qui grandissent vite, les jouets, les livres.'],
                ['💶', 'Acheter moins cher, près de chez soi', 'Des prix de seconde main, une remise en main propre gratuite, le paiement en espèces ou par carte au choix.'],
                ['🛡️', 'En confiance', 'Profils, avis, signalement et blocage, paiement sécurisé possible, alerte contre les arnaques au paiement.'],
                ['📱', 'Simple pour tous', 'Une appli gratuite sur iPhone et Android ; une photo suffit, l’intelligence artificielle aide à rédiger l’annonce.'],
                ['🏪', 'Avec les commerces de proximité', 'Des points relais dans les commerces du quartier, rémunérés à chaque colis : le réemploi crée aussi de l’activité locale.'],
            ] as [$icone, $titre, $texte])
                <div class="flex gap-4 rounded-2xl bg-gray-50 p-5">
                    <span class="text-2xl" aria-hidden="true">{{ $icone }}</span>
                    <div>
                        <h3 class="font-extrabold text-gray-900">{{ $titre }}</h3>
                        <p class="mt-0.5 text-sm text-gray-600">{{ $texte }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Pistes d'action --}}
<section id="pistes" class="bg-teal-50">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Ce que nous pouvons faire ensemble</h2>
        <div class="mt-10 grid gap-4 lg:grid-cols-2">
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-extrabold text-teal-800">🏛️ Mairies et CCAS</h3>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-700">
                    <li>Opérations « dons » ciblées : rentrée scolaire, puériculture, équipement de logement.</li>
                    <li>Mairies annexes, médiathèques ou maisons de quartier comme points relais ou de dépôt.</li>
                    <li>Ateliers d’accompagnement au numérique : publier sa première annonce, donner un objet.</li>
                    <li>Relais dans le magazine municipal et sur les réseaux de la commune.</li>
                </ul>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-extrabold text-teal-800">♻️ Intercommunalités (prévention des déchets)</h3>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-700">
                    <li>Campagnes communes « Avant de jeter, donnez ou échangez ».</li>
                    <li>Un rapport d’impact sur votre territoire : objets réemployés, tonnes évitées, CO₂ évité.</li>
                    <li>Des indicateurs utilisables dans votre programme local de prévention des déchets.</li>
                    <li>Un lien avec les déchetteries et ressourceries pour orienter les objets réutilisables.</li>
                </ul>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-extrabold text-teal-800">🇫🇷 Région, Département, services de l’État</h3>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-700">
                    <li>Soutien au déploiement dans les territoires où la plateforme démarre (Guyane, Mayotte).</li>
                    <li>Inscription dans les dispositifs économie circulaire et pouvoir d’achat.</li>
                    <li>Un suivi chiffré, territoire par territoire, pour mesurer l’effet d’une action publique.</li>
                </ul>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-extrabold text-teal-800">🏢 Bailleurs sociaux, associations, entreprises (RSE)</h3>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-700">
                    <li>Accompagner les déménagements et les entrées dans un logement avec des objets donnés.</li>
                    <li>Associations : visibilité pour vos collectes et vos dons.</li>
                    <li>Entreprises : devenir point relais ou « parrain du réemploi », avec un rapport d’impact mensuel pour votre reporting RSE.</li>
                </ul>
                <a href="{{ route('partenaires.index') }}" class="mt-3 inline-block text-sm font-bold text-teal-700 underline">Le programme des enseignes partenaires ›</a>
            </div>
        </div>
    </div>
</section>

{{-- Engagements --}}
<section class="bg-white">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Nos engagements</h2>
        <div class="mt-8 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-100 p-5"><p class="font-extrabold text-gray-900">📊 Des données transparentes</p><p class="mt-1 text-sm text-gray-600">Chiffres globaux et anonymes uniquement, conformes au RGPD. Méthode de calcul de l’impact communiquée.</p></div>
            <div class="rounded-2xl border border-gray-100 p-5"><p class="font-extrabold text-gray-900">🆓 Gratuit pour les habitants</p><p class="mt-1 text-sm text-gray-600">Inscription, annonces, dons, échanges et remise en main propre restent gratuits.</p></div>
            <div class="rounded-2xl border border-gray-100 p-5"><p class="font-extrabold text-gray-900">🌴 Une entreprise locale</p><p class="mt-1 text-sm text-gray-600">Conçue et développée à La Réunion, pour les réalités des Outre-mer, avec un interlocuteur sur place.</p></div>
        </div>
    </div>
</section>

{{-- Contact --}}
<section id="contact" class="bg-gradient-to-br from-teal-900 to-emerald-800 text-white">
    <div class="mx-auto max-w-3xl px-4 py-16 text-center">
        <h2 class="text-2xl font-extrabold sm:text-4xl">Parlons de votre territoire</h2>
        <p class="mt-3 text-teal-50/90">Un échange de 30 minutes suffit pour voir ce que Swap’Îles peut apporter à votre commune ou à votre territoire, avec vos propres chiffres.</p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="mailto:contact@swapiles.com?subject={{ rawurlencode('Partenariat réemploi — Swap’Îles') }}" class="w-full rounded-2xl bg-white px-7 py-3.5 font-extrabold text-teal-800 shadow-lg hover:bg-teal-50 sm:w-auto">✉️ contact@swapiles.com</a>
            <a href="tel:+262692323233" class="w-full rounded-2xl border border-white/40 px-7 py-3.5 font-bold text-white hover:bg-white/10 sm:w-auto">📞 0692 32 32 33</a>
        </div>
        <p class="mt-6 text-sm text-teal-100/80">Emmanuel Taochy, fondateur de Swap’Îles</p>
    </div>
</section>
@endsection
