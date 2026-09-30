<x-filament-panels::page>

    @php
        $carte = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900';
        $enTete = 'border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700';
        $ligne = 'border-b border-gray-100 dark:border-gray-800';
    @endphp

    <div class="space-y-6">

        {{-- Actions --}}
        <div class="{{ $carte }}">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Rangement proposé</h2>

            <p class="mt-1 text-sm text-gray-500">
                <strong>{{ $aReclasser->count() }}</strong> annonce(s) à reclasser d'après leur titre ·
                <strong>{{ $aRanger->count() }}</strong> à ranger depuis un rayon invalide ·
                {{ $laissees }} laissée(s) en place.
            </p>

            <p class="mt-3 text-sm text-gray-500">
                Le formulaire de dépôt n'a longtemps offert que Femme, Homme et Enfant :
                le vendeur d'un réfrigérateur n'avait aucun rayon juste à choisir. Le
                reclassement se fonde donc sur le <strong>titre seul</strong> — la description
                parle d'autre chose trop souvent pour être fiable.
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if($aRanger->count() + $aReclasser->count() > 0)
                    <x-filament::button
                        wire:click="rangerTout"
                        wire:confirm="Déplacer toutes les annonces dont le titre est reconnu ? L'opération est annulable."
                        color="primary">
                        Ranger les annonces
                    </x-filament::button>
                @endif

                @if($dejaDeplacees > 0)
                    <x-filament::button
                        wire:click="annulerRangement"
                        wire:confirm="Remettre les {{ $dejaDeplacees }} annonces déplacées là où elles étaient ?"
                        color="gray">
                        Annuler le rangement ({{ $dejaDeplacees }})
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- Aperçu du reclassement --}}
        @if($aReclasser->isNotEmpty() || $aRanger->isNotEmpty())
            <div class="{{ $carte }}">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Aperçu</h2>

                <table class="mt-4 w-full text-sm">
                    <thead>
                        <tr class="{{ $enTete }}">
                            <th class="py-2">Annonce</th>
                            <th class="py-2">Aujourd'hui</th>
                            <th class="py-2">Proposé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aReclasser->concat($aRanger)->take(80) as $item)
                            <tr class="{{ $ligne }}">
                                <td class="py-2">
                                    <a href="{{ route('listings.show', $item['listing']) }}" target="_blank"
                                       class="font-medium text-primary-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($item['listing']->title, 48) }}
                                    </a>
                                </td>
                                <td class="py-2 text-gray-500">{{ $item['avant'] }}</td>
                                <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $item['apres'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Les rayons qui manquent encore --}}
        <div class="{{ $carte }}">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Mots fréquents qu'aucune règle ne reconnaît</h2>
            <p class="mt-1 text-sm text-gray-500">
                La liste des rayons qui manquent. Un mot qui revient souvent ici mérite
                sa règle — envoyez cette liste pour qu'on l'ajoute.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($motsNonReconnus as $mot)
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm dark:bg-gray-800">
                        {{ $mot['mot'] }}
                        <span class="ml-1 font-bold text-gray-500">{{ $mot['total'] }}</span>
                    </span>
                @empty
                    <p class="text-sm text-gray-500">Tous les titres sont reconnus.</p>
                @endforelse
            </div>
        </div>

        {{-- Répartition niveau 1 --}}
        <div class="{{ $carte }}">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Répartition par catégorie</h2>

            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="{{ $enTete }}">
                        <th class="py-2">Catégorie enregistrée</th>
                        <th class="py-2">État</th>
                        <th class="py-2 text-right">Annonces</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($repartition as $l)
                        <tr class="{{ $ligne }}">
                            <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $l['label'] }}</td>
                            <td class="py-2">
                                @if($l['connue'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Dans l'arbre</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Inconnue</span>
                                @endif
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ $l['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-gray-500">Aucune annonce publiée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Répartition niveau 2 : c'est là que se voit le désordre --}}
        <div class="{{ $carte }}">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Répartition par sous-catégorie</h2>
            <p class="mt-1 text-sm text-gray-500">
                Tout étant regroupé sous trois catégories, c'est ici que se voit
                ce qui est rangé au mauvais endroit.
            </p>

            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="{{ $enTete }}">
                        <th class="py-2">Catégorie</th>
                        <th class="py-2">Sous-catégorie</th>
                        <th class="py-2 text-right">Annonces</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($repartitionNiveau2 as $l)
                        <tr class="{{ $ligne }}">
                            <td class="py-2 text-gray-500">{{ $l['niveau1'] }}</td>
                            <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                                {{ $l['label'] }}
                                @unless($l['connue'])
                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">hors arbre</span>
                                @endunless
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ $l['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

</x-filament-panels::page>
