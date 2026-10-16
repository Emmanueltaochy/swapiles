{{-- « 🎁 −10 % dès 3 articles » : la remise lot du vendeur, s'il en propose une. --}}
@if((int) ($vendeur->lot_remise_pct ?? 0) > 0)
    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 ring-1 ring-rose-200">🎁 −{{ $vendeur->lot_remise_pct }} % dès {{ max(2, (int) $vendeur->lot_remise_min) }} articles</span>
@endif
