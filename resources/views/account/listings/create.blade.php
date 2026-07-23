@extends('layouts.app')

@section('title', 'Déposer une annonce — Swap\'Îles')

@section('content')
@php
    $stripeReady = auth()->user()?->stripe_account_id
        && auth()->user()?->stripe_charges_enabled
        && auth()->user()?->stripe_payouts_enabled
        && auth()->user()?->stripe_details_submitted;

    $hasAddress = filled(auth()->user()?->address_line1)
        && filled(auth()->user()?->postal_code)
        && filled(auth()->user()?->city);

    // Par défaut, l'annonce est sur l'île du profil (là où se trouve l'article).
    $profileTerritoire = auth()->user()?->territoire ?: request()->cookie('swapiles_territoire', 'La Réunion');
    $allIslands = ['La Réunion' => '🇷🇪', 'Martinique' => '🇲🇶', 'Guadeloupe' => '🇬🇵', 'Guyane' => '🇬🇫', 'Mayotte' => '🇾🇹'];
    $oldAlso = (array) old('also_territoires', isset($listing) ? ($listing->also_territoires ?? []) : []);

    $territoireCookie = request('territoire', request()->cookie('swapiles_territoire', 'La Réunion'));

    $oldLevel1 = old('category_level1', isset($listing) ? $listing->category_level1 : '');
    $oldLevel2 = old('category_level2', isset($listing) ? $listing->category_level2 : '');
    $oldLevel3 = old('category_level3', isset($listing) ? $listing->category_level3 : '');

    $oldCb = old('payment_cb', (isset($listing) ? $listing->requires_online_payment : false) ? 1 : 0);
    $oldCash = old('payment_cash', 1);
    $oldExchange = old('payment_exchange', (isset($listing) && ($listing->allows_exchange || $listing->listing_type === 'echange-produits')) ? 1 : 0);
    $oldDon = old('payment_don', (isset($listing) ? $listing->listing_type : '') === 'don' ? 1 : 0);
    $oldNegociable = old('payment_negociable', (isset($listing) && ($listing->allows_offers || $listing->listing_type === 'negoce-prix')) ? 1 : 0);

    $inp = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100';
    $lbl = 'mb-1.5 block text-sm font-semibold text-gray-800';
@endphp

