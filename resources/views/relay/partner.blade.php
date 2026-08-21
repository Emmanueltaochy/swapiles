@extends('layouts.app')

@section('title', 'Devenir point relais partenaire — Swap\'Îles')
@section('meta_description', 'Devenez point relais Swap\'Îles : gagnez ' . number_format($merchantFee, 0, ',', ' ') . ' € par colis remis, attirez de nouveaux clients en boutique et soutenez l\'économie circulaire locale dans les Outre-mer. Zéro investissement.')

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden bg-teal-900">
    {{-- Photo de fond (littoral de La Réunion) --}}
    <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ asset('images/relay-hero.jpg') }}');"></div>
    {{-- Calque teal semi-transparent : garantit la lisibilité du texte par-dessus la photo --}}
    <div class="absolute inset-0 bg-gradient-to-br from-teal-950/90 via-teal-900/80 to-emerald-900/75"></div>
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
            <a href="#contact"
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

{{-- Bande image + texte : le passage en boutique --}}
<section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center">
        <div class="overflow-hidden rounded-3xl shadow-lg">
            <img src="{{ asset('images/relay-shopkeeper.jpg') }}" alt="Commerçant accueillant un client dans sa boutique"
                 class="h-64 w-full object-cover sm:h-80" loading="lazy">
        </div>
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Vos clients viennent à vous</h2>
            <p class="mt-4 text-gray-600">
                Chaque acheteur qui vient retirer son colis pousse la porte de votre boutique. C'est une occasion
                naturelle de créer du lien et de déclencher une vente supplémentaire — un flux de clients qualifiés,
                sans budget publicitaire.
            </p>
            <ul class="mt-5 space-y-2 text-sm text-gray-700">
                <li class="flex items-start gap-2"><span class="text-teal-600">✓</span> Un revenu simple à chaque colis remis</li>
                <li class="flex items-start gap-2"><span class="text-teal-600">✓</span> Aucune manipulation d'argent, tout est sécurisé en ligne</li>
                <li class="flex items-start gap-2"><span class="text-teal-600">✓</span> Vous soutenez le commerce circulaire de votre île</li>
            </ul>
        </div>
    </div>
</section>

{{-- Comment ça marche --}}
<section id="comment-ca-marche" class="bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16">
        <h2 class="text-center text-2xl sm:text-3xl font-extrabold text-gray-900">Comment ça marche ?</h2>
        <p class="mt-3 text-center mx-auto max-w-2xl text-gray-500">Trois étapes simples, gérées depuis votre espace relais.</p>

        <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="relative overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <img src="{{ asset('images/relay-parcels.jpg') }}" alt="Colis prêts à être déposés" class="h-40 w-full object-cover" loading="lazy">
                <span class="absolute top-3 left-3 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white shadow">1</span>
                <div class="p-6">
                    <h3 class="font-extrabold text-gray-900">📥 Vous réceptionnez</h3>
                    <p class="mt-1.5 text-sm text-gray-500">
                        Le vendeur dépose son colis chez vous. Vous confirmez la réception en un clic dans votre espace relais.
                    </p>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <img src="{{ asset('images/relay-handover.jpg') }}" alt="Remise d'un colis à un client" class="h-40 w-full object-cover" loading="lazy">
                <span class="absolute top-3 left-3 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white shadow">2</span>
                <div class="p-6">
                    <h3 class="font-extrabold text-gray-900">📤 Vous remettez</h3>
                    <p class="mt-1.5 text-sm text-gray-500">
                        L'acheteur vient chercher son colis. Il vous présente un code de retrait, vous le saisissez : c'est tout.
                    </p>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <img src="{{ asset('images/relay-shop.jpg') }}" alt="Boutique de quartier partenaire" class="h-40 w-full object-cover" loading="lazy">
                <span class="absolute top-3 left-3 grid h-8 w-8 place-items-center rounded-full bg-teal-700 text-sm font-extrabold text-white shadow">3</span>
                <div class="p-6">
                    <h3 class="font-extrabold text-gray-900">💶 Vous êtes payé</h3>
                    <p class="mt-1.5 text-sm text-gray-500">
                        {{ number_format($merchantFee, 0, ',', ' ') }} € sont crédités sur votre solde à chaque colis remis. Vous suivez tout depuis votre espace.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ courte --}}
