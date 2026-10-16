{{--
    Barre « 🛍️ Lot chez Léa : 3 articles · 54 € (−10 %) → Voir le lot »,
    au-dessus de la barre de navigation, quand l'acheteur prépare un lot chez
    ce vendeur.
--}}
@auth
    @php
        $articlesLot = \App\Support\Lots::articles(auth()->user(), $vendeur);
    @endphp
    @if($articlesLot->isNotEmpty() && auth()->id() !== $vendeur->id)
        @php
            $prixLot = $articlesLot->map(fn ($a) => (int) round((float) $a->price * 100))->all();
            $pctLot = \App\Support\Lots::remisePct($vendeur, count($prixLot));
            $totalLot = array_sum(\App\Support\Lots::prixRemises($prixLot, $pctLot)) / 100;
        @endphp
        <a href="{{ route('lots.show', $vendeur) }}"
           class="fixed inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-40 flex items-center justify-between gap-3 rounded-2xl bg-gray-900 px-4 py-3 text-white shadow-2xl sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96"
           data-barre-lot>
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold">🛍️ Lot chez {{ $vendeur->name }}</span>
                <span class="block text-xs text-white/80">{{ count($prixLot) }} article{{ count($prixLot) > 1 ? 's' : '' }} · {{ number_format($totalLot, 2, ',', ' ') }} €{{ $pctLot > 0 ? ' (−' . $pctLot . ' %)' : '' }}</span>
            </span>
            <span class="shrink-0 rounded-xl bg-white px-3 py-2 text-sm font-bold text-gray-900">Voir le lot →</span>
        </a>
    @endif
@endauth
