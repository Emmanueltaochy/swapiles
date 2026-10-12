@php
    $t = $getRecord();
    $messages = \App\Support\FicheTransaction::conversation($t);
@endphp

@if($messages->isEmpty())
    <p style="font-size:.87rem;opacity:.6;">Aucun message entre eux à propos de cette annonce.</p>
@else
    <div style="display:flex;flex-direction:column;gap:.45rem;max-height:420px;overflow:auto;padding-right:.3rem;">
        @foreach($messages as $m)
            @php $vendeur = (int) $m->sender_id === (int) $t->seller_id; @endphp
            <div style="max-width:85%;align-self:{{ $vendeur ? 'flex-start' : 'flex-end' }};border-radius:.8rem;padding:.5rem .7rem;font-size:.85rem;background:{{ $vendeur ? 'rgba(148,163,184,.16)' : 'rgba(13,148,136,.14)' }};">
                <div style="font-size:.7rem;font-weight:700;opacity:.6;">{{ $vendeur ? 'Vendeur' : 'Acheteur' }} · {{ $m->created_at->format('d/m H:i') }}</div>
                <div style="white-space:pre-line;">{{ $m->body }}</div>
            </div>
        @endforeach
    </div>
@endif
