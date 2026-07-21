@extends('layouts.app')

@section('title', 'Commande — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-extrabold text-gray-900 mb-6">
            Finaliser ma commande
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold text-gray-900 mb-4">Votre article</h2>

                <div class="flex gap-4">
                    <div class="w-28 h-36 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                        @if($listing->images->first())
                            <img src="{{ $listing->images->first()->url }}" class="w-full h-full object-cover" alt="{{ $listing->title }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                        @endif
                    </div>

                    <div>
                        <h3 class="font-extrabold text-gray-900">{{ $listing->title }}</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Vendu par {{ $listing->user->name ?? 'un membre Swap’Îles' }}
                        </p>

                        <p class="text-2xl font-extrabold text-teal-700 mt-4">
                            {{ number_format($listing->price, 0, ',', ' ') }} €
                        </p>
                    </div>
                </div>

                <div class="border-t border-gray-100 mt-6 pt-5 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Prix article</span>
                        <span class="font-bold">{{ number_format($listing->price, 0, ',', ' ') }} €</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Protection acheteur</span>
                        <span class="font-bold">Incluse</span>
                    </div>

                    <div class="flex justify-between text-lg border-t border-gray-100 pt-3">
                        <span class="font-extrabold text-gray-900">Total</span>
                        <span class="font-extrabold text-gray-900">{{ number_format($listing->price, 0, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold text-gray-900 mb-4">Paiement sécurisé</h2>

                <form id="payment-form">
                    <div id="payment-element" class="mb-5"></div>

                    <button id="submit" class="w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                        Payer {{ number_format($listing->price, 0, ',', ' ') }} €
                    </button>

                    <p id="payment-message" class="text-sm text-red-600 mt-4 hidden"></p>
                </form>

                <p class="text-xs text-gray-400 mt-4 text-center">
                    Paiement traité par Stripe. Le vendeur sera payé après validation de la transaction.
                </p>
            </div>

        </div>

    </div>
</section>

<script src="https://js.stripe.com/v3/"></script>

<script>
const stripe = Stripe(@json($stripeKey));

const elements = stripe.elements({
    clientSecret: @json($clientSecret),
});

const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

const form = document.getElementById('payment-form');
const submitButton = document.getElementById('submit');
const message = document.getElementById('payment-message');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    submitButton.disabled = true;
    submitButton.innerText = 'Paiement en cours...';

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: @json(route('checkout.success', $transaction)),
        },
    });

    if (error) {
        message.classList.remove('hidden');
        message.textContent = error.message;
        submitButton.disabled = false;
        submitButton.innerText = 'Réessayer le paiement';
    }
});
</script>
@endsection
