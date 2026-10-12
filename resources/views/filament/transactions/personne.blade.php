@php
    $t = $getRecord();
    $membre = $role === 'acheteur' ? $t->buyer : $t->seller;
    $p = $membre ? \App\Support\FicheTransaction::profil($membre) : null;
@endphp

@if(! $membre)
    <p style="font-size:.9rem;font-weight:700;">
        @if($role === 'acheteur' && $t->buyer_full_name) {{ $t->buyer_full_name }} <span style="opacity:.55;font-weight:400;">(sans compte)</span>
        @elseif($role === 'acheteur' && $t->payment_method === 'especes') Inconnu
        @else Compte supprimé
        @endif
    </p>
    @if($role === 'acheteur' && $t->payment_method === 'especes')
        <p style="font-size:.8rem;opacity:.6;margin-top:.25rem;">Le vendeur a indiqué une vente hors Swap'Îles.</p>
    @endif
@else
    <div style="display:flex;align-items:center;gap:.7rem;">
        <div style="width:2.6rem;height:2.6rem;border-radius:999px;background:rgba(13,148,136,.15);color:#0f766e;display:flex;align-items:center;justify-content:center;font-weight:800;flex:none;overflow:hidden;">
            @if($membre->avatar)<img src="{{ $membre->avatar }}" alt="" style="width:100%;height:100%;object-fit:cover;">@else{{ mb_strtoupper(mb_substr($membre->name ?? '?', 0, 1)) }}@endif
        </div>
        <div style="min-width:0;">
            <a class="tx-lien" href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $membre]) }}">{{ $membre->name }}</a>
            <div style="font-size:.78rem;opacity:.6;">{{ $membre->territoire ?: '—' }}@if($membre->city) · {{ $membre->city }}@endif</div>
        </div>
    </div>

    <div class="tx-lignes" style="margin-top:.6rem;">
        <div class="tx-ligne"><span>E-mail</span><span><a class="tx-lien" href="mailto:{{ $membre->email }}">{{ $membre->email }}</a></span></div>
        @php $tel = $role === 'acheteur' ? ($t->buyer_phone ?: $membre->phone) : $membre->phone; @endphp
        <div class="tx-ligne"><span>Téléphone</span><span>@if($tel)<a class="tx-lien" href="tel:{{ $tel }}">{{ $tel }}</a>@else — @endif</span></div>
        <div class="tx-ligne"><span>Membre depuis</span><span>{{ $p['membre_depuis']?->locale('fr')->translatedFormat('F Y') ?? '—' }}</span></div>
        <div class="tx-ligne"><span>Achats · ventes</span><span>{{ $p['achats'] }} · {{ $p['ventes'] }}</span></div>
        <div class="tx-ligne"><span>Note</span><span>{{ $p['note'] !== null ? number_format($p['note'], 1, ',', ' ') . ' / 5 (' . $p['nb_avis'] . ' avis)' : 'aucun avis' }}</span></div>
        @if($p['remboursements'] > 0)
            <div class="tx-ligne"><span>Ventes remboursées</span><span style="color:#b91c1c;">{{ $p['remboursements'] }}</span></div>
        @endif
        @if($role === 'vendeur')
            @php $stripeOk = $membre->stripe_account_id && $membre->stripe_charges_enabled && $membre->stripe_payouts_enabled; @endphp
            <div class="tx-ligne"><span>Compte de paiement</span><span style="color:{{ $stripeOk ? '#047857' : '#b45309' }};">{{ $stripeOk ? 'configuré' : 'non configuré' }}</span></div>
        @endif
        @if($membre->is_banned)
            <div class="tx-ligne"><span>Compte</span><span style="color:#b91c1c;">banni</span></div>
        @endif
    </div>
@endif
