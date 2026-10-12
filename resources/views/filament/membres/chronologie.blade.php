<style>
    .hm-fil{display:flex;flex-direction:column;max-height:640px;overflow:auto;padding-right:.3rem}
    .hm-evt{display:flex;gap:.75rem;padding:.55rem 0;border-bottom:1px solid rgba(148,163,184,.15)}
    .hm-evt:last-child{border-bottom:0}
    .hm-ico{width:1.9rem;height:1.9rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:999px;background:rgba(148,163,184,.14);font-size:.95rem}
    .hm-jour{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;opacity:.5;margin:.9rem 0 .2rem}
    .hm-jour:first-child{margin-top:0}
</style>

<div class="hm-fil">
    @php $jourPrecedent = null; @endphp
    @forelse($evenements as $evt)
        @php $jour = ucfirst($evt['date']->locale('fr')->translatedFormat('l j F Y')); @endphp
        @if($jour !== $jourPrecedent)
            <div class="hm-jour">{{ $jour }}</div>
            @php $jourPrecedent = $jour; @endphp
        @endif
        <div class="hm-evt">
            <div class="hm-ico">{{ $evt['icone'] }}</div>
            <div style="min-width:0;flex:1;">
                <div style="font-size:.88rem;">
                    @if($evt['lien'])
                        <a href="{{ $evt['lien'] }}" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-decoration-color:rgba(148,163,184,.6);">{{ $evt['texte'] }}</a>
                    @else
                        {{ $evt['texte'] }}
                    @endif
                </div>
                <div style="font-size:.72rem;opacity:.5;">{{ $evt['date']->format('H:i') }}</div>
            </div>
        </div>
    @empty
        <p style="font-size:.85rem;opacity:.6;">Aucune activité enregistrée.</p>
    @endforelse
</div>