<section class="bg-gray-50 min-h-screen py-6 sm:py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6">

        <div class="mb-6">
            <a href="{{ route('account.dashboard') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Retour à mon compte</a>
            <h1 class="mt-3 text-2xl sm:text-3xl font-bold text-gray-900">Déposer une annonce</h1>
            <p class="mt-1 text-gray-500">Quelques étapes simples, et c'est en ligne.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                <strong>Oups, il y a une erreur :</strong><br>{{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.listings.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- Étape 1 : Photos --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">1</span>
                    <h2 class="font-semibold text-gray-900">📷 Photos</h2>
                </div>
                <span class="{{ $lbl }}">Ajoute tes photos</span>

                <input id="images" type="file" name="images[]" multiple accept="image/*" class="hidden">

                <div id="photo-previews" class="hidden grid grid-cols-3 sm:grid-cols-4 gap-3 mb-3"></div>

                <button type="button" id="photo-add-btn"
                        class="flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center transition hover:border-teal-400 hover:bg-teal-50">
                    <span class="text-3xl" aria-hidden="true">📷</span>
                    <span class="font-semibold text-teal-700" id="photo-add-label">Ajouter des photos</span>
                    <span class="text-xs text-gray-500">Tu peux en ajouter plusieurs, une par une ou en lot.</span>
                </button>

                <p class="mt-2 text-xs text-gray-500">Plusieurs photos possibles · max 5 Mo par image. La 1ʳᵉ photo sera la photo principale.</p>
            </div>

            {{-- Étape 2 : Ton article --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">2</span>
                    <h2 class="font-semibold text-gray-900">📝 Ton article</h2>
                </div>

                <div>
                    <label for="title" class="{{ $lbl }}">Titre de l'annonce</label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" required placeholder="Ex : Robe Zara noire taille M" class="{{ $inp }}">
                </div>

                <div>
                    <label for="description" class="{{ $lbl }}">Description</label>
                    <textarea id="description" name="description" rows="5" required placeholder="Décris l'état, la taille, les détails importants…" class="{{ $inp }}">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="category_level1" class="{{ $lbl }}">Catégorie</label>
                        <select name="category_level1" id="category_level1" required class="{{ $inp }}">
                            <option value="">Choisir</option>
                            <option value="femme" @selected($oldLevel1 === 'femme' || $oldLevel1 === 'Femme')>Femme</option>
                            <option value="homme" @selected($oldLevel1 === 'homme' || $oldLevel1 === 'Homme')>Homme</option>
                            <option value="enfant" @selected($oldLevel1 === 'enfant' || $oldLevel1 === 'Enfant')>Enfant</option>
                        </select>
                    </div>
                    <div>
                        <label for="category_level2" class="{{ $lbl }}">Sous-catégorie</label>
                        <select name="category_level2" id="category_level2" required class="{{ $inp }}">
                            <option value="">Choisir d'abord une catégorie</option>
                        </select>
                    </div>
                    <div>
                        <label for="category_level3" class="{{ $lbl }}">Type d'article</label>
                        <select name="category_level3" id="category_level3" required class="{{ $inp }}">
                            <option value="">Choisir d'abord une sous-catégorie</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="etat" class="{{ $lbl }}">État</label>
                        <select id="etat" name="etat" class="{{ $inp }}">
                            <option value="">Non renseigné</option>
                            <option value="Neuf avec étiquette" @selected(old('etat') === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                            <option value="Neuf sans étiquette" @selected(old('etat') === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                            <option value="Très bon état" @selected(old('etat') === 'Très bon état')>Très bon état</option>
                            <option value="Bon état" @selected(old('etat') === 'Bon état')>Bon état</option>
                            <option value="Satisfaisant" @selected(old('etat') === 'Satisfaisant')>Satisfaisant</option>
                        </select>
                    </div>
                    <div>
                        <label for="marque" class="{{ $lbl }}">Marque</label>
                        <input id="marque" type="text" name="marque" value="{{ old('marque') }}" placeholder="Ex : Nike" class="{{ $inp }}">
                    </div>
                    <div>
                        <label for="taille" class="{{ $lbl }}">Taille</label>
                        <input id="taille" type="text" name="taille" value="{{ old('taille') }}" placeholder="Ex : M, 38, 6 ans" class="{{ $inp }}">
                    </div>
                </div>
            </div>

            {{-- Étape 3 : Prix & type --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">3</span>
                    <h2 class="font-semibold text-gray-900">💶 Prix & type</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="listing_type" class="{{ $lbl }}">Type d'annonce</label>
                        <select id="listing_type" name="listing_type" required class="{{ $inp }}">
                            <option value="achat" @selected(old('listing_type') === 'achat')>Vente</option>
                            <option value="negoce-prix" @selected(old('listing_type') === 'negoce-prix')>Vente / prix négociable</option>
                            <option value="don" @selected(old('listing_type') === 'don')>Don</option>
                            <option value="echange-produits" @selected(old('listing_type') === 'echange-produits')>Échange</option>
                            <option value="location-vetements" @selected(old('listing_type') === 'location-vetements')>Location vêtement</option>
                        </select>
                    </div>
                    <div>
                        <label for="price" class="{{ $lbl }}">Prix en €</label>
                        <input id="price" type="number" name="price" value="{{ old('price') }}" min="0" placeholder="Ex : 15" class="{{ $inp }}">
                    </div>
                </div>
                <p class="text-xs text-gray-500">Pour un don, le prix sera automatiquement mis à 0 €.</p>

                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Options supplémentaires <span class="font-normal text-gray-500">(cumulables)</span></p>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="payment_negociable" name="payment_negociable" value="1" class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked((bool) $oldNegociable)>
                        <span>
                            <span class="block font-semibold text-gray-900">💬 Prix négociable</span>
                            <span class="block text-sm text-gray-500">Les acheteurs peuvent vous proposer un prix.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="payment_exchange" name="payment_exchange" value="1" class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked((bool) $oldExchange)>
                        <span>
                            <span class="block font-semibold text-gray-900">🔄 Ouvert à l'échange</span>
                            <span class="block text-sm text-gray-500">Les acheteurs peuvent aussi proposer un échange en main propre.</span>
                        </span>
                    </label>
                    <p class="text-xs text-gray-400">Vous pouvez vendre votre article <strong>et</strong> accepter les offres <strong>et</strong> l'échange en même temps.</p>
                </div>
            </div>

            {{-- Étape 4 : Localisation --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">4</span>
                    <h2 class="font-semibold text-gray-900">📍 Localisation</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="territoire" class="{{ $lbl }}">Votre île <span class="text-gray-400 font-normal">(où se trouve l'article)</span></label>
                        <select id="territoire" name="territoire" required class="{{ $inp }}">
                            @foreach($allIslands as $islLabel => $islFlag)
                                <option value="{{ $islLabel }}" @selected(old('territoire', isset($listing) ? $listing->territoire : $profileTerritoire) === $islLabel)>{{ $islFlag }} {{ \App\Support\Territoires::display($islLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="location_address" class="{{ $lbl }}">Localisation (ville)</label>
                        <input id="location_address" type="text" name="location_address" value="{{ old('location_address') }}" placeholder="Ex : Saint-Pierre, La Réunion" class="{{ $inp }}">
                    </div>
                </div>

                {{-- Vendre aussi sur d'autres îles (nécessite Colissimo) --}}
                <div class="mt-4 rounded-xl border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-800">🌍 Vendre aussi sur d'autres îles <span class="font-normal text-gray-400">(optionnel)</span></p>
                    <p class="mt-1 text-xs text-gray-500">La remise en main propre n'est pas possible entre îles : proposer votre article à une autre île nécessite <strong>Colissimo</strong>.</p>
                    <div id="also-islands" class="mt-3 grid grid-cols-2 gap-2">
                        @foreach($allIslands as $islLabel => $islFlag)
                            <label data-island="{{ $islLabel }}" class="also-island flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 cursor-pointer hover:border-teal-400">
                                <input type="checkbox" name="also_territoires[]" value="{{ $islLabel }}" @checked(in_array($islLabel, $oldAlso, true)) class="also-island-cb rounded text-teal-600 focus:ring-teal-500">
                                {{ $islFlag }} {{ \App\Support\Territoires::display($islLabel) }}
                            </label>
                        @endforeach
                    </div>
                    <div id="also_colissimo_notice" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3" style="display:none;">
                        <p class="text-sm font-semibold text-amber-900">📦 Colissimo requis pour vendre sur d'autres îles</p>
                        <p class="mt-1 text-xs text-amber-800">Cochez <strong>Colissimo</strong> dans les options de livraison ci-dessous (paiement CB sécurisé requis) pour proposer votre article aux autres îles.</p>
                    </div>
                    @error('also_territoires')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Étape 5 : Paiement & livraison --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm space-y-5">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-teal-600 text-sm font-bold text-white">5</span>
                    <h2 class="font-semibold text-gray-900">🔒 Paiement & livraison</h2>
                </div>

                <div>
                    <p class="mb-3 text-sm font-semibold text-gray-800">Moyens acceptés</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl p-4 {{ $stripeReady ? 'border border-teal-100 bg-teal-50' : 'bg-gray-50 opacity-70' }}">
                            <input type="checkbox" id="payment_cb" name="payment_cb" value="1" @disabled(!$stripeReady) class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked((bool) $oldCb)>
                            <span>
                                <span class="block font-semibold text-gray-900">CB sécurisé Swap'Îles</span>
                                <span class="block text-sm text-gray-500">Paiement protégé. Obligatoire pour Colissimo.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-gray-50 p-4">
                            <input type="checkbox" id="payment_cash" name="payment_cash" value="1" class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked((bool) $oldCash)>
                            <span>
                                <span class="block font-semibold text-gray-900">Espèces</span>
                                <span class="block text-sm text-gray-500">Uniquement en main propre.</span>
                            </span>
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">L'échange et le prix négociable se règlent à l'étape 3 « Prix &amp; type ».</p>

                    @if(!$stripeReady)
                        <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-950">🔐 CB sécurisé non activé</p>
                            <p class="mt-1 text-sm text-amber-800">Connecte ton compte bancaire pour activer le paiement CB et Colissimo.</p>
                            <a href="{{ route('account.dashboard') }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-5 py-2.5 text-sm font-semibold text-white">Connecter mon compte bancaire</a>
                        </div>
                    @endif
                </div>

                <div>
                    <p class="mb-3 text-sm font-semibold text-gray-800">Remise / livraison</p>
                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                            <input type="checkbox" id="allows_hand_delivery" name="allows_hand_delivery" value="1" class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked(old('allows_hand_delivery', isset($listing) ? $listing->allows_hand_delivery : true))>
                            <span>
                                <span class="block font-semibold text-emerald-950">🤝 Remise en main propre</span>
                                <span class="block text-sm text-emerald-700">Obligatoire si tu acceptes espèces, échange ou don.</span>
                            </span>
                        </label>

                        <label id="colissimo_box" class="flex cursor-pointer items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <input type="checkbox" id="allows_colissimo" name="allows_colissimo" value="1" @disabled(!$stripeReady) class="mt-1 rounded text-teal-600 focus:ring-teal-500" @checked(old('allows_colissimo', isset($listing) ? $listing->allows_colissimo : false))>
                            <span>
                                <span class="block font-semibold text-blue-950">📦 Colissimo</span>
                                <span class="block text-sm text-blue-700">Avec CB sécurisé uniquement. Frais calculés au paiement.</span>
                            </span>
                        </label>

                        {{-- Colissimo exige l'adresse d'expédition du vendeur --}}
                        @if(!$hasAddress)
                            <div id="colissimo_address_notice" class="rounded-xl border border-amber-200 bg-amber-50 p-4" @unless(session('need_address') || old('allows_colissimo')) style="display:none;" @endunless>
                                <p class="text-sm font-semibold text-amber-900">📍 Renseignez votre adresse pour activer Colissimo</p>
                                <p class="mt-1 text-sm text-amber-800">Votre adresse sert d'adresse d'expédition pour générer le bordereau Colissimo. Elle est obligatoire pour proposer l'envoi.</p>
                                <a href="{{ route('account.addresses.edit') }}" class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700">
                                    Renseigner mon adresse →
                                </a>
                            </div>
                        @endif

                        @error('allows_colissimo')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror

                        <div id="weight_box">
                            <label for="weight_kg" class="{{ $lbl }}">Poids du colis (kg) <span class="text-red-500">*</span></label>
                            <input id="weight_kg" type="number" step="0.01" min="0.01" max="30" name="weight_kg" value="{{ old('weight_kg', isset($listing) ? $listing->weight_kg : '') }}" placeholder="Ex : 0.50" class="{{ $inp }}">
                            <p class="mt-1 text-xs text-gray-500">Obligatoire pour Colissimo — le poids sert à calculer l'affranchissement et à générer le bordereau.</p>
                            @error('weight_kg')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <button class="w-full rounded-xl bg-teal-600 px-6 py-4 font-semibold text-white shadow-sm transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                Publier mon annonce
            </button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tree = {
        femme: {
            'vetements': {
                label: 'Vêtements',
                children: {
                    'robes': 'Robes',
                    'hauts-et-t-shirts': 'Hauts et t-shirts',
                    'jeans-pantalons-shorts': 'Jeans, pantalons, shorts',
                    'jupes': 'Jupes',
                    'ensembles-combi': 'Ensembles / combinaisons',
                    'maillots-de-bain': 'Maillots de bain',
                    'sous-vetements': 'Sous-vêtements'
                }
            },
            'chaussures': {
                label: 'Chaussures',
                children: {
                    'baskets': 'Baskets',
                    'sandales': 'Sandales',
                    'talons': 'Talons',
                    'bottes-bottines': 'Bottes / bottines',
                    'savates': 'Savates'
                }
            },
            'accessoires': {
                label: 'Accessoires',
                children: {
                    'sacs-a-main': 'Sacs à main',
                    'sacs-a-dos': 'Sacs à dos',
                    'bijoux': 'Bijoux',
                    'montres': 'Montres',
                    'ceintures': 'Ceintures',
                    'bananes': 'Bananes'
                }
            }
        },
        homme: {
            'vetements': {
                label: 'Vêtements',
                children: {
                    'hauts-et-t-shirts': 'Hauts et t-shirts',
                    'jeans-pantalons-shorts': 'Jeans, pantalons, shorts',
                    'costumes': 'Costumes',
                    'maillots-de-bain': 'Maillots de bain'
                }
            },
            'chaussures-homme': {
                label: 'Chaussures',
                children: {
                    'baskets': 'Baskets',
                    'savates-sandales': 'Savates / sandales',
                    'bottes': 'Bottes'
                }
            },
            'accessoires': {
                label: 'Accessoires',
                children: {
                    'montres': 'Montres',
                    'ceintures': 'Ceintures',
                    'sacs-a-dos': 'Sacs à dos',
                    'bijoux': 'Bijoux'
                }
            }
        },
        enfant: {
            'vetements-enfants': {
                label: 'Vêtements enfants',
                children: {
                    'robes-de-ceremonie': 'Robes de cérémonie',
                    'hauts-et-t-shirts': 'Hauts et t-shirts',
                    'jeans-pantalons-shorts': 'Jeans, pantalons, shorts',
                    'pyjamas': 'Pyjamas',
                    'bodies': 'Bodies',
                    'costumes-de-carnaval-enfant': 'Costumes de carnaval',
                    'costumes-de-ceremonie-enfant': 'Costumes de cérémonie'
                }
            },
            'chaussures-enfants': {
                label: 'Chaussures enfants',
                children: {
                    'baskets': 'Baskets',
                    'sandales': 'Sandales',
                    'chaussons': 'Chaussons'
                }
            },
            'puericulture': {
                label: 'Puériculture',
                children: {
                    'poussettes': 'Poussettes',
                    'sieges-auto': 'Sièges auto',
                    'lits-bebe': 'Lits bébé',
                    'porte-bebes-echarpes': 'Porte-bébés / écharpes',
                    'chaises-hautes': 'Chaises hautes',
                    'biberons': 'Biberons'
                }
            },
            'jeux-enfant': {
                label: 'Jeux / jouets',
                children: {
                    'jouets-d-eveil': 'Jouets d’éveil',
                    'jeux-educatifs': 'Jeux éducatifs',
                    'puzzles': 'Puzzles',
                    'jeux-de-societe': 'Jeux de société',
                    'jeux-exterieurs-plage-jardin': 'Jeux extérieurs / plage / jardin'
                }
            }
        }
    };

    const oldLevel1 = @json($oldLevel1);
    const oldLevel2 = @json($oldLevel2);
    const oldLevel3 = @json($oldLevel3);

    const l1 = document.getElementById('category_level1');
    const l2 = document.getElementById('category_level2');
    const l3 = document.getElementById('category_level3');

    const cb = document.getElementById('payment_cb');
    const cash = document.getElementById('payment_cash');
    const exchange = document.getElementById('payment_exchange');
    const don = document.getElementById('payment_don');
    const hand = document.getElementById('allows_hand_delivery');
    const coli = document.getElementById('allows_colissimo');
    const coliBox = document.getElementById('colissimo_box');
    const weightBox = document.getElementById('weight_box');
    const weight = document.getElementById('weight_kg');
    const hasAddress = @json((bool) $hasAddress);
    const coliNotice = document.getElementById('colissimo_address_notice');
    const territoireSel = document.getElementById('territoire');
    const alsoLabels = Array.from(document.querySelectorAll('.also-island'));
    const alsoNotice = document.getElementById('also_colissimo_notice');

    function syncAlsoIslands() {
        const primary = territoireSel?.value;
        let anyOther = false;
        alsoLabels.forEach(function (lbl) {
            const island = lbl.getAttribute('data-island');
            const cb = lbl.querySelector('.also-island-cb');
            if (island === primary) {
                lbl.style.display = 'none';
                if (cb) cb.checked = false;
            } else {
                lbl.style.display = '';
                if (cb && cb.checked) anyOther = true;
            }
        });
        if (alsoNotice) {
            alsoNotice.style.display = (anyOther && !coli?.checked) ? 'block' : 'none';
        }
        return anyOther;
    }

    function normalize(v) {
        return (v || '').toString().toLowerCase();
    }

    function fillLevel2(selected = '') {
        const key = normalize(l1.value);
        l2.innerHTML = '<option value="">Choisir</option>';
        l3.innerHTML = '<option value="">Choisir d’abord une sous-catégorie</option>';

        if (!tree[key]) return;

        Object.entries(tree[key]).forEach(([value, item]) => {
            const opt = new Option(item.label, value);
            if (value === selected) opt.selected = true;
            l2.add(opt);
        });

        fillLevel3(oldLevel3);
    }

    function fillLevel3(selected = '') {
        const key1 = normalize(l1.value);
        const key2 = l2.value;
        l3.innerHTML = '<option value="">Choisir</option>';

        if (!tree[key1] || !tree[key1][key2]) return;

        Object.entries(tree[key1][key2].children).forEach(([value, label]) => {
            const opt = new Option(label, value);
            if (value === selected) opt.selected = true;
            l3.add(opt);
        });
    }

    function syncPaymentDelivery() {
        const localOnly = (cash?.checked || exchange?.checked || don?.checked);

        if (localOnly && hand) {
            hand.checked = true;
        }

        if (!cb?.checked && coli) {
            coli.checked = false;
            coli.disabled = true;
            coliBox.classList.add('opacity-50');
        } else if (coli) {
            coli.disabled = false;
            coliBox.classList.remove('opacity-50');
        }

        if (weightBox) {
            weightBox.style.display = coli?.checked ? 'block' : 'none';
        }

        // Le poids n'est requis (et bloquant) que si Colissimo est coché.
        if (weight) {
            weight.required = !!coli?.checked;
        }

        // Colissimo exige une adresse d'expédition : on affiche l'alerte si besoin.
        if (coliNotice) {
            coliNotice.style.display = (coli?.checked && !hasAddress) ? 'block' : 'none';
        }
    }

    l1?.addEventListener('change', () => fillLevel2(''));
    l2?.addEventListener('change', () => fillLevel3(''));

    [cb, cash, exchange, don, coli].forEach(el => el?.addEventListener('change', function () { syncPaymentDelivery(); syncAlsoIslands(); }));
    territoireSel?.addEventListener('change', syncAlsoIslands);
    document.querySelectorAll('.also-island-cb').forEach(el => el.addEventListener('change', syncAlsoIslands));

    // On empêche la publication si Colissimo est coché sans adresse, ou si on vend
    // sur d'autres îles sans Colissimo activé.
    coli?.closest('form')?.addEventListener('submit', function (e) {
        if (coli?.checked && !hasAddress) {
            e.preventDefault();
            if (coliNotice) {
                coliNotice.style.display = 'block';
                coliNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        if (syncAlsoIslands() && !coli?.checked) {
            e.preventDefault();
            if (alsoNotice) {
                alsoNotice.style.display = 'block';
                alsoNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });

    if (oldLevel1) {
        const normalized = normalize(oldLevel1);
        if (['femme','homme','enfant'].includes(normalized)) l1.value = normalized;
    }

    fillLevel2(oldLevel2);
    syncPaymentDelivery();
    syncAlsoIslands();
});
</script>

<script>
(function () {
    const input = document.getElementById('images');
    const addBtn = document.getElementById('photo-add-btn');
    const label = document.getElementById('photo-add-label');
    const previews = document.getElementById('photo-previews');

    if (!input || !addBtn || !previews) return;

    // Repli : si DataTransfer n'est pas supporté, on garde l'input natif visible.
    let supportsDataTransfer = true;
    try { new DataTransfer(); } catch (e) { supportsDataTransfer = false; }

    if (!supportsDataTransfer) {
        input.classList.remove('hidden');
        addBtn.classList.add('hidden');
        return;
    }

    const MAX_BYTES = 5 * 1024 * 1024;
    let files = [];

    addBtn.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        let skipped = 0;
        for (const f of input.files) {
            if (!f.type.startsWith('image/')) continue;
            if (f.size > MAX_BYTES) { skipped++; continue; }
            // évite les doublons (même nom + taille)
            if (files.some(x => x.name === f.name && x.size === f.size)) continue;
            files.push(f);
        }
        sync();
        render();
        if (skipped > 0) {
            alert(skipped + ' photo(s) ignorée(s) : chaque image doit faire moins de 5 Mo.');
        }
    });

    function sync() {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }

    function render() {
        previews.innerHTML = '';
        if (files.length === 0) {
            previews.classList.add('hidden');
            label.textContent = 'Ajouter des photos';
            return;
        }
        previews.classList.remove('hidden');
        label.textContent = 'Ajouter d’autres photos';

        files.forEach((file, i) => {
            const url = URL.createObjectURL(file);
            const cell = document.createElement('div');
            cell.className = 'relative aspect-square overflow-hidden rounded-xl border border-gray-200 bg-gray-100';

            const img = document.createElement('img');
            img.src = url;
            img.className = 'h-full w-full object-cover';
            img.onload = () => URL.revokeObjectURL(url);
            cell.appendChild(img);

            if (i === 0) {
                const badge = document.createElement('span');
                badge.textContent = 'Principale';
                badge.className = 'absolute left-1 top-1 rounded-full bg-teal-600 px-2 py-0.5 text-[10px] font-bold text-white';
                cell.appendChild(badge);
            }

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.dataset.i = i;
            rm.textContent = '✕';
            rm.setAttribute('aria-label', 'Supprimer cette photo');
            rm.className = 'remove-photo absolute right-1 top-1 grid h-6 w-6 place-items-center rounded-full bg-black/60 text-xs font-bold text-white hover:bg-black';
            cell.appendChild(rm);

            previews.appendChild(cell);
        });
    }

    previews.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-photo');
        if (!btn) return;
        files.splice(Number(btn.dataset.i), 1);
        sync();
        render();
    });
})();
</script>
@endsection
