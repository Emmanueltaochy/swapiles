@extends('layouts.app')

@section('title', 'Live shopping Swap’Îles — pour les professionnels')
@section('meta_description', 'Vendez en direct à toute votre île avec le live shopping Swap’Îles : vidéo en direct, achat en un geste, paiement sécurisé, livraison inter-îles.')
{{-- Page de présentation envoyée aux pros : pas dans les menus, ni dans Google. --}}
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $nb = fn ($n) => number_format((int) $n, 0, ',', ' ');
@endphp

{{-- Hero : les montagnes de La Réunion, voile sombre pour lire le texte --}}
<section class="relative overflow-hidden bg-teal-950 text-white">
    <img src="{{ asset('images/pro/hero-montagnes.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
    <div class="absolute inset-0 bg-gradient-to-br from-teal-950/90 via-teal-900/75 to-emerald-950/80" aria-hidden="true"></div>
    <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:py-20 lg:grid-cols-2">
        <div class="text-center lg:text-left">
            <span class="inline-flex items-center gap-2 rounded-full bg-red-500/90 px-4 py-1.5 text-sm font-extrabold">
                <span class="h-2 w-2 animate-pulse rounded-full bg-white" aria-hidden="true"></span> LIVE SHOPPING
            </span>
            <h1 class="mt-5 text-3xl font-extrabold leading-tight drop-shadow sm:text-5xl">
                Vendez en direct<br class="hidden sm:block"> à toute votre île
            </h1>
            <p class="mt-4 text-base text-teal-50/95 sm:text-lg">
                Présentez vos articles en vidéo depuis votre téléphone. Vos clients posent leurs questions dans le chat
                et achètent en un geste, avec un paiement sécurisé, pendant que vous êtes en direct.
            </p>
            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:justify-start">
                <a href="#contact" class="w-full rounded-2xl bg-white px-7 py-3.5 text-center font-extrabold text-teal-800 shadow-lg hover:bg-teal-50 sm:w-auto">Organiser mon premier live</a>
                <a href="#comment" class="w-full rounded-2xl border border-white/40 px-7 py-3.5 text-center font-bold text-white hover:bg-white/10 sm:w-auto">Comment ça marche</a>
            </div>
            <p class="mt-4 text-sm text-teal-100/85">Un simple téléphone suffit · aucun matériel · aucune installation</p>
        </div>

        {{-- Aperçu d'un live --}}
        <div class="mx-auto w-full max-w-[290px]" aria-hidden="true">
            <div class="relative aspect-[9/19] overflow-hidden rounded-[2.5rem] border-[6px] border-gray-900 bg-gray-900 shadow-2xl ring-1 ring-white/10">
                <img src="{{ asset('images/pro/live-vendeuse.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-[45%_center]">
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/55 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/70 via-black/25 to-transparent"></div>

                <div class="absolute inset-x-0 top-0 flex items-center gap-2 p-3 pt-4">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-teal-600 text-xs font-bold text-white ring-2 ring-white">MB</span>
                    <span class="text-xs font-extrabold text-white drop-shadow">Ma Boutique</span>
                    <span class="ml-auto rounded-md bg-red-600 px-1.5 py-0.5 text-[10px] font-extrabold text-white">LIVE</span>
                    <span class="rounded-md bg-black/45 px-1.5 py-0.5 text-[10px] font-bold text-white">👁 128</span>
                </div>

                <div class="absolute bottom-24 left-3 right-14 space-y-1.5 text-[11px]">
                    <p class="w-fit rounded-xl bg-black/40 px-2 py-1 text-white backdrop-blur-sm"><b>Léa</b> Il reste du 38 ? 😍</p>
                    <p class="w-fit rounded-xl bg-black/40 px-2 py-1 text-white backdrop-blur-sm"><b>Kévin</b> Je prends la bleue !</p>
                    <p class="w-fit rounded-xl bg-emerald-600/95 px-2 py-1 font-bold text-white">🛍️ Kévin vient d’acheter</p>
                </div>

                {{-- Cœurs qui s'envolent, comme les « j'aime » d'un live --}}
                <div class="swp-coeurs pointer-events-none absolute bottom-20 right-2 h-56 w-14">
                    @foreach([['❤️', 0, 0], ['💚', 0.7, 14], ['🩵', 1.3, 4], ['❤️', 1.9, 20], ['💛', 2.6, 8], ['🩷', 3.2, 16], ['❤️', 3.9, 2], ['💚', 4.5, 22]] as [$c, $delai, $x])
                        <span class="swp-coeur" style="left:{{ $x }}px;animation-delay:{{ $delai }}s">{{ $c }}</span>
                    @endforeach
                </div>

                <div class="absolute inset-x-3 bottom-3 flex items-center gap-2 rounded-2xl bg-white p-2 shadow-xl">
                    <img src="{{ asset('images/pro/live-basket.webp') }}" alt="" class="h-11 w-11 shrink-0 rounded-xl object-cover">
                    <span class="min-w-0 flex-1 leading-tight"><span class="block truncate text-[11px] font-bold text-gray-900">Dunk Low bleu ciel</span><span class="text-sm font-extrabold text-gray-900">89 €</span></span>
                    <span class="rounded-xl bg-teal-600 px-3 py-2 text-xs font-extrabold text-white">Acheter</span>
                </div>
            </div>
        </div>
    </div>
    <style>
        .swp-coeur { position: absolute; bottom: 0; font-size: 22px; opacity: 0; animation: swp-coeur 4.8s ease-in infinite; filter: drop-shadow(0 2px 3px rgba(0,0,0,.3)); }
        @keyframes swp-coeur {
            0% { transform: translate(0, 0) scale(.6); opacity: 0; }
            10% { opacity: 1; transform: translate(-4px, -20px) scale(1); }
            40% { transform: translate(8px, -90px) scale(1.05); }
            70% { transform: translate(-8px, -160px) scale(.95); opacity: .85; }
            100% { transform: translate(6px, -220px) scale(.8); opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) { .swp-coeur { animation: none; opacity: 0; } .swp-coeur:first-child { opacity: 1; } }
    </style>
</section>

{{-- Chiffres réels --}}
<section class="border-b border-gray-100 bg-white">
    <div class="mx-auto grid max-w-5xl grid-cols-3 gap-4 px-4 py-8 text-center">
        <div>
            <p class="text-2xl font-extrabold text-teal-700 sm:text-4xl">{{ $nb($membres) }}</p>
            <p class="mt-1 text-xs font-semibold text-gray-500 sm:text-sm">membres</p>
        </div>
        <div>
            <p class="text-2xl font-extrabold text-teal-700 sm:text-4xl">{{ $nb($annonces) }}</p>
            <p class="mt-1 text-xs font-semibold text-gray-500 sm:text-sm">annonces en ligne</p>
        </div>
        <div>
            <p class="text-2xl font-extrabold text-teal-700 sm:text-4xl">5</p>
            <p class="mt-1 text-xs font-semibold text-gray-500 sm:text-sm">territoires</p>
        </div>
    </div>
    <p class="pb-6 text-center text-xs text-gray-400">La Réunion · Guadeloupe · Martinique · Guyane · Mayotte — chiffres à jour en temps réel</p>
</section>

{{-- Pourquoi le live --}}
<section class="bg-gray-50">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Pourquoi vendre en live ?</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-gray-600">Le live shopping s’est imposé en Asie et arrive en Europe : on y vend comme en boutique, mais à des centaines de personnes en même temps. Dans les îles, c’est encore rare.</p>
        <div class="mt-10 grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">🤝</p>
                <h3 class="mt-3 font-extrabold text-gray-900">La confiance de la boutique</h3>
                <p class="mt-1 text-sm text-gray-600">Vos clients voient le produit sous tous les angles, posent leurs questions et ont la réponse tout de suite, comme en magasin.</p>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">⚡</p>
                <h3 class="mt-3 font-extrabold text-gray-900">L’envie d’acheter maintenant</h3>
                <p class="mt-1 text-sm text-gray-600">Pièces uniques, nouveautés dévoilées en direct, quantités limitées : le direct crée l’événement et déclenche l’achat.</p>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                <p class="text-3xl">🏝️</p>
                <h3 class="mt-3 font-extrabold text-gray-900">Toute l’île, et au-delà</h3>
                <p class="mt-1 text-sm text-gray-600">Des clients qui ne passeront jamais devant votre vitrine achètent depuis chez eux, même depuis une autre île grâce à l’envoi Colissimo.</p>
            </div>
        </div>
    </div>
</section>

{{-- Comment ça marche --}}
<section id="comment" class="bg-white">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Comment ça marche</h2>
        <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <li class="rounded-3xl bg-teal-50 p-6">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-teal-600 font-extrabold text-white">1</span>
                <h3 class="mt-4 font-extrabold text-gray-900">Préparez vos articles</h3>
                <p class="mt-1 text-sm text-gray-700">Vous choisissez les articles du live. Certains peuvent rester <b>cachés jusqu’au direct</b> : des exclusivités dévoilées devant vos spectateurs.</p>
            </li>
            <li class="rounded-3xl bg-teal-50 p-6">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-teal-600 font-extrabold text-white">2</span>
                <h3 class="mt-4 font-extrabold text-gray-900">Annoncez-le</h3>
                <p class="mt-1 text-sm text-gray-700">Vos abonnés et les personnes inscrites reçoivent une notification <b>15 minutes avant</b> et <b>au début</b> du live. Partagez le lien sur vos réseaux.</p>
            </li>
            <li class="rounded-3xl bg-teal-50 p-6">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-teal-600 font-extrabold text-white">3</span>
                <h3 class="mt-4 font-extrabold text-gray-900">Lancez le direct</h3>
                <p class="mt-1 text-sm text-gray-700">Depuis votre téléphone, en un bouton. Vous mettez un article <b>en avant à l’écran</b>, vous répondez au chat, les cœurs s’envolent.</p>
            </li>
            <li class="rounded-3xl bg-teal-50 p-6">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-teal-600 font-extrabold text-white">4</span>
                <h3 class="mt-4 font-extrabold text-gray-900">Ils achètent en direct</h3>
                <p class="mt-1 text-sm text-gray-700">Un geste sur « Acheter » : l’article est <b>réservé quelques minutes</b> pour l’acheteur le temps de payer. Tout le monde voit les ventes passer.</p>
            </li>
        </ol>
    </div>
</section>

{{-- Ce qui est inclus --}}
<section class="bg-gray-50">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Tout est inclus</h2>
        <div class="mt-10 grid gap-3 sm:grid-cols-2">
            @foreach([
                ['🔒', 'Paiement sécurisé', 'Carte bancaire via Stripe. L’argent vous est versé une fois l’article reçu par le client : moins de litiges, plus de confiance.'],
                ['📦', 'Livraison ou retrait', 'Remise en main propre, points relais partenaires, ou Colissimo vers les autres îles.'],
                ['💬', 'Chat et réactions en direct', 'Questions, cœurs, ventes affichées en temps réel : l’ambiance d’un vrai événement.'],
                ['🛡️', 'Modération', 'Vous masquez un message ou excluez un spectateur en un geste. Signalement et blocage intégrés.'],
                ['🔔', 'Notifications', 'Vos abonnés sont prévenus avant et au lancement, directement sur leur téléphone.'],
                ['🎬', 'Replay', 'Vous choisissez de garder votre live sur votre profil : vos articles continuent d’être vus après le direct.'],
                ['📱', 'Appli iPhone et Android', 'Vos clients regardent dans l’appli Swap’Îles ou sur le site, sans rien installer de plus.'],
                ['🌴', 'Une plateforme locale', 'Conçue à La Réunion pour les Outre-mer, avec un vrai interlocuteur qui vous accompagne.'],
            ] as [$icone, $titre, $texte])
                <div class="flex gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
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

{{-- Idées de lives --}}
<section class="bg-white">
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h2 class="text-center text-2xl font-extrabold text-gray-900 sm:text-3xl">Des idées de lives qui marchent</h2>
        <div class="mt-8 flex flex-wrap justify-center gap-2">
            @foreach(['👟 Arrivage de baskets', '👗 Vide-dressing de fin de saison', '💄 Démonstration beauté', '📱 Déstockage high-tech', '🧸 Spécial rentrée et fêtes', '⏱️ Ventes flash 30 minutes', '🎁 Pièces uniques dévoilées en direct'] as $idee)
                <span class="rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-800">{{ $idee }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- Contact --}}
<section id="contact" class="bg-gradient-to-br from-teal-900 to-emerald-800 text-white">
    <div class="mx-auto max-w-3xl px-4 py-16 text-center">
        <h2 class="text-2xl font-extrabold sm:text-4xl">On organise votre premier live ensemble ?</h2>
        <p class="mt-3 text-teal-50/90">Nous vous accompagnons pour le premier : préparation des articles, annonce, et un test avant le jour J. Les conditions sont à définir ensemble selon votre activité.</p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="tel:+262692323233" class="w-full rounded-2xl bg-white px-7 py-3.5 font-extrabold text-teal-800 shadow-lg hover:bg-teal-50 sm:w-auto">📞 0692 32 32 33</a>
            <a href="https://wa.me/262692323233?text={{ rawurlencode('Bonjour, je suis intéressé(e) par le live shopping Swap’Îles.') }}" class="w-full rounded-2xl bg-emerald-500 px-7 py-3.5 font-extrabold text-white shadow-lg hover:bg-emerald-400 sm:w-auto">💬 WhatsApp</a>
            <a href="mailto:contact@swapiles.com?subject={{ rawurlencode('Live shopping Swap’Îles') }}" class="w-full rounded-2xl border border-white/40 px-7 py-3.5 font-bold text-white hover:bg-white/10 sm:w-auto">✉️ contact@swapiles.com</a>
        </div>
        <p class="mt-6 text-sm text-teal-100/80">Emmanuel Taochy, fondateur de Swap’Îles</p>
    </div>
</section>
@endsection
