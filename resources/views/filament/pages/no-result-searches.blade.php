<x-filament-panels::page>
    @php
        $date = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
        $pastille = 'display:inline-flex;align-items:center;border-radius:999px;padding:.2rem .6rem;font-size:.8rem;font-weight:600;';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <x-filament::tabs>
            <x-filament::tabs.item :active="$onglet === 'toutes'" wire:click="$set('onglet', 'toutes')" icon="heroicon-m-magnifying-glass">
                Toutes les recherches
                <x-slot name="badge">{{ $nbRecherches }}</x-slot>
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$onglet === 'introuvables'" wire:click="$set('onglet', 'introuvables')" icon="heroicon-m-question-mark-circle">
                Introuvables sur la plateforme
                <x-slot name="badge">{{ $nbIntrouvables }}</x-slot>
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if($onglet === 'toutes')
            <p style="font-size:.85rem;opacity:.7;line-height:1.5;">
                Ce que les visiteurs tapent dans la recherche, du plus cherché au moins cherché. Les articles les plus demandés :
                à mettre en avant, ou à faire déposer par les vendeurs.
            </p>
            @forelse($toutes as $r)
                <x-filament::section compact>
                    <p style="font-weight:800;font-size:1rem;word-break:break-word;">« {{ $r->term }} »</p>
                    <div style="margin-top:.5rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;">
                        <span style="{{ $pastille }}background:rgba(13,148,136,.15);color:#0f766e;">🔁 {{ $r->total }} recherche{{ $r->total > 1 ? 's' : '' }}</span>
                        <span style="{{ $pastille }}background:rgba(148,163,184,.18);">👤 {{ $r->visitors }} visiteur{{ $r->visitors > 1 ? 's' : '' }}</span>
                        <span style="{{ $pastille }}background:rgba(99,102,241,.15);color:#4f46e5;">📦 ~{{ (int) $r->resultats }} résultat{{ $r->resultats > 1 ? 's' : '' }}</span>
                        @if($r->vides > 0)
                            <span style="{{ $pastille }}background:rgba(245,158,11,.15);color:#b45309;" title="Recherches sans résultat à cause d’un filtre (île, catégorie, prix…)">⚠️ {{ $r->vides }} fois vide</span>
                        @endif
                        <span style="font-size:.75rem;opacity:.6;">· {{ $date($r->last_seen) }}</span>
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p style="text-align:center;opacity:.7;">Les recherches apparaîtront ici dès les prochaines visites.</p>
                </x-filament::section>
            @endforelse
            <p style="font-size:.75rem;opacity:.6;line-height:1.5;">
                « ~ résultats » = nombre moyen d’annonces trouvées. « Fois vide » = le visiteur n’a rien vu, souvent à cause d’un filtre
                (une autre île, une catégorie) : l’article existe sur la plateforme.
            </p>
        @else
            <p style="font-size:.85rem;opacity:.7;line-height:1.5;">
                Ce que les visiteurs cherchent et qui <strong>n’existe nulle part</strong> sur Swap’Îles (toutes îles, sans filtre).
                C’est la demande à combler : à faire déposer par les vendeurs ou les pros.
            </p>
            @forelse($introuvables as $r)
                <x-filament::section compact>
                    <p style="font-weight:800;font-size:1rem;word-break:break-word;">« {{ $r->term }} »</p>
                    <div style="margin-top:.5rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;">
                        <span style="{{ $pastille }}background:rgba(220,38,38,.12);color:#b91c1c;">🔁 {{ $r->total }} recherche{{ $r->total > 1 ? 's' : '' }}</span>
                        <span style="{{ $pastille }}background:rgba(148,163,184,.18);">👤 {{ $r->visitors }} visiteur{{ $r->visitors > 1 ? 's' : '' }}</span>
                        <span style="font-size:.75rem;opacity:.6;">· {{ $date($r->last_seen) }}</span>
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p style="text-align:center;opacity:.7;">Rien d’introuvable sur 30 jours 🎉</p>
                </x-filament::section>
            @endforelse
            <p style="font-size:.75rem;opacity:.6;line-height:1.5;">
                Beaucoup de recherches pour peu de visiteurs = une même personne insiste ; beaucoup de visiteurs = vraie demande.
            </p>
        @endif
    </div>
</x-filament-panels::page>
