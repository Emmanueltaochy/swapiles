<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border-2 border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 text-sm">
            <p class="font-bold text-red-800 dark:text-red-200">⛔ Délivrabilité d'abord</p>
            <p class="mt-1 text-red-800 dark:text-red-200">
                N'envoie PAS cette campagne tant que <strong>SPF / DKIM / DMARC</strong> ne sont pas validés en prod.
                Si DKIM est cassé, l'e-mail part en spam chez tes 15 vendeurs — et tu grilles ta seule liste de relance.
                Vérifie les DNS, envoie-toi un test, puis seulement lance la campagne.
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700 p-4 text-sm">
            <p class="font-semibold text-gray-900 dark:text-gray-100">Aperçu de l'e-mail</p>
            <pre class="mt-2 whitespace-pre-wrap text-gray-800 dark:text-gray-200 text-xs leading-relaxed">Objet : Ton compte vendeur Swap'îles est presque prêt

Bonjour [prénom],

Bonne nouvelle : ton compte vendeur est presque prêt, ton identité est validée.
Il ne manque plus que ton IBAN (ton compte bancaire) pour être payé automatiquement
dès qu'un acheteur règle par carte.

Et une info qui change tout : la commission vendeur est passée à 0 %. Tu reçois
désormais 100 % du prix affiché sur ton annonce.

Ajoute ton IBAN en 2 minutes ici : [lien vers ton portefeuille]

Avec le paiement sécurisé Swap'îles, tu es payé avant même de remettre l'article.

L'équipe Swap'îles</pre>
        </div>

        <div>
            <h2 class="text-lg font-bold mb-2">Vendeurs ciblés ({{ $targets->count() }})</h2>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                        <tr><th class="p-3">User</th><th class="p-3">Nom</th><th class="p-3">E-mail</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($targets as $t)
                            <tr>
                                <td class="p-3 font-mono">#{{ $t->id }}</td>
                                <td class="p-3">{{ $t->name }}</td>
                                <td class="p-3">{{ $t->email }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-6 text-center text-gray-500">Aucun vendeur dans ce cas (tous ont leur IBAN 🎉).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-sm text-gray-500">Rien n'est envoyé tant que tu ne cliques pas « Envoyer la relance IBAN » en haut à droite.</p>
        </div>
    </div>
</x-filament-panels::page>
