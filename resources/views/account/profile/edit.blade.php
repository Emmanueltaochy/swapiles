@extends('layouts.app')

@section('title', 'Profil & expédition — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-extrabold text-gray-900">Profil & expédition</h1>
        <p class="text-gray-500 mt-2">Mettez à jour votre profil et vos informations d’expédition.</p>

        @if(session('status'))
            <div class="mt-6 bg-teal-50 text-teal-800 rounded-2xl p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mt-6 bg-red-50 text-red-700 rounded-2xl p-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data" class="mt-6 bg-white rounded-3xl border border-gray-100 shadow-sm p-6 space-y-5">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-full bg-teal-100 overflow-hidden flex items-center justify-center text-3xl font-extrabold text-teal-800">
                    @if($user->avatar)
                        <img src="{{ $user->avatar }}" class="w-full h-full object-cover" alt="{{ $user->name }}">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>

                <div class="flex-1">
                    <label class="block text-sm font-bold text-gray-800 mb-2">Photo de profil</label>
                    <input type="file" name="avatar" accept="image/*" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Nom</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Téléphone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Territoire</label>
                <select name="territoire" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                    <option value="">Non renseigné</option>
                    <option value="La Réunion" @selected(old('territoire', $user->territoire) === 'La Réunion')>🇷🇪 La Réunion</option>
                    <option value="Martinique" @selected(old('territoire', $user->territoire) === 'Martinique')>🇲🇶 Martinique</option>
                    <option value="Guadeloupe" @selected(old('territoire', $user->territoire) === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                    <option value="Guyane" @selected(old('territoire', $user->territoire) === 'Guyane')>🇬🇫 Guyane</option>
                    <option value="Mayotte" @selected(old('territoire', $user->territoire) === 'Mayotte')>🇾🇹 Mayotte</option>
                </select>
            </div>

            <div class="border-t border-gray-100 pt-5">
                <h2 class="text-lg font-extrabold text-gray-900">📦 Adresse d’expédition</h2>
                <p class="text-sm text-gray-500 mt-1">Utilisée pour générer vos bordereaux Colissimo.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Adresse</label>
                <input type="text" name="address_line1" value="{{ old('address_line1', $user->address_line1) }}" placeholder="Ex : 10 rue de Rivoli" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Complément</label>
                <input type="text" name="address_line2" value="{{ old('address_line2', $user->address_line2) }}" placeholder="Bâtiment, appartement..." class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Code postal</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" placeholder="97410" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-800 mb-2">Ville</label>
                    <input type="text" name="city" value="{{ old('city', $user->city) }}" placeholder="Saint-Pierre" class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
                </div>
            </div>

            <input type="hidden" name="country_code" value="FR">

            <div class="border-t border-gray-100 pt-5">
                <h2 class="font-extrabold text-gray-900 mb-3">Changer le mot de passe</h2>

                <div class="space-y-4">
                    <label class="block mt-5 text-sm font-extrabold text-gray-800">Nouveau mot de passe</label>
<input autocomplete="new-password" required type="password" name="password" placeholder="Nouveau mot de passe"
            class="w-full mt-2 rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror">
                    <label class="block mt-5 text-sm font-extrabold text-gray-800">Confirmation du mot de passe</label>
<input autocomplete="new-password" required type="password" name="password_confirmation" placeholder="Confirmer le mot de passe"
            class="w-full mt-2 rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password_confirmation') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror">
<p class="mt-2 text-sm text-gray-500">
    Utilisez au moins 8 caractères. Les deux mots de passe doivent être identiques.
</p>
                </div>
            </div>

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                Enregistrer
            </button>
        </form>

    </div>
</section>
@endsection
