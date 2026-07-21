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

            <x-filament::button type="submit" color="warning">
                Envoyer la newsletter
            </x-filament::button>
        </form>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h3 class="text-lg font-extrabold text-amber-950">
                Connexion des anciens inscrits
            </h3>

            <p class="mt-2 text-sm text-amber-900">
                Envoyez un Magic Link personnalisé à tous les utilisateurs inscrits afin qu’ils puissent se reconnecter à la nouvelle plateforme.
            </p>

            <div class="mt-4">
                <x-filament::button
                    color="danger"
                    wire:click="sendMagicLinksToAllUsers"
                    wire:confirm="Confirmer l’envoi d’un Magic Link à tous les inscrits ?"
                >
                    Envoyer les Magic Links à tous les inscrits
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
