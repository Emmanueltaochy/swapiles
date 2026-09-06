<x-filament-panels::page>
    @if(! $dispo)
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
            Le journal des envois n’est pas encore disponible.
        </div>
    @else
        <div class="space-y-4">

            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs text-gray-500">E-mails envoyés (7 derniers jours)</p>
                <p class="mt-1 text-3xl font-bold">{{ $total7j }}</p>
                <p class="mt-2 text-sm text-gray-500">
                    L’hébergeur limite la boîte d’envoi à <strong>{{ $quota }} e-mails par jour</strong>.
                    Au-delà, tous les envois sont ralentis : les e-mails importants
                    (confirmation d’adresse, mot de passe, vente) arrivent en retard ou en indésirable.
                </p>
            </div>

            {{-- Par jour --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Par jour (14 jours)</p>
                <div class="mt-3 space-y-2">
                    @forelse($jours as $j)
                        @php
                            $pct = min(100, (int) round($j->total / max(1, $quota) * 100));
                            $depasse = $j->total >= $quota;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">
                                    {{ \Illuminate\Support\Carbon::parse($j->jour)->format('d/m/Y') }}
                                </span>
                                <span class="font-semibold {{ $depasse ? 'text-danger-600' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $j->total }}{{ $depasse ? ' · plafond atteint' : '' }}
                                </span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full {{ $depasse ? 'bg-danger-500' : 'bg-primary-500' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Aucun envoi enregistré.</p>
                    @endforelse
                </div>
            </div>

            {{-- Par type --}}
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Par type (7 jours)</p>
                <div class="mt-3 space-y-2">
                    @forelse($types as $t)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-600 dark:text-gray-300 break-words">{{ $t['famille'] }}</span>
                            <span class="shrink-0 rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-1 font-semibold text-gray-700 dark:text-gray-300">
                                {{ $t['total'] }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Aucun envoi enregistré.</p>
                    @endforelse
                </div>
            </div>

        </div>
    @endif
</x-filament-panels::page>
