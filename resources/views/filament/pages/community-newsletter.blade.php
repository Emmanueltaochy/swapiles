<x-filament-panels::page>
    <div class="space-y-8">
        <div>
            <h2 class="text-xl font-bold">Animer la communauté</h2>
            <p class="text-sm text-gray-500">
                Envoyez un email aux membres Swap’Îles pour annoncer une nouveauté, relancer l’activité ou promouvoir les annonces inter-îles.
            </p>
        </div>

        <form wire:submit="send" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary" icon="heroicon-o-paper-airplane">
                Envoyer la newsletter
            </x-filament::button>
        </form>

        {{-- Outil ponctuel de la migration depuis l'ancien site : replié pour
             qu'on ne l'envoie pas par erreur à tous les membres. --}}
        <details class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <summary class="cursor-pointer text-sm font-bold text-amber-950">
                Outil de migration : connexion des anciens inscrits
            </summary>

            <p class="mt-3 text-sm text-amber-900">
                Envoie un lien de connexion personnel (valable 7 jours) à <strong>tous</strong> les inscrits,
                pour qu’ils se reconnectent à la nouvelle plateforme. À n’utiliser qu’une fois.
                L’hébergeur limite à 1000 e-mails par jour : au-delà, les e-mails importants arrivent en retard.
            </p>

            <div class="mt-4">
                <x-filament::button
                    color="danger"
                    wire:click="sendMagicLinksToAllUsers"
                    wire:confirm="Envoyer un lien de connexion à TOUS les inscrits ? Cette action envoie de vrais e-mails."
                >
                    Envoyer les liens à tous les inscrits
                </x-filament::button>
            </div>
        </details>
    </div>
</x-filament-panels::page>
