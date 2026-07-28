<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Résumé --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500">Recherches (30 j)</p>
                <p class="mt-1 text-3xl font-bold">{{ $totalSearches }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500">Termes distincts</p>
                <p class="mt-1 text-3xl font-bold">{{ $distinctTerms }}</p>
            </div>
        </div>

        {{-- Liste en cartes : lisible au pouce sur mobile --}}
        <div class="space-y-2">
            @forelse($rows as $r)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-base font-bold text-gray-900 dark:text-gray-100 break-words">« {{ $r->term }} »</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="rounded-full bg-primary-50 dark:bg-primary-900/30 px-2.5 py-1 font-semibold text-primary-700 dark:text-primary-300">
                            🔁 {{ $r->total }} recherche{{ $r->total > 1 ? 's' : '' }}
                        </span>
                        <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-1 font-medium text-gray-600 dark:text-gray-300">
                            👤 {{ $r->visitors }} visiteur{{ $r->visitors > 1 ? 's' : '' }}
                        </span>
                        <span class="text-xs text-gray-400">
                            · {{ \Illuminate\Support\Carbon::parse($r->last_seen)->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500">
                    Aucune recherche sans résultat sur 30 jours 🎉
                </div>
            @endforelse
        </div>

        <p class="text-xs text-gray-500 leading-relaxed">
            Classé par nombre de recherches (30 derniers jours). « Visiteurs » = personnes distinctes
            (cookie visiteur, même non connectées). Beaucoup de recherches pour <span class="font-semibold">peu</span>
            de visiteurs = une même personne insiste ; beaucoup de visiteurs = vraie demande. Les robots à
            User-Agent connu sont déjà exclus.
        </p>
    </div>
</x-filament-panels::page>
