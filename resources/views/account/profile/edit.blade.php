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
<input autocomplete="new-password" type="password" name="password" placeholder="Laisser vide pour ne pas changer"
            class="w-full mt-2 rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror">
                    <label class="block mt-5 text-sm font-extrabold text-gray-800">Confirmation du mot de passe</label>
<input autocomplete="new-password" type="password" name="password_confirmation" placeholder="Confirmer le mot de passe"
            class="w-full mt-2 rounded-2xl border-2 border-gray-300 bg-white px-5 py-4 text-lg font-bold text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100 @error('password_confirmation') border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-100 @enderror">
<p class="mt-2 text-sm text-gray-500">
    Laissez ces champs vides pour conserver votre mot de passe actuel. Sinon, utilisez au moins 8 caractères (les deux doivent être identiques).
</p>
                </div>
            </div>

            @if($relayPoints->isNotEmpty())
                <div class="pt-5 border-t border-gray-100">
                    <h2 class="font-extrabold text-gray-900 mb-1">🏪 Mes points relais de dépôt</h2>
                    <p class="text-sm text-gray-500 mb-3">
                        Coche les commerçants où tu acceptes de déposer tes colis. L'acheteur choisira, parmi eux, le plus proche de chez lui.
                        Si tu n'en coches aucun, tous les points relais de ton île seront proposés.
                    </p>
                    <div class="space-y-2">
                        @foreach($relayPoints as $rp)
                            <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-3 cursor-pointer has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50/40">
                                <input type="checkbox" name="relay_point_ids[]" value="{{ $rp->id }}"
                                       class="mt-1 rounded text-teal-600 focus:ring-teal-500"
                                       @checked(in_array($rp->id, old('relay_point_ids', $selectedRelayIds)))>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900">{{ $rp->name }}</span>
                                    @if($rp->fullAddress())
                                        <span class="block text-sm text-gray-500">{{ $rp->fullAddress() }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                Enregistrer
            </button>
        </form>

        {{-- Réglages de notification : le membre doit pouvoir couper ce qu'il ne
             veut plus recevoir. Sans ça, le seul moyen d'arrêter d'être sollicité
             était de supprimer son compte. --}}
        <div id="notifications" class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900">🔔 Mes notifications</h2>
            <p class="mt-1 text-sm text-gray-500">
                Choisissez ce que vous souhaitez recevoir. Les messages liés à vos
                ventes et à vos achats vous sont toujours envoyés.
            </p>

            <form method="POST" action="{{ route('account.profile.update') }}" class="mt-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" value="{{ auth()->user()->name }}">
                <input type="hidden" name="notification_prefs_submitted" value="1">

                @php $prefs = auth()->user()->notification_prefs ?? []; @endphp

                <div class="overflow-hidden rounded-xl border border-gray-100">
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500">
                        <span>Type de notification</span>
                        <span class="flex gap-4"><span class="w-14 text-center">Mobile</span><span class="w-14 text-center">E-mail</span></span>
                    </div>

                    @foreach(\App\Support\NotificationPreferences::CATEGORIES as $cle => $categorie)
                        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $categorie['label'] }}</p>
                                <p class="text-xs text-gray-500">{{ $categorie['description'] }}</p>
                            </div>
                            <div class="flex shrink-0 gap-4">
                                <label class="flex w-14 justify-center">
                                    <input type="checkbox" name="notification_prefs[{{ $cle }}][push]" value="1"
                                           @checked($prefs[$cle]['push'] ?? true)
                                           class="h-5 w-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                </label>
                                <label class="flex w-14 justify-center">
                                    <input type="checkbox" name="notification_prefs[{{ $cle }}][email]" value="1"
                                           @checked($prefs[$cle]['email'] ?? true)
                                           class="h-5 w-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button class="mt-4 w-full rounded-xl bg-teal-600 px-6 py-3 font-semibold text-white transition hover:bg-teal-700">
                    Enregistrer mes préférences
                </button>
            </form>
        </div>

        {{-- Suppression de compte (RGPD) --}}
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 text-center shadow-sm">
            <p class="text-sm text-gray-500">Vous souhaitez quitter Swap'Îles ?</p>
            <a href="{{ route('account.deletion.info') }}" class="mt-1 inline-block text-sm font-semibold text-red-600 hover:text-red-700">
                Supprimer mon compte
            </a>
        </div>

    </div>
</section>
@endsection
