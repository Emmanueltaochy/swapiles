{{-- Points relais acceptés pour cette annonce (surcharge le défaut du compte).
     Réservé aux annonces CB. Vide = on utilise les relais par défaut du vendeur. --}}
@if(($relayPoints ?? collect())->isNotEmpty())
    <div id="relay_box" class="rounded-xl border border-teal-100 bg-teal-50/50 p-4">
        <p class="font-semibold text-teal-950">🏪 Points relais acceptés <span class="font-normal text-teal-700">(facultatif)</span></p>
        <p class="mt-1 mb-3 text-sm text-teal-800">
            Coche les commerçants où tu acceptes de déposer ce colis. L'acheteur choisira le plus proche de chez lui.
            Laisse vide pour utiliser tes points relais par défaut (réglés dans ton profil).
        </p>
        <div class="space-y-2">
            @foreach($relayPoints as $rp)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50">
                    <input type="checkbox" name="relay_point_ids[]" value="{{ $rp->id }}"
                           class="mt-1 rounded text-teal-600 focus:ring-teal-500"
                           @checked(in_array($rp->id, old('relay_point_ids', $selectedRelayIds ?? [])))>
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
