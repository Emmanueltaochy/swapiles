<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 p-4 text-sm">
            <p class="font-semibold text-amber-900 dark:text-amber-200">Aperçu de l'e-mail qui sera envoyé</p>
            <pre class="mt-2 whitespace-pre-wrap text-amber-900 dark:text-amber-100 text-xs leading-relaxed">Objet : 💶 Il te manque juste ton IBAN pour être payé sur Swap'Îles

Bonjour [prénom],

Bonne nouvelle : ton compte vendeur est presque prêt, ton identité est validée ✅.
Il ne manque plus que ton IBAN (ton compte bancaire) pour être payé automatiquement
dès qu'un acheteur règle par carte.

Ajoute ton IBAN en 2 minutes ici : [lien vers ton portefeuille]

Une fois fait, tes annonces passent en paiement sécurisé et tu es payé après
chaque remise confirmée — zéro impayé, zéro avance.

L'équipe Swap'Îles</pre>
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