<section class="bg-white">
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

{{-- Formulaire de contact --}}
<section id="contact" class="bg-gray-50">
    <div class="max-w-5xl mx-auto px-4 py-14 sm:py-16">
        <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xl grid grid-cols-1 md:grid-cols-2">
            {{-- Visuel --}}
            <div class="relative hidden md:block">
                <img src="{{ asset('images/relay-handover.jpg') }}" alt="Commerçant remettant un colis à un client"
                     class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-teal-950/70 to-teal-900/20"></div>
                <div class="absolute bottom-0 p-8 text-white">
                    <p class="text-2xl font-extrabold">Rejoignez le réseau Swap'Îles</p>
                    <p class="mt-2 text-teal-50/90">Gratuit, sans engagement. On vous accompagne pour vos premiers colis.</p>
                </div>
            </div>

            {{-- Formulaire --}}
            <div class="p-6 sm:p-8">
                <h2 class="text-2xl font-extrabold text-gray-900">Devenir point relais</h2>
                <p class="mt-1 text-sm text-gray-500">Laissez-nous vos coordonnées, nous vous recontactons très vite.</p>

                @if(session('relay_contact_status'))
                    <div class="mt-4 rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-sm font-semibold text-emerald-800">
                        {{ session('relay_contact_status') }}
                    </div>
                @endif
                @error('relay_contact')
                    <div class="mt-4 rounded-2xl bg-red-50 border border-red-100 p-4 text-sm font-semibold text-red-700">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('relay.partner.contact') }}" class="mt-5 space-y-4">
                    @csrf
                    {{-- Honeypot anti-spam (caché aux humains) --}}
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                    <div>
                        <label for="business" class="mb-1 block text-sm font-semibold text-gray-700">Nom du commerce <span class="text-red-600">*</span></label>
                        <input id="business" name="business" value="{{ old('business') }}" required maxlength="160"
                               class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-100 @error('business') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                        @error('business')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="mb-1 block text-sm font-semibold text-gray-700">Votre nom <span class="text-red-600">*</span></label>
                            <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                                   class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-100 @error('name') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                            @error('name')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="city" class="mb-1 block text-sm font-semibold text-gray-700">Ville <span class="text-red-600">*</span></label>
                            <input id="city" name="city" value="{{ old('city') }}" required maxlength="120"
                                   class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-100 @error('city') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                            @error('city')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="mb-1 block text-sm font-semibold text-gray-700">Téléphone <span class="text-red-600">*</span></label>
                            <input id="phone" name="phone" value="{{ old('phone') }}" required maxlength="40" inputmode="tel" autocomplete="tel"
                                   class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-100 @error('phone') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                            @error('phone')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">E-mail <span class="text-red-600">*</span></label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="191" autocomplete="email"
                                   class="w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-100 @error('email') border-red-500 @else border-gray-200 focus:border-teal-500 @enderror">
                            @error('email')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="hours" class="mb-1 block text-sm font-semibold text-gray-700">Horaires d'ouverture <span class="font-normal text-gray-400">(facultatif)</span></label>
                        <input id="hours" name="hours" value="{{ old('hours') }}" maxlength="160" placeholder="Ex : Lun–Sam 9h–18h"
                               class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                    </div>

                    <div>
                        <label for="message" class="mb-1 block text-sm font-semibold text-gray-700">Message <span class="font-normal text-gray-400">(facultatif)</span></label>
                        <textarea id="message" name="message" rows="3" maxlength="2000"
                                  class="w-full resize-none rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">{{ old('message') }}</textarea>
                    </div>

                    <button class="w-full rounded-2xl bg-teal-700 px-6 py-3.5 font-extrabold text-white hover:bg-teal-800 transition">
                        Envoyer ma demande
                    </button>
                    <p class="text-center text-xs text-gray-400">Ou écrivez-nous à <a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700 hover:underline">contact@swapiles.com</a></p>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection
