@extends('layouts.app')

@section('title', 'Modifier une annonce — Swap\'Îles')

@section('content')
@php
    $stripeReady = auth()->user()?->stripe_account_id
        && auth()->user()?->stripe_charges_enabled
        && auth()->user()?->stripe_payouts_enabled
        && auth()->user()?->stripe_details_submitted;
@endphp

<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('account.dashboard') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Retour à mon compte</a>
            <h1 class="mt-3 text-3xl sm:text-4xl font-extrabold text-gray-900">Modifier l’annonce</h1>
            <p class="text-gray-500 mt-2">Modifiez les informations de votre annonce.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 text-red-700 rounded-2xl p-4 text-sm">
                <strong>Il y a une erreur :</strong><br>{{ $errors->first() }}
            </div>
        @endif

        @if($listing->images->count())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-7 mb-6">
                <h2 class="text-lg font-extrabold text-gray-900 mb-3">Photos actuelles</h2>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                    @foreach($listing->images as $image)
                        <div class="relative group">
                            <img src="{{ $image->url }}" alt="{{ $listing->title }}" class="aspect-square w-full object-cover rounded-2xl bg-gray-100">

                            @if($image->order === 0)
                                <span class="absolute left-2 top-2 bg-teal-700 text-white text-[10px] font-bold px-2 py-1 rounded-full shadow">Principale</span>
                            @else
                                <form method="POST" action="{{ route('account.listings.images.main', ['listing' => $listing, 'image' => $image]) }}" class="absolute left-2 top-2">
                                    @csrf
                                    @method('PATCH')
                                    <button class="bg-white/95 text-gray-800 text-[10px] font-bold px-2 py-1 rounded-full shadow">Mettre en principal</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('account.listings.images.destroy', ['listing' => $listing, 'image' => $image]) }}" onsubmit="return confirm('Supprimer cette photo ?');" class="absolute top-2 right-2">
                                @csrf
                                @method('DELETE')
                                <button class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center shadow text-sm font-bold">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('account.listings.update', $listing) }}" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-7 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Ajouter des photos</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 text-sm">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Titre de l’annonce</label>
                <input type="text" name="title" value="{{ old('title', $listing->title) }}" required placeholder="Ex : Robe Zara noire taille M" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Description</label>
                <textarea name="description" rows="5" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">{{ old('description', $listing->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Type d’annonce</label>
                    <select name="listing_type" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                        <option value="achat" @selected(old('listing_type', $listing->listing_type) === 'achat')>Vente</option>
                        <option value="negoce-prix" @selected(old('listing_type', $listing->listing_type) === 'negoce-prix')>Vente / prix négociable</option>
                        <option value="don" @selected(old('listing_type', $listing->listing_type) === 'don')>Don</option>
                        <option value="echange-produits" @selected(old('listing_type', $listing->listing_type) === 'echange-produits')>Échange</option>
                        <option value="location-vetements" @selected(old('listing_type', $listing->listing_type) === 'location-vetements')>Location vêtement</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Prix en €</label>
                    <input type="number" name="price" value="{{ old('price', $listing->price) }}" min="0" placeholder="Ex : 15" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>
            </div>

            @php
    $oldLevel1 = old('category_level1', isset($listing) ? $listing->category_level1 : '');
    $oldLevel2 = old('category_level2', isset($listing) ? $listing->category_level2 : '');
    $oldLevel3 = old('category_level3', isset($listing) ? $listing->category_level3 : '');

    $oldCb = old('payment_cb', (isset($listing) ? $listing->requires_online_payment : false) ? 1 : 0);
    $oldCash = old('payment_cash', 1);
    $oldExchange = old('payment_exchange', (isset($listing) ? $listing->listing_type : '') === 'echange-produits' ? 1 : 0);
    $oldDon = old('payment_don', (isset($listing) ? $listing->listing_type : '') === 'don' ? 1 : 0);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
        <label class="block text-sm font-bold text-gray-800 mb-2">Catégorie principale</label>
        <select name="category_level1" id="category_level1" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            <option value="">Choisir</option>
            <option value="femme" @selected($oldLevel1 === 'femme' || $oldLevel1 === 'Femme')>Femme</option>
            <option value="homme" @selected($oldLevel1 === 'homme' || $oldLevel1 === 'Homme')>Homme</option>
            <option value="enfant" @selected($oldLevel1 === 'enfant' || $oldLevel1 === 'Enfant')>Enfant</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-800 mb-2">Sous-catégorie</label>
        <select name="category_level2" id="category_level2" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            <option value="">Choisir d’abord une catégorie</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-800 mb-2">Type d’article</label>
        <select name="category_level3" id="category_level3" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            <option value="">Choisir d’abord une sous-catégorie</option>
        </select>
    </div>
