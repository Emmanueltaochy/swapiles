<x-filament-panels::page>
    <div class="space-y-8">
        <div>
            <h2 class="text-xl font-bold">Envoyer une notification push</h2>
            <p class="text-sm text-gray-500">
                Prévenez tous les membres qui ont installé l’application (nouveautés, annonces, événements…).
                La notification s’affiche directement sur leur téléphone.
            </p>
        </div>

        @php $count = \App\Models\DeviceToken::count(); $ready = \App\Support\FcmService::configured(); @endphp

        <div class="flex flex-wrap gap-3">
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3">
                <p class="text-2xl font-extrabold text-gray-900">{{ number_format($count, 0, ',', ' ') }}</p>
                <p class="text-xs text-gray-500">appareils enregistrés</p>
            </div>
            <div class="rounded-2xl border px-5 py-3 {{ $ready ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                <p class="text-sm font-bold {{ $ready ? 'text-emerald-800' : 'text-amber-800' }}">
                    {{ $ready ? '✅ Push configuré' : '⚠️ Push non configuré' }}
                </p>
                <p class="text-xs {{ $ready ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $ready ? 'Prêt à envoyer' : 'Compte de service Firebase à installer sur le serveur' }}
                </p>
            </div>
        </div>

        <form wire:submit="send" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary" icon="heroicon-o-bell-alert"
                wire:confirm="Envoyer cette notification à tous les appareils ?">
                Envoyer la notification
            </x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
