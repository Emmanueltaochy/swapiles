<x-filament-panels::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold mb-2">Config prod (pour les DNS / délivrabilité)</h2>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($config as $k => $v)
                            <tr>
                                <td class="p-3 font-mono text-gray-500">{{ $k }}</td>
                                <td class="p-3 font-mono font-semibold">{{ $v }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-sm text-gray-500">
                APP_URL doit être <code>https://swapiles.com</code>. MAIL_MAILER / MAIL_HOST indiquent par quel fournisseur partent les e-mails (pour régler SPF/DKIM).
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold mb-2">Membres & vendeurs</h2>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($rows as $r)
                            <tr>
                                <td class="p-3">{{ $r['label'] }}</td>
                                <td class="p-3 text-right font-bold">{{ $r['value'] }}</td>
                                <td class="p-3 text-right text-gray-500">{{ $r['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-bold mb-2">Annonces publiées — pourquoi pas en CB ? (point 5)</h2>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($listingRows as $r)
                            <tr>
                                <td class="p-3">{{ $r['label'] }}</td>
                                <td class="p-3 text-right font-bold
                                    {{ $r['tone'] === 'warn' ? 'text-amber-600' : ($r['tone'] === 'ok' ? 'text-emerald-600' : 'text-gray-700 dark:text-gray-200') }}">
                                    {{ $r['value'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-sm text-gray-500">
                « CB à activer alors que vendeur opérationnel » doit être <strong>0</strong> : la commande
                <code>listings:enable-cb-for-stripe-sellers</code> les bascule à chaque déploiement. Les annonces restées
                hors CB le sont parce que le vendeur n'a pas d'IBAN (point 10) ou que l'annonce est inéligible (don/échange, prix 0).
            </p>
        </div>
    </div>
</x-filament-panels::page>
