@php
    $t = $getRecord();
    $a = \App\Support\FicheTransaction::argent($t);
    $e = fn ($m) => \App\Support\TableauDeBord::euros((float) $m);
    $versement = match ($t->wallet_status) {
        'paid' => 'versé',
        'processing' => 'en cours',
        'cancelled' => 'annulé',
        default => $a['en_ligne'] ? 'en attente' : 'sans objet',
    };
@endphp

<div class="tx-lignes">
    <div class="tx-ligne"><span>Prix de l'article</span><span>{{ $e($a['prix_article']) }}</span></div>
    <div class="tx-ligne"><span>Protection acheteur</span><span>{{ $e($a['protection']) }}</span></div>
    @if(($a['protection_offerte'] ?? 0) > 0)
        <div class="tx-ligne"><span>🎁 Protection offerte (parrainage)</span><span>{{ $e($a['protection_offerte']) }} non facturés</span></div>
    @endif
    <div class="tx-ligne"><span>Livraison</span><span>{{ $e($a['livraison']) }}</span></div>
    @if($a['frais_relais'] > 0)
        <div class="tx-ligne"><span>Frais point relais</span><span>{{ $e($a['frais_relais']) }}</span></div>
    @endif
    @if(($a['credit_utilise'] ?? 0) > 0)
        <div class="tx-ligne"><span>💚 Crédit Swap'Îles utilisé (payé par Swap'Îles)</span><span>− {{ $e($a['credit_utilise']) }}</span></div>
    @endif
    <div class="tx-ligne" style="font-size:.95rem;"><span style="opacity:1;font-weight:800;">Payé par l'acheteur</span><span style="font-weight:800;">{{ $e($a['total_acheteur']) }}</span></div>
</div>

<div class="tx-l" style="margin-top:1rem;">Répartition</div>
<div class="tx-lignes">
    <div class="tx-ligne"><span>Pour le vendeur</span><span>{{ $e($a['pour_le_vendeur']) }}</span></div>
    @if($a['part_commercant'] > 0)
        <div class="tx-ligne"><span>Pour le commerçant relais</span><span>{{ $e($a['part_commercant']) }}</span></div>
    @endif
    @if($a['livraison'] > 0)
        <div class="tx-ligne"><span>Transport</span><span>{{ $e($a['livraison']) }}</span></div>
    @endif
    <div class="tx-ligne"><span>Revenu Swap'Îles</span><span style="color:#0d9488;">{{ $e($a['revenu_swapiles']) }}</span></div>
    @if($a['en_ligne'])
        <div class="tx-ligne"><span>Frais Stripe (estimés)</span><span>− {{ $e($a['frais_stripe_estimes']) }}</span></div>
        <div class="tx-ligne"><span style="opacity:1;font-weight:800;">Marge estimée</span><span style="font-weight:800;color:{{ $a['marge_estimee'] < 0 ? '#b91c1c' : '#047857' }};">{{ $e($a['marge_estimee']) }}</span></div>
    @endif
</div>

@if($a['en_ligne'])
    <div class="tx-l" style="margin-top:1rem;">Versement au vendeur</div>
    <div class="tx-lignes">
        <div class="tx-ligne"><span>État</span><span>{{ $versement }}</span></div>
        @if($t->estimated_payout_date)<div class="tx-ligne"><span>Prévu le</span><span>{{ \Illuminate\Support\Carbon::parse($t->estimated_payout_date)->format('d/m/Y') }}</span></div>@endif
        @if($t->transferred_at)<div class="tx-ligne"><span>Versé le</span><span>{{ \Illuminate\Support\Carbon::parse($t->transferred_at)->format('d/m/Y') }}</span></div>@endif
        @if($t->stripe_payment_intent_id)<div class="tx-ligne"><span>Paiement Stripe</span><span style="font-family:monospace;font-size:.75rem;">{{ $t->stripe_payment_intent_id }}</span></div>@endif
        @if($t->stripe_transfer_id)<div class="tx-ligne"><span>Virement Stripe</span><span style="font-family:monospace;font-size:.75rem;">{{ $t->stripe_transfer_id }}</span></div>@endif
    </div>
@endif
