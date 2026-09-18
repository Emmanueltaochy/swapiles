<x-filament-panels::page>
    <div class="space-y-8">
        <div>
            <h2 class="text-xl font-bold">Envoyer une notification push</h2>
            <p class="text-sm text-gray-500">
                Prévenez tous les membres qui ont installé l’application (nouveautés, annonces, événements…).
                La notification s’affiche directement sur leur téléphone.
            </p>
        </div>

        {{-- Appareils enregistrés --}}
        <div class="flex flex-wrap gap-3">
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-transparent">
                <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ number_format($total, 0, ',', ' ') }}</p>
                <p class="text-xs text-gray-500">appareils enregistrés</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-transparent">
                <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $iosCount }}</p>
                <p class="text-xs text-gray-500">iPhone / iPad</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-transparent">
                <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $androidCount }}</p>
                <p class="text-xs text-gray-500">Android</p>
            </div>
        </div>

        {{-- État des deux services d'envoi : c'est ici qu'on voit ce qui bloque --}}
        <div class="space-y-3">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">État de l’envoi</p>

            {{-- Apple --}}
            <div class="rounded-2xl border px-5 py-4 {{ $apnsPret ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                <p class="text-sm font-bold {{ $apnsPret ? 'text-emerald-800' : 'text-amber-800' }}">
                    {{ $apnsPret ? '✅' : '⚠️' }} iPhone / iPad — Apple (APNs)
                </p>
                @if($apnsPret)
                    <p class="mt-0.5 text-xs text-emerald-700">Prêt à envoyer.</p>
                    @unless($http2)
                        <p class="mt-2 rounded-lg bg-amber-100 px-3 py-2 text-xs text-amber-900">
                            Attention : ce serveur ne sait pas parler HTTP/2, qu’Apple exige.
                            Les envois vers iPhone échoueront tant que ce n’est pas corrigé.
                        </p>
                    @endunless
                @else
                    <p class="mt-1 text-xs text-amber-800">
                        Il manque encore : {{ implode(', ', $apnsManquant) }}.
                    </p>
                    <p class="mt-2 text-xs text-amber-700">
                        L’application iOS n’utilise pas Firebase : son jeton est un jeton Apple,
                        que Firebase refuse. Les notifications iPhone passent donc directement par Apple,
                        ce qui demande une clé d’authentification APNs.
                    </p>
                @endif
            </div>

            {{-- Android --}}
            <div class="rounded-2xl border px-5 py-4 {{ $fcmPret ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                <p class="text-sm font-bold {{ $fcmPret ? 'text-emerald-800' : 'text-amber-800' }}">
                    {{ $fcmPret ? '✅' : '⚠️' }} Android — Firebase (FCM)
                </p>
                <p class="mt-0.5 text-xs {{ $fcmPret ? 'text-emerald-700' : 'text-amber-800' }}">
                    {{ $fcmPret ? 'Prêt à envoyer.' : 'Compte de service Firebase à installer sur le serveur.' }}
                </p>
            </div>
        </div>

        {{-- Appareils enregistrés et resultat du dernier envoi --}}
        @if($total > 0)
            <div class="space-y-2">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Appareils</p>
                @foreach($appareils as $appareil)
                    <div class="rounded-2xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ \App\Jobs\SendPushBroadcast::estIos($appareil) ? 'iPhone / iPad' : 'Android' }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $appareil->tokenApercu() }}</span>
                            @if($appareil->last_result === 'ok')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">✅ accepté</span>
                            @elseif($appareil->last_result)
                                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">⚠️ {{ $appareil->last_result }}</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">jamais sollicité</span>
                            @endif
                            @if($appareil->last_sent_at)
                                <span class="text-xs text-gray-400">· {{ $appareil->last_sent_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                        @if($appareil->last_error)
                            <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">{{ $appareil->last_error }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Test immediat : montre la reponse exacte du service --}}
        <div class="rounded-2xl border border-gray-200 px-5 py-4 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tester l’envoi</p>
            <p class="mt-1 text-xs text-gray-500">
                Envoie immédiatement une notification de test aux appareils enregistrés et affiche
                la réponse exacte d’Apple ou de Google — utile quand rien n’arrive sans qu’on sache pourquoi.
            </p>

            <x-filament::button wire:click="testerEnvoi" color="gray" icon="heroicon-o-beaker" class="mt-3">
                Envoyer un test et voir le résultat
            </x-filament::button>

            @if(count($diagnostic) > 0)
                <div class="mt-4 space-y-2">
                    @foreach($diagnostic as $ligne)
                        <div class="rounded-xl border px-4 py-3 text-sm {{ $ligne['statut'] === 'ok' ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }}">
                            <p class="font-semibold {{ $ligne['statut'] === 'ok' ? 'text-emerald-800' : 'text-red-800' }}">
                                {{ $ligne['statut'] === 'ok' ? '✅' : '⚠️' }} {{ $ligne['plateforme'] }} — {{ $ligne['jeton'] }}
                            </p>
                            @if($ligne['erreur'])
                                <p class="mt-1 text-xs text-red-800">{{ $ligne['erreur'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
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
