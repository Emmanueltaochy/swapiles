<div style="display:flex;flex-direction:column;gap:.65rem;max-height:60vh;overflow-y:auto;padding:.25rem;">
    @if($current->listing)
        <div style="font-size:.82rem;opacity:.6;font-weight:600;">
            Annonce : {{ $current->listing->title }}
        </div>
    @else
        <div style="font-size:.82rem;opacity:.6;font-weight:600;">Conversation générale</div>
    @endif

    @php $firstSenderId = $messages->first()?->sender_id; @endphp

    @forelse($messages as $msg)
        @php $isFirstSide = $msg->sender_id === $firstSenderId; @endphp
        <div style="display:flex;{{ $isFirstSide ? 'justify-content:flex-start' : 'justify-content:flex-end' }};">
            <div style="max-width:78%;border-radius:1rem;padding:.6rem .85rem;
                        background:{{ $isFirstSide ? 'rgba(148,163,184,.15)' : 'rgba(13,148,136,.15)' }};">
                <div style="font-size:.72rem;font-weight:800;opacity:.7;margin-bottom:.15rem;">
                    {{ $msg->sender?->name ?? $msg->sender?->email ?? 'Membre' }}
                    <span style="font-weight:600;opacity:.6;">→ {{ $msg->receiver?->name ?? $msg->receiver?->email ?? 'Membre' }}</span>
                </div>
                <div style="white-space:pre-wrap;word-break:break-word;font-size:.9rem;">{{ $msg->body }}</div>
                <div style="font-size:.68rem;opacity:.5;margin-top:.25rem;text-align:right;">
                    {{ optional($msg->created_at)->format('d/m/Y H:i') }}
                    @if($msg->id === $current->id) · <strong>message sélectionné</strong> @endif
                    @if(is_null($msg->read_at)) · non lu @endif
                </div>
            </div>
        </div>
    @empty
        <p style="opacity:.6;">Aucun message dans ce fil.</p>
    @endforelse
</div>