</div>

<div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 space-y-4">
    <div>
        <h3 class="text-lg font-extrabold text-gray-900">Moyens acceptés</h3>
        <p class="text-sm text-gray-500 mt-1">
            Vous pouvez proposer plusieurs options. Colissimo est disponible uniquement avec le paiement CB sécurisé.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="flex items-start gap-3 rounded-2xl {{ $stripeReady ? 'bg-teal-50 border border-teal-100' : 'bg-gray-50 opacity-70' }} p-4 cursor-pointer">
            <input type="checkbox" id="payment_cb" name="payment_cb" value="1" @disabled(!$stripeReady)
                   class="mt-1 rounded text-teal-700 focus:ring-teal-600"
                   @checked((bool) $oldCb)>
            <span>
                <span class="block font-bold text-gray-900">CB sécurisé Swap’Îles</span>
                <span class="block text-sm text-gray-500">Paiement protégé. Obligatoire pour Colissimo.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 rounded-2xl bg-gray-50 p-4 cursor-pointer">
            <input type="checkbox" id="payment_cash" name="payment_cash" value="1"
                   class="mt-1 rounded text-teal-700 focus:ring-teal-600"
                   @checked((bool) $oldCash)>
            <span>
                <span class="block font-bold text-gray-900">Espèces</span>
                <span class="block text-sm text-gray-500">Disponible uniquement en remise en main propre.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 rounded-2xl bg-gray-50 p-4 cursor-pointer">
            <input type="checkbox" id="payment_exchange" name="payment_exchange" value="1"
                   class="mt-1 rounded text-teal-700 focus:ring-teal-600"
                   @checked((bool) $oldExchange)>
            <span>
                <span class="block font-bold text-gray-900">Échange</span>
                <span class="block text-sm text-gray-500">Disponible uniquement en remise en main propre.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 rounded-2xl bg-gray-50 p-4 cursor-pointer">
            <input type="checkbox" id="payment_don" name="payment_don" value="1"
                   class="mt-1 rounded text-teal-700 focus:ring-teal-600"
                   @checked((bool) $oldDon)>
            <span>
                <span class="block font-bold text-gray-900">Don</span>
                <span class="block text-sm text-gray-500">Le prix sera mis à 0 € si le type d’annonce est Don.</span>
            </span>
        </label>
    </div>

    @if(!$stripeReady)
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
            <p class="font-extrabold text-amber-950">🔐 CB sécurisé non activé</p>
            <p class="text-sm text-amber-800 mt-1">Connectez votre compte bancaire pour activer le paiement CB et Colissimo.</p>
            <a href="{{ route('account.dashboard') }}" class="inline-flex mt-3 bg-amber-900 text-white font-extrabold rounded-2xl px-5 py-3 text-sm">
                Connecter mon compte bancaire
            </a>
        </div>
    @endif
</div>

<div class="mt-6 rounded-3xl border border-gray-200 bg-white p-5 space-y-4">
    <h3 class="text-lg font-extrabold text-gray-900">Remise / livraison</h3>

    <label class="flex items-start gap-3 rounded-2xl bg-emerald-50 border border-emerald-100 p-4 cursor-pointer">
        <input type="checkbox" id="allows_hand_delivery" name="allows_hand_delivery" value="1"
               class="mt-1 rounded text-teal-700 focus:ring-teal-600"
               @checked(old('allows_hand_delivery', isset($listing) ? $listing->allows_hand_delivery : true))>
        <span>
            <span class="block font-bold text-emerald-950">Remise en main propre</span>
            <span class="block text-sm text-emerald-700">Obligatoire si vous acceptez espèces, échange ou don.</span>
        </span>
    </label>

    <label id="colissimo_box" class="flex items-start gap-3 rounded-2xl bg-blue-50 border border-blue-100 p-4 cursor-pointer">
        <input type="checkbox" id="allows_colissimo" name="allows_colissimo" value="1" @disabled(!$stripeReady)
               class="mt-1 rounded text-teal-700 focus:ring-teal-600"
               @checked(old('allows_colissimo', isset($listing) ? $listing->allows_colissimo : false))>
        <span>
            <span class="block font-bold text-blue-950">Colissimo</span>
            <span class="block text-sm text-blue-700">Disponible uniquement avec CB sécurisé. Frais calculés automatiquement au paiement.</span>
        </span>
    </label>

    <div id="weight_box">
        <label class="block text-sm font-bold text-gray-700 mb-2">Poids du colis en kg</label>
        <input type="number" step="0.01" min="0.01" max="30" name="weight_kg" value="{{ old('weight_kg', isset($listing) ? $listing->weight_kg : '') }}" placeholder="Ex : 0.50" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
        <p class="text-xs text-gray-500 mt-1">Obligatoire si vous proposez Colissimo.</p>
    </div>
