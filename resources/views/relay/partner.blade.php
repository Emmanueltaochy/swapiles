@extends('layouts.app')

@section('title', 'Devenir point relais partenaire — Swap\'Îles')
@section('meta_description', 'Devenez point relais Swap\'Îles : gagnez ' . number_format($merchantFee, 0, ',', ' ') . ' € par colis remis, attirez de nouveaux clients en boutique et soutenez l\'économie circulaire locale dans les Outre-mer. Zéro investissement.')

@php
    $mailto = 'mailto:contact@swapiles.com?subject=' . rawurlencode('Devenir point relais partenaire Swap\'Îles')
        . '&body=' . rawurlencode("Bonjour,\n\nJe gère un commerce et je souhaite devenir point relais Swap'Îles.\n\nNom du commerce :\nVille :\nHoraires d'ouverture :\nTéléphone :\n\nMerci !");
@endphp

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden bg-teal-900">
    <div class="absolute inset-0 bg-gradient-to-br from-teal-950 via-teal-800 to-emerald-600"></div>
    <div class="relative max-w-5xl mx-auto px-4 py-16 sm:py-20 text-center text-white">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm font-semibold backdrop-blur">
            🏪 Programme partenaire
        </span>
        <h1 class="mt-5 text-3xl sm:text-5xl font-extrabold leading-tight">
            Devenez point relais Swap'Îles
        </h1>
        <p class="mt-4 mx-auto max-w-2xl text-base sm:text-lg text-teal-50/90">
            Faites entrer de nouveaux clients dans votre boutique, gagnez de l'argent à chaque colis,
            et soutenez l'économie circulaire de votre île — le tout sans rien investir.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ $mailto }}"
               class="w-full sm:w-auto rounded-2xl bg-white px-7 py-3.5 font-extrabold text-teal-800 shadow-lg hover:bg-teal-50 transition">
                Devenir partenaire
            </a>
            <a href="#comment-ca-marche"
               class="w-full sm:w-auto rounded-2xl border border-white/40 px-7 py-3.5 font-bold text-white hover:bg-white/10 transition">
                Comment ça marche
            </a>
        </div>
        <p class="mt-4 text-sm text-teal-100/80">Gratuit · sans engagement · vous ne manipulez jamais d'argent</p>
    </div>
</section>

{{-- Statistiques (affichées seulement si significatives) --}}
@if($showStats)
    <section class="bg-white border-b border-gray-100">
        <div class="max-w-5xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            <div>
                <p class="text-4xl font-extrabold text-teal-700">{{ number_format($parcelsDelivered, 0, ',', ' ') }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-500">colis remis par nos partenaires</p>
            </div>
            <div>
                <p class="text-4xl font-extrabold text-teal-700">{{ number_format($merchantEarned, 0, ',', ' ') }} €</p>
                <p class="mt-1 text-sm font-semibold text-gray-500">reversés aux commerçants</p>
            </div>
            <div>
                <p class="text-4xl font-extrabold text-teal-700">{{ number_format($activeRelays, 0, ',', ' ') }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-500">points relais partenaires</p>
            </div>
        </div>
    </section>
@endif

{{-- Avantages --}}
<section class="bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16">
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-gray-900">Pourquoi devenir point relais ?</h2>
        <p class="mt-3 text-center mx-auto max-w-2xl text-gray-500">
            Un service simple qui travaille pour votre commerce, dès le premier colis.
        </p>

        <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">💶</div>
                <h3 class="mt-4 font-extrabold text-gray-900">{{ number_format($merchantFee, 0, ',', ' ') }} € par colis</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Vous gagnez {{ number_format($merchantFee, 0, ',', ' ') }} € pour chaque colis remis à un acheteur. Un revenu simple qui s'ajoute à votre activité.
                </p>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">🚶</div>
                <h3 class="mt-4 font-extrabold text-gray-900">Du passage en boutique</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Chaque retrait de colis fait entrer un client chez vous — l'occasion d'une vente en plus. Le trafic, gratuitement.
                </p>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">🌍</div>
                <h3 class="mt-4 font-extrabold text-gray-900">Économie circulaire locale</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Vous participez à donner une seconde vie aux objets de votre île et à limiter les déchets. Un engagement qui valorise votre enseigne.
                </p>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">🔒</div>
                <h3 class="mt-4 font-extrabold text-gray-900">Zéro gestion d'argent</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Le paiement est sécurisé en ligne : vous gardez le colis et le remettez contre un code. Vous ne manipulez jamais d'espèces.
                </p>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">🎯</div>
                <h3 class="mt-4 font-extrabold text-gray-900">Zéro investissement</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Pas de matériel, pas d'abonnement, pas de stock. Un simple coin pour stocker quelques colis suffit pour démarrer.
                </p>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-50 text-2xl">🏝️</div>
                <h3 class="mt-4 font-extrabold text-gray-900">Un service pensé pour l'île</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Livraison locale rapide et sans transporteur : vous devenez un maillon clé du commerce de seconde main près de chez vous.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Comment ça marche --}}
<section id="comment-ca-marche" class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16">
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-gray-900">Comment ça marche ?</h2>
        <p class="mt-3 text-center mx-auto max-w-2xl text-gray-500">Trois étapes simples, gérées depuis votre espace relais.</p>

        <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="relative rounded-3xl border border-gray-100 bg-gray-50 p-6">
                <span class="absolute -top-3 left-6 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white">1</span>
                <h3 class="mt-3 font-extrabold text-gray-900">📥 Vous réceptionnez</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    Le vendeur dépose son colis chez vous. Vous confirmez la réception en un clic dans votre espace relais.
                </p>
            </div>
            <div class="relative rounded-3xl border border-gray-100 bg-gray-50 p-6">
                <span class="absolute -top-3 left-6 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white">2</span>
                <h3 class="mt-3 font-extrabold text-gray-900">📤 Vous remettez</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    L'acheteur vient chercher son colis. Il vous présente un code de retrait, vous le saisissez : c'est tout.
                </p>
            </div>
            <div class="relative rounded-3xl border border-gray-100 bg-gray-50 p-6">
                <span class="absolute -top-3 left-6 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white">3</span>
                <h3 class="mt-3 font-extrabold text-gray-900">💶 Vous êtes payé</h3>
                <p class="mt-1.5 text-sm text-gray-500">
                    {{ number_format($merchantFee, 0, ',', ' ') }} € sont crédités sur votre solde à chaque colis remis. Vous suivez tout depuis votre espace.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- FAQ courte --}}
