@php
    $t = $getRecord();
    $l = $t->listing;
    $photo = $l?->images?->sortBy('order')->first()?->url;
    $cat = $l ? collect([$l->category_level1, $l->category_level2, $l->category_level3])->map(fn ($c) => \App\Support\Categories::label($c))->filter()->implode(' › ') : null;
@endphp

@if(! $l)
    <p style="opacity:.6;">L'annonce a été supprimée.</p>
@else
    <div style="display:flex;gap:1rem;align-items:flex-start;">
        <div style="width:96px;height:96px;flex:none;border-radius:.75rem;overflow:hidden;background:rgba(148,163,184,.15);">
            @if($photo)<img src="{{ $photo }}" alt="{{ $l->title }}" style="width:100%;height:100%;object-fit:cover;">@endif
        </div>
        <div style="min-width:0;flex:1;">
            <div style="font-size:1.05rem;font-weight:800;">{{ $l->title }}</div>
            <div style="font-size:.82rem;opacity:.6;margin-top:.15rem;">{{ $cat ?: 'Catégorie non renseignée' }}@if($l->marque) · {{ $l->marque }}@endif @if($l->taille) · taille {{ $l->taille }}@endif</div>
            <div style="margin-top:.5rem;font-size:.87rem;">
                Prix affiché : <strong>{{ \App\Support\TableauDeBord::euros((float) $l->price) }}</strong>
                @if($t->listing_offer_id) · <span class="tx-pastille" style="color:#1d4ed8;background:rgba(59,130,246,.12);">prix négocié par une offre</span>@endif
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:.6rem;font-size:.85rem;">
                <a class="tx-lien" href="{{ route('listings.show', $l) }}" target="_blank" rel="noopener">Voir l'annonce sur le site ↗</a>
                <a class="tx-lien" href="{{ \App\Filament\Resources\Listings\ListingResource::getUrl('view', ['record' => $l]) }}">Fiche admin de l'annonce</a>
                <span style="opacity:.6;">{{ (int) $l->views_count }} vue(s) · annonce {{ ['published' => 'en ligne', 'sold' => 'vendue', 'draft' => 'masquée', 'pending' => 'en attente'][$l->status] ?? $l->status }}</span>
            </div>
        </div>
    </div>
@endif
