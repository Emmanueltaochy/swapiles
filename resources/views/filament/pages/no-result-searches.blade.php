<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500">Recherches sans résultat (30 j)</p>
                <p class="text-2xl font-bold">{{ $totalSearches }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500">Termes distincts</p>
                <p class="text-2xl font-bold">{{ $distinctTerms }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                    <tr>
                        <th class="p-3 font-semibold">Recherche</th>
                        <th class="p-3 font-semibold text-right">Occurrences</th>
                        <th class="p-3 font-semibold text-right">Utilisateurs</th>
                        <th class="p-3 font-semibold text-right">Dernière fois</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $r)
                        <tr>
                            <td class="p-3 font-medium">{{ $r->term }}</td>
                            <td class="p-3 text-right font-bold">{{ $r->total }}</td>
                            <td class="p-3 text-right">{{ $r->users }}</td>
                            <td class="p-3 text-right text-gray-500">{{ \Illuminate\Support\Carbon::parse($r->last_seen)->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-gray-500">Aucune recherche sans résultat sur 30 jours 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-sm text-gray-500">Classé par nombre d'occurrences sur les 30 derniers jours. « Utilisateurs » = comptes connectés distincts (les recherches anonymes comptent comme un seul).</p>
    </div>
</x-filament-panels::page>
