@extends('layouts.app')

@section('title', 'Mon profil — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <a href="{{ route('account.settings') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Réglages</a>
        <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Mon profil</h1>
        <p class="text-gray-500 mt-2">Votre nom, votre photo et votre île, tels que les membres les voient.</p>

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
                    <p class="block text-sm font-bold text-gray-800 mb-2">Photo de profil</p>
                    {{-- Bouton en français (le champ fichier natif affiche « Choose File »). --}}
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-gray-100 px-4 py-3 text-sm font-bold text-gray-800 hover:bg-gray-200">
                        📷 <span data-nom-photo>{{ $user->avatar ? 'Changer la photo' : 'Choisir une photo' }}</span>
                        <input type="file" name="avatar" accept="image/*" class="sr-only"
                               onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Choisir une photo'">
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-800 mb-2">Nom</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">
            </div>

            <div>
                <label for="bio" class="block text-sm font-bold text-gray-800 mb-2">Bio <span class="font-normal text-gray-400">(facultatif, {{ \App\Support\Bio::MAX }} caractères)</span></label>
                <textarea id="bio" name="bio" rows="3" maxlength="{{ \App\Support\Bio::MAX }}" placeholder="Quelques mots sur vous et votre dressing"
                          class="w-full rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600">{{ old('bio', $user->bio) }}</textarea>
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

            <a href="{{ route('account.addresses.edit') }}" class="flex items-center justify-between rounded-2xl bg-gray-50 px-4 py-3 text-sm hover:bg-gray-100">
                <span><span class="font-bold text-gray-900">📮 Mon adresse</span><span class="block text-gray-500">{{ $user->address_line1 ? $user->address_line1 . ', ' . $user->postal_code . ' ' . $user->city : 'À compléter pour vendre avec Colissimo' }}</span></span>
                <span class="text-gray-400" aria-hidden="true">›</span>
            </a>

            <div id="mot-de-passe" class="scroll-mt-24 border-t border-gray-100 pt-5">
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
                <div id="points-relais" class="scroll-mt-24 pt-5 border-t border-gray-100">
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

        {{-- Les preferences de notification ont leur propre page : elles etaient
             ici, tout en bas, apres le mot de passe et les points relais, donc
             introuvables pour qui voulait juste arreter de recevoir un message. --}}
        <a href="{{ route('account.notifications.preferences') }}"
           class="mt-6 flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:bg-gray-50">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gray-50 text-xl" aria-hidden="true">🔔</span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900">Préférences de notification</span>
                <span class="mt-0.5 block text-sm text-gray-500">Choisir ce que je reçois, sur mobile et par e-mail</span>
            </span>
            <span class="shrink-0 text-gray-300" aria-hidden="true">›</span>
        </a>

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
