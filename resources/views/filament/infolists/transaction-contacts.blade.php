@php
    $annonce = $getRecord()->listing;
    $candidats = $annonce ? $annonce->cashBuyerCandidates() : collect();
    $messages = $annonce
        ? \App\Models\Message::query()->where('listing_id', $annonce->id)->where('sender_id', '!=', $annonce->user_id)
            ->selectRaw('sender_id, COUNT(*) as nombre, MAX(created_at) as dernier')->groupBy('sender_id')->get()->keyBy('sender_id')
        : collect();
    $favoris = $annonce ? $annonce->favoritedBy()->pluck('users.id')->all() : [];
@endphp

@if($candidats->isEmpty())
    <p style="font-size:.9rem;opacity:.7;">Personne n'a écrit au vendeur ni mis cette annonce en favori sur Swap'Îles.</p>
@else
    <div style="display:flex;flex-direction:column;gap:.5rem;">
        @foreach($candidats as $membre)
            @php $m = $messages[$membre->id] ?? null; @endphp
            <div style="display:flex;justify-content:space-between;gap:1rem;border:1px solid rgba(148,163,184,.25);border-radius:.7rem;padding:.55rem .8rem;font-size:.88rem;">
                <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $membre]) }}" style="font-weight:700;color:#0d9488;">{{ $membre->name }}</a>
                <span style="opacity:.65;">
                    @if($m) 💬 {{ $m->nombre }} message(s), dernier {{ \Illuminate\Support\Carbon::parse($m->dernier)->diffForHumans() }} @endif
                    @if(in_array($membre->id, $favoris)) · ❤️ en favori @endif
                </span>
            </div>
        @endforeach
    </div>
@endif
