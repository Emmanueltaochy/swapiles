{{-- « Revenir au live » : on a quitté un live encore en cours (pour payer
     un article, voir une annonce…). Disparaît dès que le live est fini. --}}
@php
    $liveRetour = null;
    if (session()->has('live_retour') && ! request()->routeIs('live.*')) {
        $liveRetour = \App\Models\LiveSession::find(session('live_retour'));
        if (! $liveRetour || ! $liveRetour->enDirect() || ! \App\Support\LiveAcces::visible(auth()->user())) {
            session()->forget('live_retour');
            $liveRetour = null;
        }
    }
@endphp
@if($liveRetour)
    <div data-live-retour="{{ $liveRetour->id }}" class="fixed right-3 z-[55] flex items-center gap-1 rounded-full bg-gray-900 py-1 pl-1 pr-1 text-white shadow-xl shadow-black/30 lg:bottom-6 lg:right-6" style="bottom:calc(88px + env(safe-area-inset-bottom))">
        <a href="{{ route('live.show', $liveRetour) }}" class="flex items-center gap-2 rounded-full py-1.5 pl-2 pr-2 text-sm font-extrabold">
            <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span></span>
            Revenir au live
        </a>
        <button type="button" class="grid h-7 w-7 place-items-center rounded-full bg-white/15 text-xs" aria-label="Masquer"
                onclick="try { sessionStorage.setItem('swp_live_retour_masque', '{{ $liveRetour->id }}'); } catch (e) {} this.parentElement.remove();">✕</button>
    </div>
    <script>
        try {
            if (sessionStorage.getItem('swp_live_retour_masque') === '{{ $liveRetour->id }}') {
                document.querySelector('[data-live-retour="{{ $liveRetour->id }}"]').remove();
            }
        } catch (e) {}
    </script>
@endif
