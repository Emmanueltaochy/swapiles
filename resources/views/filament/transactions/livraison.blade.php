@php
    $t = $getRecord();
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y à H:i') : '—';
    $lignes = [];
    if ($t->delivery_method === 'hand_delivery') {
        $lignes['Mode'] = '🤝 Remise en main propre';
        $lignes['Lieu de remise'] = $t->hand_delivery_location ?: '—';
        $lignes['Remis le'] = $d($t->received_at ?? $t->completed_at);
    } elseif ($t->delivery_method === 'relay') {
        $p = $t->relayPoint;
        $lignes['Mode'] = '🏪 Point relais Swap’Îles';
        $lignes['Point relais'] = $p ? $p->name . ' — ' . trim($p->address . ', ' . $p->postal_code . ' ' . $p->city, ' ,') : '—';
        $lignes['Contact du point relais'] = $p ? trim(($p->contact_name ?? '') . ' ' . ($p->contact_phone ?? '')) ?: '—' : '—';
        $lignes['Déposé par le vendeur'] = $d($t->relay_deposited_at);
        $lignes['Retiré par l’acheteur'] = $d($t->relay_collected_at);
        $lignes['Code de retrait'] = $t->relay_pickup_code ?: '—';
    } else {
        $lignes['Mode'] = '📦 Colissimo' . ($t->colissimo_delivery_type ? ' (' . ($t->colissimo_delivery_type === 'pickup' ? 'point de retrait' : 'à domicile') . ')' : '');
        if ($t->pickup_name || $t->pickup_address) {
            $lignes['Point de retrait'] = trim($t->pickup_name . ' — ' . $t->pickup_address . ', ' . $t->pickup_postal_code . ' ' . $t->pickup_city, ' —,');
        }
        if ($t->shipping_address_line1) {
            $lignes['Adresse de livraison'] = trim($t->shipping_address_line1 . ' ' . $t->shipping_address_line2 . ', ' . $t->shipping_postal_code . ' ' . $t->shipping_city . ' ' . $t->shipping_country);
        }
        $lignes['Transporteur'] = $t->carrier ?: '—';
        $lignes['Expédié le'] = $d($t->shipped_at);
        $lignes['Livré le'] = $d($t->delivered_at);
        $lignes['Réception confirmée le'] = $d($t->received_at);
    }
    if ($t->buyer_full_name || $t->buyer_phone) {
        $lignes['Destinataire'] = trim($t->buyer_full_name . ' · ' . $t->buyer_phone, ' ·');
    }
@endphp

<div class="tx-lignes">
    @foreach($lignes as $libelle => $valeur)
        <div class="tx-ligne"><span>{{ $libelle }}</span><span>{{ $valeur }}</span></div>
    @endforeach
    @if($t->tracking_number)
        <div class="tx-ligne">
            <span>Suivi</span>
            <span>
                @if($t->tracking_url)<a class="tx-lien" href="{{ $t->tracking_url }}" target="_blank" rel="noopener">{{ $t->tracking_number }} ↗</a>@else{{ $t->tracking_number }}@endif
            </span>
        </div>
    @endif
</div>
