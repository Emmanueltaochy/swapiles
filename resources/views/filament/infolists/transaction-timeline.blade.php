@php
    $t = $getRecord();
    $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y à H:i') : null;

    $isHand = $t->delivery_method === 'hand_delivery'
        || in_array($t->shipping_status, ['hand_delivered'], true);

    $paidAt = $t->paid_at ?? (in_array($t->status, ['paid', 'completed'], true) ? $t->created_at : null);

    $steps = [];
    $steps[] = ['label' => 'Commande passée', 'icon' => '🛒', 'at' => $t->created_at, 'note' => "Achat initié par l'acheteur"];
    $steps[] = ['label' => 'Paiement confirmé', 'icon' => '💳', 'at' => $paidAt, 'note' => $t->stripe_payment_intent_id ? 'Carte sécurisée (Stripe)' : ('Paiement : ' . ($t->payment_method ?? '—'))];

    if ($isHand) {
        $steps[] = ['label' => 'Remise en main propre', 'icon' => '🤝', 'at' => $t->received_at ?? $t->completed_at, 'note' => "Article remis à l'acheteur"];
    } else {
        $track = trim(($t->carrier ? $t->carrier . ' ' : '') . ($t->tracking_number ? '· ' . $t->tracking_number : ''));
        $steps[] = ['label' => 'Expédié', 'icon' => '📦', 'at' => $t->shipped_at, 'note' => $track !== '' ? $track : 'Colis expédié'];
        $steps[] = ['label' => 'Livré', 'icon' => '🚚', 'at' => $t->delivered_at, 'note' => 'Colis livré'];
        $steps[] = ['label' => 'Réception confirmée', 'icon' => '✅', 'at' => $t->received_at, 'note' => "L'acheteur a confirmé la réception"];
    }

    $steps[] = ['label' => 'Versement au vendeur', 'icon' => '💶', 'at' => $t->transferred_at ?? $t->released_at, 'note' => 'Argent envoyé sur le compte du vendeur'];

    $cancelled = in_array($t->status, ['cancelled', 'refunded'], true);
@endphp

<div>
    @if($cancelled)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-3 text-sm font-semibold text-red-700 dark:text-red-300">
            {{ $t->status === 'refunded' ? '↩️ Transaction remboursée' : '✖️ Transaction annulée' }}
        </div>
    @endif

    <div>
        @foreach($steps as $s)
            <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-bold
                        {{ $s['at'] ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500' }}">
                        {{ $s['at'] ? '✓' : '' }}
                    </div>
                    @unless($loop->last)
                        <div class="my-1 w-0.5 flex-1 {{ $s['at'] ? 'bg-emerald-300 dark:bg-emerald-700' : 'bg-gray-200 dark:bg-gray-700' }}" style="min-height:1.75rem"></div>
                    @endunless
                </div>
                <div class="pb-5">
                    <p class="font-semibold {{ $s['at'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }}">
                        {{ $s['icon'] }} {{ $s['label'] }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $s['note'] }}</p>
                    <p class="mt-0.5 text-xs {{ $s['at'] ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                        {{ $s['at'] ? $fmt($s['at']) : 'En attente' }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>
