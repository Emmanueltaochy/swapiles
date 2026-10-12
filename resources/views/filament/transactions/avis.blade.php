@php
    $t = $getRecord();
    $avis = \App\Models\Review::where('transaction_id', $t->id)->with('reviewer:id,name')->get();
@endphp

@forelse($avis as $r)
    <div style="padding:.45rem 0;border-bottom:1px solid rgba(148,163,184,.14);font-size:.87rem;">
        <div style="font-weight:700;">{{ str_repeat('★', (int) $r->rating) }}<span style="opacity:.3;">{{ str_repeat('★', 5 - (int) $r->rating) }}</span>
            <span style="font-weight:400;opacity:.6;">par {{ $r->reviewer->name ?? '—' }} ({{ (int) $r->reviewer_id === (int) $t->buyer_id ? 'acheteur' : 'vendeur' }})</span></div>
        @if($r->comment)<div style="margin-top:.2rem;">« {{ $r->comment }} »</div>@endif
    </div>
@empty
    <p style="font-size:.87rem;opacity:.6;">Aucun avis pour cette vente.</p>
@endforelse
