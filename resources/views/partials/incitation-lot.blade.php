{{--
    « Vendez en lot » : au bon moment, le bon message (paiement en ligne à
    activer, remise à proposer, ou remise déjà active).
--}}
@php $incitation = \App\Support\IncitationLot::pour($vendeur); @endphp
@if($incitation)
    @if($incitation['etat'] === 'activer_cb')
        <div class="rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-50 to-emerald-50 p-4">
            <p class="font-bold text-gray-900">🛍️ Vendez plusieurs articles d’un coup</p>
            <p class="mt-1 text-sm text-gray-700">
                Activez le <strong>paiement en ligne</strong> sur vos annonces : vos acheteurs pourront les prendre <strong>en lot</strong>,
                avec une seule livraison et votre <strong>remise</strong>. Mettez toutes les chances de votre côté pour vendre !
            </p>
            <form method="POST" action="{{ route('account.paiement-en-ligne.activer') }}" class="mt-3">
                @csrf
                <button class="w-full rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-700 sm:w-auto">
                    Activer sur mes {{ $incitation['annonces'] }} annonce{{ $incitation['annonces'] > 1 ? 's' : '' }}
                </button>
            </form>
            <p class="mt-2 text-xs text-gray-500">Paiement protégé, sans commission. La remise en main propre reste possible.</p>
        </div>
    @elseif($incitation['etat'] === 'proposer_remise')
        <div class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-amber-50 p-4">
            <p class="font-bold text-gray-900">🎁 Proposez une remise lot</p>
            <p class="mt-1 text-sm text-gray-700">
                Par exemple <strong>−10 % dès 2 articles</strong> : les acheteurs ajoutent plus d’articles de votre dressing,
                et vous ne préparez qu’<strong>un seul envoi</strong>.
            </p>
            <a href="{{ route('account.remise-lot.edit') }}" class="mt-3 inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 sm:w-auto">Choisir ma remise</a>
        </div>
    @else
        <a href="{{ route('account.remise-lot.edit') }}" class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 text-sm shadow-sm hover:bg-gray-50">
            <span>🎁 <strong>Remise lot active</strong> : −{{ $vendeur->lot_remise_pct }} % dès {{ max(2, (int) $vendeur->lot_remise_min) }} articles</span>
            <span class="shrink-0 font-semibold text-teal-700">Modifier</span>
        </a>
    @endif
@endif