</div>

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
    }

    l1?.addEventListener('change', () => fillLevel2(''));
    l2?.addEventListener('change', () => fillLevel3(''));

    [cb, cash, exchange, don, coli].forEach(el => el?.addEventListener('change', syncPaymentDelivery));

    if (oldLevel1) {
        const normalized = normalize(oldLevel1);
        if (['femme','homme','enfant'].includes(normalized)) l1.value = normalized;
    }

    fillLevel2(oldLevel2);
    syncPaymentDelivery();
});
</script>


            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Territoire</label>
                    <select name="territoire" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                        <option value="La Réunion" @selected(old('territoire', $listing->territoire ?? 'La Réunion') === 'La Réunion')>🇷🇪 La Réunion</option>
                        <option value="Martinique" @selected(old('territoire', $listing->territoire) === 'Martinique')>🇲🇶 Martinique</option>
                        <option value="Guadeloupe" @selected(old('territoire', $listing->territoire) === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                        <option value="Guyane" @selected(old('territoire', $listing->territoire) === 'Guyane')>🇬🇫 Guyane</option>
                        <option value="Mayotte" @selected(old('territoire', $listing->territoire) === 'Mayotte')>🇾🇹 Mayotte</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Ville <span class="text-red-500">*</span></label>
                    <input type="text" name="pickup_city" value="{{ old('pickup_city', $listing->pickup_city ?? (auth()->user()->city ?? '')) }}" placeholder="Ex : Saint-Pierre" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                    @error('pickup_city')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Code postal <span class="text-red-500">*</span></label>
                    <input type="text" inputmode="numeric" name="pickup_postal_code" value="{{ old('pickup_postal_code', $listing->pickup_postal_code ?? (auth()->user()->postal_code ?? '')) }}" placeholder="Ex : 97410" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                    @error('pickup_postal_code')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Adresse exacte <span class="font-normal text-gray-400">(recommandé)</span></label>
                    <input type="text" name="location_address" value="{{ old('location_address', $listing->location_address) }}" placeholder="Ex : 12 rue des Filaos" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>
            </div>
            <div class="mt-1 flex items-start gap-2 rounded-xl border border-teal-100 bg-teal-50 px-3 py-2.5 text-xs text-teal-800">
                <span class="text-sm leading-none">🔒</span>
                <span>Votre <strong>adresse exacte n'est JAMAIS affichée</strong> publiquement — seule une <strong>zone approximative</strong> (commune) apparaît sur la carte de l'annonce.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">État</label>
                    <select name="etat" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                        <option value="">Non renseigné</option>
                        <option value="Neuf avec étiquette" @selected(old('etat', $listing->etat) === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                        <option value="Neuf sans étiquette" @selected(old('etat', $listing->etat) === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                        <option value="Très bon état" @selected(old('etat', $listing->etat) === 'Très bon état')>Très bon état</option>
                        <option value="Bon état" @selected(old('etat', $listing->etat) === 'Bon état')>Bon état</option>
                        <option value="Satisfaisant" @selected(old('etat', $listing->etat) === 'Satisfaisant')>Satisfaisant</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Marque</label>
                    <input type="text" name="marque" value="{{ old('marque', $listing->marque) }}" placeholder="Ex : Nike" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Taille</label>
                    <input type="text" name="taille" value="{{ old('taille', $listing->taille) }}" placeholder="Ex : M, 38, 6 ans" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>
            </div>

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                Enregistrer les modifications
            </button>
        </form>
    </div>
</section>
@endsection
