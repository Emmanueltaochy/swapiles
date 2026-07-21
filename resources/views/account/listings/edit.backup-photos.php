@extends('layouts.app')

@section('title', 'Modifier une annonce — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="{{ route('account.dashboard') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">
                ← Retour à mon compte
            </a>

            <h1 class="mt-3 text-3xl sm:text-4xl font-extrabold text-gray-900">
                Modifier l’annonce
            </h1>

            <p class="text-gray-500 mt-2">
                Modifiez les informations de votre annonce.
            </p>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 text-red-700 rounded-2xl p-4 text-sm">
                <strong>Il y a une erreur :</strong><br>
                {{ $errors->first() }}
            </div>
        @endif

        
        @if($listing->images->count())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-7 mb-6">
                <h2 class="text-lg font-extrabold text-gray-900 mb-3">Photos actuelles</h2>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                    @foreach($listing->images as $image)
                        <img src="{{ $image->url }}" alt="{{ $listing->title }}" class="aspect-square w-full object-cover rounded-2xl bg-gray-100">
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-3">Pour l’instant, tu peux ajouter des photos. La suppression photo par photo viendra dans une prochaine étape.</p>
            </div>
        @endif

<form method="POST" action="{{ route('account.listings.update', $listing) }}" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 sm:p-7 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Photos</label>
                <input type="file" name="images[]" multiple accept="image/*" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 text-sm">
                <p class="text-xs text-gray-500 mt-2">Tu peux ajouter plusieurs photos. Max 5 Mo par image.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Titre de l’annonce</label>
                <input type="text" name="title" value="{{ old('title', $listing->title) }}" required placeholder="Ex : Robe Zara noire taille M" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Description</label>
                <textarea name="description" rows="5" required placeholder="Décris l’état, la taille, les détails importants..." class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">{{ old('description', $listing->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Type d’annonce</label>
                    <select name="listing_type" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                        <option value="achat" @selected(old('listing_type', $listing->listing_type) === 'achat')>🔒 Vente avec paiement protégé</option>
                        <option value="negoce-prix" @selected(old('listing_type', $listing->listing_type) === 'negoce-prix')>💵 Vente / prix négociable</option>
                        <option value="don" @selected(old('listing_type', $listing->listing_type) === 'don')>🎁 Don</option>
                        <option value="echange-produits" @selected(old('listing_type', $listing->listing_type) === 'echange-produits')>🔄 Échange</option>
                        <option value="location-vetements" @selected(old('listing_type', $listing->listing_type) === 'location-vetements')>👗 Location vêtement</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Prix en €</label>
                    <input type="number" name="price" value="{{ old('price', $listing->price) }}" min="0" placeholder="Ex : 15" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                    <p class="text-xs text-gray-500 mt-2">Pour un don, le prix sera automatiquement à 0 €.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Catégorie</label>
                    <select name="category_level1" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                        <option value="">Choisir</option>
                        <option value="Femme" @selected(old('category_level1', $listing->category_level1) === 'Femme')>Femme</option>
                        <option value="Homme" @selected(old('category_level1', $listing->category_level1) === 'Homme')>Homme</option>
                        <option value="Enfant" @selected(old('category_level1', $listing->category_level1) === 'Enfant')>Enfant</option>
                        <option value="Accessoires" @selected(old('category_level1', $listing->category_level1) === 'Accessoires')>Accessoires</option>
                    </select>
                </div>

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

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Localisation</label>
                <input type="text" name="location_address" value="{{ old('location_address', $listing->location_address) }}" placeholder="Ex : Saint-Pierre, La Réunion" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex items-center gap-3 bg-gray-50 rounded-2xl p-4 cursor-pointer">
                    <input type="checkbox" name="pickup_enabled" value="1" checked class="rounded text-teal-700 focus:ring-teal-600">
                    <span class="text-sm font-semibold text-gray-700">Remise en main propre</span>
                </label>

                <label class="flex items-center gap-3 bg-gray-50 rounded-2xl p-4 cursor-pointer">
                    <input type="checkbox" name="shipping_enabled" value="1" class="rounded text-teal-700 focus:ring-teal-600">
                    <span class="text-sm font-semibold text-gray-700">Livraison possible</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Prix livraison en €</label>
                <input type="number" name="shipping_price" value="{{ old('shipping_price', $listing->shipping_price) }}" min="0" placeholder="Ex : 5" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                Enregistrer les modifications
            </button>
        </form>

    </div>
</section>
@endsection
