@extends('layouts.app')

@section('title', 'Finaliser ma commande — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8 sm:py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">

        <div class="mb-6 flex items-center justify-between gap-3">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Finaliser ma commande</h1>
            <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1.5 text-sm font-semibold text-teal-700">🔒 Paiement sécurisé</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Récapitulatif (1er sur mobile, à droite sur desktop) --}}
            <aside class="order-1 lg:order-2">
                <div class="lg:sticky lg:top-24 space-y-4">
                    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="flex gap-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                                @if($listing->images->first())
                                    <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="h-full w-full object-cover">
                                @else
                                    <div class="grid h-full w-full place-items-center text-2xl text-gray-300" aria-hidden="true">📦</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $listing->title }}</p>
                                <p class="text-sm text-gray-500">Vendu par {{ $listing->user->name ?? 'Utilisateur' }}</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3 border-t border-gray-100 pt-5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Prix article</span>
                                <span class="font-semibold text-gray-900">{{ number_format($itemAmount ?? $listing->price, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Protection acheteur 🛡️</span>
                                <span class="font-semibold text-teal-700">{{ number_format($buyerProtectionFee ?? 0, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ ($deliveryMethod ?? null) === 'hand_delivery' ? 'Remise en main propre' : 'Livraison' }}</span>
                                <span class="font-semibold text-gray-900">{{ number_format($shippingFee ?? 0, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                                <span class="text-base font-bold text-gray-900">Total à payer</span>
                                <span class="text-2xl font-bold text-teal-700">{{ number_format($totalAmount ?? $listing->price, 2, ',', ' ') }} €</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-4 text-sm text-teal-800">
                        <span class="text-lg" aria-hidden="true">🛡️</span>
                        <p>Ton paiement est <strong>protégé</strong>. Le vendeur n'est payé qu'<strong>après confirmation</strong> de la remise ou de la réception.</p>
                    </div>
                </div>
            </aside>

            {{-- Paiement (2e sur mobile, à gauche sur desktop) --}}
            <div class="order-2 lg:order-1">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="font-semibold text-gray-900">Paiement par carte</h2>

                    <form id="payment-form" class="mt-5">
                        <div id="payment-element"></div>

                        @if(($deliveryMethod ?? null) === 'hand_delivery')
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <div class="font-semibold">📍 Lieu de remise en main propre</div>
                                <div class="mt-1">{{ $listing->hand_delivery_location ?: ($listing->location_address ?: 'Lieu à confirmer avec le vendeur.') }}</div>
                            </div>
                        @endif

                        <button id="submit"
                                class="mt-6 w-full rounded-xl bg-teal-600 px-5 py-4 font-semibold text-white shadow-sm transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                            Payer {{ number_format($totalAmount ?? $listing->price, 2, ',', ' ') }} €
                        </button>

                        <div id="payment-message" class="mt-4 hidden text-sm font-medium text-red-600"></div>
                    </form>

                    <div class="mt-5 flex items-center justify-center gap-2 text-xs text-gray-400">
                        <span aria-hidden="true">🔒</span>
                        <span>Paiement chiffré et sécurisé via Stripe</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script src="https://js.stripe.com/v3/"></script>

<script>
const stripeKey = "{{ env('STRIPE_KEY') }}";
const clientSecret = "{{ $clientSecret ?? '' }}";

const messageEl = document.getElementById('payment-message');
const submitBtn = document.getElementById('submit');

if (!stripeKey || !clientSecret) {
    messageEl.textContent = "Stripe n’est pas correctement configuré. Vérifie STRIPE_KEY et STRIPE_SECRET dans le fichier .env.";
    messageEl.classList.remove('hidden');
    if (submitBtn) submitBtn.disabled = true;
} else {
    const stripe = Stripe(stripeKey);
    const elements = stripe.elements({ clientSecret });
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    const form = document.getElementById('payment-form');
    const submitLabel = submitBtn ? submitBtn.textContent : '';

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Feedback + protection contre le double-clic
        messageEl.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Paiement en cours…';
        }

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: "{{ route('checkout.success', $transaction) }}"
            }
        });

        // En cas de succès, Stripe redirige vers return_url ; on ne réactive
        // le bouton qu'en cas d'erreur (l'acheteur reste sur la page).
        if (error) {
            messageEl.textContent = error.message;
            messageEl.classList.remove('hidden');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitLabel;
            }
        }
    });
}
</script>
@endsection