<section class="bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 py-14 sm:py-16">
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-gray-900">Questions fréquentes</h2>
        <div class="mt-8 space-y-3">
            @foreach([
                ['Est-ce que ça me coûte quelque chose ?', 'Non. Devenir point relais est 100 % gratuit : aucun matériel, aucun abonnement, aucune commission prélevée sur vous. Vous êtes rémunéré, pas l\'inverse.'],
                ['Dois-je encaisser de l\'argent ?', 'Jamais. L\'acheteur paie en ligne, de façon sécurisée, avant même le dépôt. Vous vous contentez de garder le colis et de le remettre contre un code de retrait.'],
                ['De combien de place ai-je besoin ?', 'Très peu : un simple coin pour stocker quelques colis en attente de retrait suffit pour démarrer le pilote.'],
                ['Comment suis-je payé ?', 'Vous gagnez ' . number_format($merchantFee, 0, ',', ' ') . ' € par colis remis. Le montant s\'accumule sur votre solde, visible à tout moment dans votre espace relais.'],
                ['Comment je commence ?', 'Créez un compte Swap\'Îles, puis contactez-nous : nous activons votre point relais et vous accompagnons pour vos premiers colis.'],
            ] as [$q, $a])
                <details class="group rounded-2xl border border-gray-100 bg-white p-5">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 font-bold text-gray-900">
                        {{ $q }}
                        <span class="shrink-0 text-teal-700 transition group-open:rotate-45">＋</span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA final --}}
<section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16">
        <div class="rounded-3xl bg-gradient-to-br from-teal-800 to-emerald-600 px-6 py-12 sm:px-12 text-center text-white shadow-xl">
            <h2 class="text-2xl sm:text-3xl font-extrabold">Prêt à rejoindre le réseau ?</h2>
            <p class="mt-3 mx-auto max-w-xl text-teal-50/90">
                Dites-nous en deux mots où se trouve votre commerce, et nous vous recontactons pour activer votre point relais.
            </p>
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ $mailto }}"
                   class="w-full sm:w-auto rounded-2xl bg-white px-7 py-3.5 font-extrabold text-teal-800 shadow-lg hover:bg-teal-50 transition">
                    Devenir partenaire
                </a>
                @guest
                    <a href="{{ route('register') }}"
                       class="w-full sm:w-auto rounded-2xl border border-white/40 px-7 py-3.5 font-bold text-white hover:bg-white/10 transition">
                        Créer mon compte
                    </a>
                @endguest
            </div>
            <p class="mt-4 text-sm text-teal-100/80">Ou écrivez-nous à <a href="mailto:contact@swapiles.com" class="font-semibold underline">contact@swapiles.com</a></p>
        </div>
    </div>
</section>

@endsection
