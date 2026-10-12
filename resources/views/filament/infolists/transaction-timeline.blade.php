@php
    $t = $getRecord();
    $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y à H:i') : null;
    $paye = in_array($t->status, ['paid', 'completed'], true) || $t->stripe_payment_intent_id;

    $etapes = [];
    if ($t->estDeclaree()) {
        $etapes[] = ['📝', 'Vente déclarée', $t->created_at, 'Déclarée par le vendeur, hors paiement Swap’Îles'];
        if ($t->acheteur_confirme_at) {
            $etapes[] = ['✅', "Confirmée par l'acheteur", $t->acheteur_confirme_at, null];
        } elseif ($t->acheteur_conteste_at) {
            $etapes[] = ['⛔', "Contestée par l'acheteur", $t->acheteur_conteste_at, null];
        } elseif ($t->buyer_id) {
            $etapes[] = ['⏳', "Confirmation de l'acheteur", null, 'en attente'];
        }
    } else {
        $etapes[] = ['🛒', 'Commande passée', $t->created_at, "Achat initié par l'acheteur"];
        $etapes[] = ['💳', 'Paiement confirmé', $paye ? $t->created_at : null, 'Carte bancaire (Stripe)'];

        if ($t->delivery_method === 'hand_delivery') {
            $etapes[] = ['🤝', 'Remise en main propre', $t->received_at ?? ($t->status === 'completed' ? $t->completed_at : null), $t->hand_delivery_location];
        } elseif ($t->delivery_method === 'relay') {
            $etapes[] = ['🏪', 'Déposé au point relais', $t->relay_deposited_at, $t->relayPoint?->name];
            $etapes[] = ['🙋', "Retiré par l'acheteur", $t->relay_collected_at ?? $t->received_at, null];
        } else {
            $suivi = trim(($t->carrier ? $t->carrier . ' ' : '') . ($t->tracking_number ? '· ' . $t->tracking_number : ''));
            $etapes[] = ['📦', 'Expédié', $t->shipped_at, $suivi ?: null];
            $etapes[] = ['🚚', 'Livré', $t->delivered_at, null];
            $etapes[] = ['✅', 'Réception confirmée', $t->received_at, "par l'acheteur"];
        }

        $etapes[] = ['💶', 'Versement au vendeur', $t->transferred_at ?? $t->released_at, null];
    }

    $annulee = in_array($t->status, ['cancelled', 'refunded'], true);
@endphp

<div>
    @if($annulee)
        <div style="margin-bottom:.8rem;border-radius:.7rem;padding:.6rem .85rem;font-size:.87rem;font-weight:700;color:#b91c1c;background:rgba(239,68,68,.12);">
            {{ $t->status === 'refunded' ? '↩️ Vente remboursée' : '✖️ Vente annulée' }}
        </div>
    @endif

    @foreach($etapes as $i => [$icone, $libelle, $date, $note])
        @php $fait = $date !== null; $derniere = $i === count($etapes) - 1; @endphp
        <div style="display:flex;gap:.8rem;">
            <div style="display:flex;flex-direction:column;align-items:center;">
                <div style="width:2rem;height:2rem;flex:none;border-radius:999px;display:flex;align-items:center;justify-content:center;font-size:.9rem;{{ $fait ? 'background:rgba(16,185,129,.16);' : 'background:rgba(148,163,184,.14);filter:grayscale(1);opacity:.55;' }}">{{ $icone }}</div>
                @unless($derniere)<div style="width:2px;flex:1;min-height:1.2rem;background:{{ $fait ? 'rgba(16,185,129,.45)' : 'rgba(148,163,184,.3)' }};"></div>@endunless
            </div>
            <div style="padding-bottom:{{ $derniere ? '0' : '.9rem' }};padding-top:.25rem;">
                <div style="font-weight:700;font-size:.9rem;{{ $fait ? '' : 'opacity:.55;' }}">{{ $libelle }}</div>
                <div style="font-size:.78rem;opacity:.6;">
                    {{ $fait ? $fmt($date) : 'à venir' }}@if($note) · {{ $note }}@endif
                </div>
            </div>
        </div>
    @endforeach
</div>
