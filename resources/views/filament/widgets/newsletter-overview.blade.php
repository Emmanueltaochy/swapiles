<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">📊 Vue d'ensemble — toutes les campagnes</x-slot>

        @php $n = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

        @if($empty ?? true)
            <p style="opacity:.6;font-size:.9rem;">Aucune campagne envoyée pour l'instant. Les statistiques globales apparaîtront après votre premier envoi.</p>
        @else
            <style>
                .swp-nlo{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr));}
                @media(max-width:1024px){.swp-nlo{grid-template-columns:repeat(2,minmax(0,1fr));}}
                @media(max-width:560px){.swp-nlo{grid-template-columns:1fr;}}
                .swp-nlo .c{border-radius:1rem;padding:1rem 1.1rem;}
                .swp-nlo .l{font-size:.74rem;font-weight:700;opacity:.6;}
                .swp-nlo .v{font-size:1.55rem;font-weight:900;line-height:1.15;margin-top:.2rem;}
                .swp-nlo .s{font-size:.72rem;font-weight:600;opacity:.5;margin-top:.1rem;}
            </style>
            <div class="swp-nlo">
                <div class="c" style="background:rgba(148,163,184,.12);">
                    <div class="l">📨 Campagnes envoyées</div>
                    <div class="v">{{ $n($campaigns) }}</div>
                    <div class="s">{{ $n($totalSent) }} e-mails au total</div>
                </div>
                <div class="c" style="background:rgba(13,148,136,.14);">
                    <div class="l">📬 Taux d'ouverture moyen</div>
                    <div class="v">{{ number_format($avgOpenRate, 1, ',', ' ') }}%</div>
                    <div class="s">{{ $n($uniqueOpens) }} ouvertures uniques</div>
                </div>
                <div class="c" style="background:rgba(245,158,11,.14);">
                    <div class="l">🖱️ Taux de clic moyen</div>
                    <div class="v">{{ number_format($avgClickRate, 1, ',', ' ') }}%</div>
                    <div class="s">{{ $n($uniqueClicks) }} clics uniques</div>
                </div>
                <div class="c" style="background:rgba(59,130,246,.12);">
                    <div class="l">🔁 Engagement total</div>
                    <div class="v">{{ $n($totalOpens) }}</div>
                    <div class="s">ouvertures · {{ $n($totalClicks) }} clics</div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
