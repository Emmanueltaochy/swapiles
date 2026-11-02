<x-filament-panels::page>
    @php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €'; @endphp
    <style>
        .vp-ligne{display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid rgba(148,163,184,.15);font-size:.88rem;}
        .vp-ligne:last-child{border-bottom:0;}
        .vp-s{font-size:.75rem;opacity:.65;}
        .vp-nav{display:flex;align-items:center;gap:.75rem;font-weight:800;}
        .vp-nav a{padding:.25rem .7rem;border-radius:.6rem;background:rgba(148,163,184,.15);}
    </style>

    <x-filament::section>
        <x-slot name="heading">
            <span class="vp-nav">
                <a href="?mois={{ $precedent }}">‹</a>
                {{ ucfirst($debut->locale('fr')->translatedFormat('F Y')) }}
                @if($suivant)<a href="?mois={{ $suivant }}">›</a>@endif
            </span>
        </x-slot>
        @forelse($lignes as $l)
            <div class="vp-ligne">
                <div>
                    <div><strong>{{ $l['pro']?->pro_raison_sociale ?? $l['pro']?->name ?? 'Pro supprimé' }}</strong></div>
                    <div class="vp-s">{{ $l['nombre'] }} vente(s) · CA TTC {{ $eur($l['chiffre']) }} · lui revient {{ $eur($l['net']) }} (dont {{ $eur($l['verse']) }} versés)</div>
                </div>
                <div style="text-align:right">
                    <div style="font-weight:900">{{ $eur($l['commission']) }}</div>
                    <div class="vp-s">commission à facturer</div>
                </div>
            </div>
        @empty
            <p class="vp-s">Aucune vente de professionnel ce mois-ci.</p>
        @endforelse
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Comptes pros actifs ({{ $pros->count() }})</x-slot>
        @forelse($pros as $p)
            <div class="vp-ligne">
                <div>
                    <div><strong>{{ $p->pro_raison_sociale }}</strong> — {{ $p->name }}</div>
                    <div class="vp-s">SIRET {{ \App\Support\ComptePro::siret($p->pro_siret) }} · commission {{ str_replace('.', ',', round(\App\Support\ComptePro::taux($p) * 100, 2)) }} %
                        · {{ \App\Support\ComptePro::retraitBoutique($p) ? 'retrait boutique' : 'pas de retrait' }}
                        · {{ \App\Support\ComptePro::livraison($p) ? 'livraison ' . (\App\Support\ComptePro::prixLivraison($p) > 0 ? $eur(\App\Support\ComptePro::prixLivraison($p)) : 'offerte') : 'pas de livraison' }}
                        · lives {{ $p->live_autorise ? 'oui' : 'non' }}</div>
                </div>
                <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $p]) }}" class="vp-s" style="font-weight:700">Fiche ›</a>
            </div>
        @empty
            <p class="vp-s">Aucun compte pro. Pour en créer un : Membres → la fiche du membre → « Compte pro / lives ».</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
