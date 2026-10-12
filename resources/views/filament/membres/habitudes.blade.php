@php
    $h = $habitudes;
    $e = fn ($m) => \App\Support\TableauDeBord::euros((float) $m);
@endphp
<style>
    .hm-grille{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr))}
    @media(max-width:1024px){.hm-grille{grid-template-columns:repeat(2,minmax(0,1fr))}}
    .hm-case{border:1px solid rgba(148,163,184,.25);border-radius:.75rem;padding:.75rem .85rem}
    .hm-l{font-size:.72rem;font-weight:700;opacity:.55}
    .hm-v{font-size:1.05rem;font-weight:800;margin-top:.2rem}
    .hm-s{font-size:.75rem;opacity:.6;margin-top:.15rem}
    .hm-titre{font-size:.8rem;font-weight:800;margin:1.1rem 0 .5rem}
    .hm-chip{display:inline-block;font-size:.78rem;font-weight:600;padding:.2rem .6rem;border-radius:999px;background:rgba(13,148,136,.12);color:#0f766e;margin:0 .3rem .35rem 0}
    .dark .hm-chip{color:#5eead4}
    .hm-bar{height:.4rem;border-radius:999px;background:rgba(148,163,184,.2);overflow:hidden;margin-top:.25rem}
    .hm-bar>div{height:100%;background:#0d9488;border-radius:999px}
</style>

<div class="hm-grille">
    <div class="hm-case">
        <div class="hm-l">Dernière activité</div>
        <div class="hm-v">{{ $h['derniere_activite'] ? $h['derniere_activite']->diffForHumans() : '—' }}</div>
        <div class="hm-s">{{ $h['jours_actifs'] }} jour(s) actif(s) · {{ $h['pages_vues'] }} page(s) vue(s)</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Quand vient-il ?</div>
        <div class="hm-v">{{ $h['moment_prefere'] ? ucfirst($h['moment_prefere']) : '—' }}</div>
        <div class="hm-s">{{ $h['jour_prefere'] ? 'surtout le ' . $h['jour_prefere'] : 'pas encore assez de données' }} · heure de son île</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Appareil</div>
        <div class="hm-v">{{ $h['appareil'] ?? '—' }}</div>
        <div class="hm-s">{{ $h['favoris'] }} favori(s) · {{ $h['messages_envoyes'] }} message(s) envoyé(s)</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Budget habituel</div>
        <div class="hm-v">{{ $h['budget'] ? $e($h['budget']['median']) : '—' }}</div>
        <div class="hm-s">{{ $h['budget'] ? 'le plus souvent entre ' . $e($h['budget']['min']) . ' et ' . $e($h['budget']['max']) : 'selon ses favoris et consultations' }}</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Achats</div>
        <div class="hm-v">{{ $h['achats']['nombre'] }} · {{ $e($h['achats']['montant']) }}</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Ventes</div>
        <div class="hm-v">{{ $h['ventes']['nombre'] }} · {{ $e($h['ventes']['montant']) }}</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Annonces en ligne</div>
        <div class="hm-v">{{ $h['annonces_en_ligne'] }}</div>
    </div>
    <div class="hm-case">
        <div class="hm-l">Profil</div>
        <div class="hm-v">
            @if($h['ventes']['nombre'] > 0 && $h['achats']['nombre'] > 0) Acheteur et vendeur
            @elseif($h['ventes']['nombre'] > 0 || $h['annonces_en_ligne'] > 0) Plutôt vendeur
            @elseif($h['achats']['nombre'] > 0 || $h['favoris'] > 0) Plutôt acheteur
            @else Curieux
            @endif
        </div>
    </div>
</div>

<div class="hm-titre">Ce qui l'intéresse (favoris et annonces regardées)</div>
@forelse($h['categories'] as $c)
    <div style="margin-bottom:.45rem;">
        <div style="display:flex;justify-content:space-between;font-size:.85rem;font-weight:600;"><span>{{ $c['libelle'] }}</span><span>{{ $c['part'] }} %</span></div>
        <div class="hm-bar"><div style="width:{{ $c['part'] }}%"></div></div>
    </div>
@empty
    <p style="font-size:.85rem;opacity:.6;">Pas encore de favori ni d'annonce regardée.</p>
@endforelse

@if($h['marques'])
    <div class="hm-titre">Marques</div>
    @foreach($h['marques'] as $marque)<span class="hm-chip">{{ ucfirst($marque) }}</span>@endforeach
@endif

@if($h['recherches'])
    <div class="hm-titre">Dernières recherches</div>
    @foreach($h['recherches'] as $r)<span class="hm-chip">🔍 {{ $r }}</span>@endforeach
@endif

<p style="font-size:.72rem;opacity:.5;margin-top:1rem;">Usage interne uniquement (support, modération, amélioration du service). Le contenu des messages privés n'est jamais affiché.</p>
