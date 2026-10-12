@php
    $t = $getRecord();
    $argent = \App\Support\FicheTransaction::argent($t);
    $etape = \App\Support\FicheTransaction::etape($t);
    $alertes = \App\Support\FicheTransaction::alertes($t);
    $e = fn ($m) => \App\Support\TableauDeBord::euros((float) $m);
    $fiab = \App\Models\Transaction::FIABILITES[$t->fiabilite()];
    $couleurs = [
        'success' => ['#047857', 'rgba(16,185,129,.12)'],
        'warning' => ['#b45309', 'rgba(245,158,11,.14)'],
        'danger' => ['#b91c1c', 'rgba(239,68,68,.12)'],
        'info' => ['#1d4ed8', 'rgba(59,130,246,.12)'],
        'gray' => ['#4b5563', 'rgba(148,163,184,.18)'],
    ];
    $pastille = fn (string $c) => 'color:' . $couleurs[$c][0] . ';background:' . $couleurs[$c][1];
    $mode = match ($t->delivery_method) {
        'colissimo' => '📦 Colissimo',
        'relay' => '🏪 Point relais',
        'hand_delivery' => '🤝 Main propre',
        default => $t->delivery_method ?: '—',
    };
@endphp

<style>
    .tx-kpis{display:grid;gap:.75rem;grid-template-columns:repeat(5,minmax(0,1fr))}
    @media(max-width:1200px){.tx-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:640px){.tx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
    .tx-carte{border:1px solid rgba(148,163,184,.25);border-radius:.9rem;padding:.85rem 1rem;background:rgba(255,255,255,.02)}
    .tx-l{font-size:.72rem;font-weight:700;opacity:.55}
    .tx-v{font-size:1.35rem;font-weight:800;margin-top:.2rem;line-height:1.2}
    .tx-s{font-size:.75rem;opacity:.6;margin-top:.2rem}
    .tx-pastille{display:inline-block;font-size:.75rem;font-weight:700;padding:.18rem .6rem;border-radius:999px}
    .tx-lignes{display:flex;flex-direction:column}
    .tx-ligne{display:flex;justify-content:space-between;gap:1rem;padding:.42rem 0;border-bottom:1px solid rgba(148,163,184,.14);font-size:.87rem}
    .tx-ligne:last-child{border-bottom:0}
    .tx-ligne>span:first-child{opacity:.65}
    .tx-ligne>span:last-child{font-weight:600;text-align:right}
    .tx-lien{color:#0d9488;font-weight:700}
    .tx-alerte{display:flex;gap:.6rem;align-items:flex-start;border-radius:.75rem;padding:.6rem .85rem;font-size:.87rem;font-weight:600}
</style>

<div style="display:flex;flex-direction:column;gap:.9rem;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;">
        <span class="tx-pastille" style="{{ $pastille($etape['couleur']) }}">● {{ $etape['libelle'] }}</span>
        @if($etape['depuis'])<span style="font-size:.8rem;opacity:.6;">depuis {{ $etape['depuis']->diffForHumans(null, true) }} ({{ $etape['depuis']->format('d/m/Y à H:i') }})</span>@endif
        <span class="tx-pastille" style="{{ $pastille($fiab[1]) }}" title="{{ $fiab[2] }}">{{ $fiab[0] }}</span>
        <span class="tx-pastille" style="{{ $pastille('gray') }}">{{ $mode }}</span>
    </div>

    @foreach($alertes as $a)
        <div class="tx-alerte" style="{{ $pastille($a['niveau']) }}">
            <span>{{ ['danger' => '⛔', 'warning' => '⚠️', 'info' => 'ℹ️'][$a['niveau']] ?? '•' }}</span><span>{{ $a['texte'] }}</span>
        </div>
    @endforeach

    <div class="tx-kpis">
        <div class="tx-carte">
            <div class="tx-l">Payé par l'acheteur</div>
            <div class="tx-v">{{ $e($argent['total_acheteur']) }}</div>
            <div class="tx-s">{{ $argent['en_ligne'] ? 'par carte, via Swap’Îles' : 'déclaré, hors Swap’Îles' }}</div>
        </div>
        <div class="tx-carte">
            <div class="tx-l">Pour le vendeur</div>
            <div class="tx-v">{{ $e($argent['pour_le_vendeur']) }}</div>
            <div class="tx-s">{{ $t->transferred_at || $t->released_at ? 'versé' : ($argent['en_ligne'] ? 'pas encore versé' : 'réglé de la main à la main') }}</div>
        </div>
        <div class="tx-carte">
            <div class="tx-l">Revenu Swap'Îles</div>
            <div class="tx-v" style="color:#0d9488;">{{ $e($argent['revenu_swapiles']) }}</div>
            <div class="tx-s">{{ $argent['en_ligne'] ? 'marge estimée ' . $e($argent['marge_estimee']) . ' après Stripe' : 'aucun frais : vente hors Swap’Îles' }}</div>
        </div>
        <div class="tx-carte">
            <div class="tx-l">{{ $argent['en_ligne'] ? 'Acheté le' : 'Déclaré le' }}</div>
            <div class="tx-v" style="font-size:1.05rem;">{{ $t->created_at?->format('d/m/Y') }}</div>
            <div class="tx-s">à {{ $t->created_at?->format('H:i') }} · {{ $t->created_at?->diffForHumans() }}</div>
        </div>
        <div class="tx-carte">
            <div class="tx-l">Île</div>
            <div class="tx-v" style="font-size:1.05rem;">{{ $t->listing->territoire ?? $t->seller->territoire ?? '—' }}</div>
            <div class="tx-s">vente n° {{ $t->id }}</div>
        </div>
    </div>
</div>
