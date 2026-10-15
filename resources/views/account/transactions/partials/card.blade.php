@php
    $isSale = $type === 'sale';
    $isPurchase = $type === 'purchase';

    $status = $transaction->status;
    $shipping = $transaction->shipping_status;

    $stepPayment = in_array($status, ['paid', 'completed']);
    $stepShipped = in_array($shipping, ['shipped', 'received']);
    $stepReceived = $shipping === 'received';
    $stepPaidOut = ($transaction->wallet_status ?? null) === 'paid';

    if ($status === 'cancelled') {
        $badgeClass = 'bg-red-100 text-red-700';
        $badgeLabel = 'Annulée';
    } elseif ($status === 'completed') {
        $badgeClass = 'bg-green-100 text-green-700';
        $badgeLabel = 'Terminée';
    } elseif ($shipping === 'shipped') {
        $badgeClass = 'bg-blue-100 text-blue-700';
        $badgeLabel = 'En livraison';
    } elseif ($status === 'paid' && $shipping === 'pending') {
        $badgeClass = 'bg-orange-100 text-orange-700';
        $badgeLabel = $isSale ? 'À expédier' : 'En préparation';
    } else {
        $badgeClass = 'bg-gray-100 text-gray-700';
        $badgeLabel = 'En attente';
    }
@endphp

<div class="border border-gray-100 rounded-3xl p-3.5 mb-3 hover:bg-gray-50 transition">
    <a href="{{ route('account.transactions.show', $transaction) }}" class="block">
        <div class="flex gap-3">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                @if($transaction->listing?->images?->first())
                    <img src="{{ $transaction->listing->images->first()->url }}" alt="{{ $transaction->listing->title }}" class="w-full h-full object-cover" loading="lazy">
                @else
                    <div class="w-full h-full flex items-center justify-center text-2xl text-gray-300">📦</div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <p class="font-extrabold text-gray-900 truncate">{{ $transaction->listing->title ?? 'Annonce supprimée' }}</p>
                <p class="text-sm text-gray-500 truncate">
                    {{ $isPurchase ? 'Vendeur' : 'Acheteur' }} · {{ $isPurchase ? ($transaction->seller->name ?? '—') : ($transaction->buyer->name ?? $transaction->buyer_full_name ?? '—') }}
                </p>
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <p class="font-extrabold text-gray-900">{{ number_format($transaction->amount, 2, ',', ' ') }} €</p>
                    <span class="shrink-0 whitespace-nowrap text-xs font-extrabold rounded-full px-2.5 py-1 {{ $badgeClass }}">{{ $badgeLabel }}</span>
                </div>
            </div>
        </div>

        {{-- Les 4 étapes sur une seule ligne : barre + libellés. --}}
        @php
            $etapes = ['Paiement' => $stepPayment, 'Expédié' => $stepShipped, 'Reçu' => $stepReceived, 'Virement' => $stepPaidOut];
        @endphp
        <div class="mt-3 grid grid-cols-4 gap-1" aria-label="Étapes de la vente">
            @foreach($etapes as $libelle => $fait)
                <div>
                    <div class="h-1.5 rounded-full {{ $fait ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                    <p class="mt-1 text-center text-[11px] font-bold {{ $fait ? 'text-green-700' : 'text-gray-400' }}">{{ $libelle }}</p>
                </div>
            @endforeach
        </div>
    </a>

    <div class="mt-3 flex flex-wrap gap-2 empty:hidden">
        @if($isSale && $transaction->status === 'paid')
            @if($transaction->delivery_method === 'colissimo')
                @if(!$transaction->colissimo_label_path)
                    <form method="POST" action="{{ route('account.colissimo.generate', $transaction) }}">
                        @csrf
                        <button class="bg-blue-700 hover:bg-blue-800 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                            🏷️ Générer le bordereau Colissimo
                        </button>
                    </form>
                @else
                    <a href="{{ route('account.colissimo.download', $transaction) }}"
                       class="bg-gray-900 hover:bg-black text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                        🖨️ Voir / imprimer le bordereau
                    </a>

                    @if($transaction->shipping_status === 'pending')
                        <form method="POST" action="{{ route('transactions.shipped', $transaction) }}">
                            @csrf
                            @method('PATCH')
                            <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                                📦 Marquer comme expédié
                            </button>
                        </form>
                    @endif
                @endif
            @elseif($transaction->shipping_status === 'pending')
                <form method="POST" action="{{ route('transactions.shipped', $transaction) }}">
                    @csrf
                    @method('PATCH')
                    <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                        📦 J’ai déposé le colis
                    </button>
                </form>
            @endif
        @endif

        @if($isPurchase && $transaction->shipping_status === 'shipped' && $transaction->status === 'paid')
            <form method="POST" action="{{ route('transactions.received', $transaction) }}">
                @csrf
                @method('PATCH')
                <button class="bg-emerald-600 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                    ✅ Confirmer réception
                </button>
            </form>
        @endif
    </div>
</div>
