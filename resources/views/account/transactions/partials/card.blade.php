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

<div class="border border-gray-100 rounded-3xl p-4 mb-4 hover:bg-gray-50 transition">
    <a href="{{ route('account.transactions.show', $transaction) }}" class="block">
        <div class="flex gap-4">
            <div class="w-20 h-20 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                @if($transaction->listing?->images?->first())
                    <img src="{{ $transaction->listing->images->first()->url }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-3xl text-gray-300">📦</div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-extrabold text-gray-900 truncate">{{ $transaction->listing->title ?? 'Annonce supprimée' }}</p>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $isPurchase ? 'Vendeur' : 'Acheteur' }} :
                            {{ $isPurchase ? ($transaction->seller->name ?? '-') : ($transaction->buyer->name ?? '-') }}
                        </p>
                    </div>

                    <span class="shrink-0 text-xs font-extrabold rounded-full px-3 py-1 {{ $badgeClass }}">
                        {{ $badgeLabel }}
                    </span>
                </div>

                <p class="mt-3 font-extrabold text-gray-900">
                    {{ number_format($transaction->amount, 2, ',', ' ') }} €
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] font-bold">
            <span class="px-2 py-1 rounded-full {{ $stepPayment ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                {{ $stepPayment ? '✓' : '○' }} Paiement
            </span>
            <span class="text-gray-300">→</span>
            <span class="px-2 py-1 rounded-full {{ $stepShipped ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                {{ $stepShipped ? '✓' : '○' }} Expédié
            </span>
            <span class="text-gray-300">→</span>
            <span class="px-2 py-1 rounded-full {{ $stepReceived ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                {{ $stepReceived ? '✓' : '○' }} Reçu
            </span>
            <span class="text-gray-300">→</span>
            <span class="px-2 py-1 rounded-full {{ $stepPaidOut ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                {{ $stepPaidOut ? '✓' : '○' }} Virement
            </span>
        </div>
    </a>

    <div class="mt-4 flex flex-wrap gap-2">
        @if($isSale && $transaction->status === 'paid' && $transaction->shipping_status === 'pending')
            <form method="POST" action="{{ route('transactions.shipped', $transaction) }}">
                @csrf
                @method('PATCH')
                <button class="bg-teal-700 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                    📦 J’ai déposé le colis
                </button>
            </form>
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
