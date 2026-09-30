<x-filament-panels::page>

    <div class="space-y-6">

        {{-- Où sont vraiment les annonces --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Répartition réelle des annonces publiées</h2>
            <p class="mt-1 text-sm text-gray-500">
                Une catégorie « inconnue » n'existe pas dans l'arbre : ces annonces
                n'apparaissent dans aucun rayon et ne se trouvent qu'à la recherche texte.
            </p>

            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700">
                        <th class="py-2">Catégorie enregistrée</th>
                        <th class="py-2">État</th>
                        <th class="py-2 text-right">Annonces</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($repartition as $ligne)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $ligne['label'] }}</td>
                            <td class="py-2">
                                @if($ligne['connue'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Dans l'arbre</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Inconnue</span>
                                @endif
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ $ligne['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-gray-500">Aucune annonce publiée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Aperçu du rangement --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Rangement proposé</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $reconnues }} annonce{{ $reconnues > 1 ? 's' : '' }} reconnue{{ $reconnues > 1 ? 's' : '' }}
                        par les règles · {{ $inconnues }} laissée{{ $inconnues > 1 ? 's' : '' }} en place
                        (aucune règle ne correspond).
                    </p>
                </div>

                @if($reconnues > 0)
                    <x-filament::button
                        wire:click="rangerTout"
                        wire:confirm="Ranger toutes les annonces reconnues ? Les annonces déjà bien classées ne sont pas touchées."
                        color="primary">
                        Ranger les annonces
                    </x-filament::button>
                @endif
            </div>

            <p class="mt-3 text-sm text-gray-500">
                Une annonce déjà bien classée n'est jamais déplacée : le choix du
                vendeur fait foi. Une annonce qu'aucune règle ne reconnaît reste
                où elle est — mal la ranger la rendrait plus difficile à retrouver
                que de la laisser sans catégorie.
            </p>

            @if($propositions->isNotEmpty())
                <table class="mt-4 w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700">
                            <th class="py-2">Annonce</th>
                            <th class="py-2">Aujourd'hui</th>
                            <th class="py-2">Proposé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($propositions->take(60) as $proposition)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">
                                    <a href="{{ route('listings.show', $proposition['listing']) }}" target="_blank"
                                       class="font-medium text-primary-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($proposition['listing']->title, 50) }}
                                    </a>
                                </td>
                                <td class="py-2 text-gray-500">{{ $proposition['avant'] }}</td>
                                <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $proposition['apres'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($propositions->count() > 60)
                    <p class="mt-3 text-sm text-gray-500">
                        … et {{ $propositions->count() - 60 }} autre{{ $propositions->count() - 60 > 1 ? 's' : '' }}.
                        Le bouton traite l'ensemble des annonces, pas seulement cet aperçu.
                    </p>
                @endif
            @endif
        </div>

    </div>

</x-filament-panels::page>
