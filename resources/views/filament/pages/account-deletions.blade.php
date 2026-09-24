<x-filament-panels::page>
    @if(! $dispo)
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-6 text-center text-gray-500">
            Le suivi des départs n’est pas encore disponible.
        </div>
    @else
        <div class="space-y-4">

            <div class="flex flex-wrap gap-3">
                <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-transparent">
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $semaine }}</p>
                    <p class="text-xs text-gray-500">départs cette semaine</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-transparent">
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{{ $total }}</p>
                    <p class="text-xs text-gray-500">motifs recueillis au total</p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Motifs, du plus fréquent au moins fréquent</p>
                <div class="mt-3 space-y-2">
                    @forelse($motifs as $m)
                        @php $pct = $total > 0 ? (int) round($m['total'] / $total * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-300">{{ $m['label'] }}</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $m['total'] }} · {{ $pct }} %</span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">
                            Aucun motif recueilli pour l’instant. La question est posée à la suppression,
                            et la réponse est facultative.
                        </p>
                    @endforelse
                </div>
            </div>

            @if($recents->isNotEmpty())
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ce qu’ils ont écrit</p>
                    <div class="mt-3 space-y-2">
                        @foreach($recents as $r)
                            <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-gray-800/40">
                                <p class="text-gray-700 dark:text-gray-200">« {{ $r->details }} »</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $r->motifLabel() }}
                                    @if($r->days_since_signup !== null) · inscrit depuis {{ $r->days_since_signup }} j @endif
                                    @if($r->had_sales) · avait des ventes @endif
                                    @if($r->created_at) · {{ $r->created_at->format('d/m/Y') }} @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    @endif
</x-filament-panels::page>
