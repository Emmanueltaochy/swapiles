<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500">Total des ventes (tous modes)</p>
                <p class="text-2xl font-bold">{{ number_format($totalAll, 2, ',', ' ') }} €</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500">Sécurisé (en ligne)</p>
                <p class="text-2xl font-bold text-teal-600">{{ number_format($securedAll, 2, ',', ' ') }} €</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500">Hors plateforme</p>
                <p class="text-2xl font-bold text-amber-600">{{ number_format($offPlatform, 2, ',', ' ') }} € ({{ $offShare }} %)</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                    <tr>
                        <th class="p-3 font-semibold">Mode</th>
                        <th class="p-3 font-semibold text-right">Ventes ({{ $monthLabel }})</th>
                        <th class="p-3 font-semibold text-right">Montant (mois)</th>
                        <th class="p-3 font-semibold text-right">Ventes (total)</th>
                        <th class="p-3 font-semibold text-right">Montant (total)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $row)
                        <tr>
                            <td class="p-3 font-medium">{{ $row['label'] }}</td>
                            <td class="p-3 text-right">{{ $row['count_month'] }}</td>
                            <td class="p-3 text-right">{{ number_format($row['total_month'], 2, ',', ' ') }} €</td>
                            <td class="p-3 text-right">{{ $row['count_all'] }}</td>
                            <td class="p-3 text-right">{{ number_format($row['total_all'], 2, ',', ' ') }} €</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-gray-500">Aucune vente enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-sm text-gray-500">
            « Sécurisé » = paiements par carte passés par Stripe (créditent le wallet vendeur).
            « Hors plateforme » = espèces, dons et échanges : jamais dans le solde retirable.
        </p>
    </div>
</x-filament-panels::page>
