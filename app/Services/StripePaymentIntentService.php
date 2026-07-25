<?php

namespace App\Services;

use Stripe\StripeClient;

/**
 * Création du PaymentIntent, isolée pour être mockable en test (on capture le
 * montant exact envoyé à Stripe) et pour centraliser la configuration Stripe.
 */
class StripePaymentIntentService
{
    /**
     * @param  int  $amountCents  Montant EXACT à débiter (centimes entiers) — doit
     *                            toujours être égal au « Total à payer » affiché.
     * @param  array<string,mixed>  $metadata
     * @return object  PaymentIntent Stripe (->id, ->client_secret)
     */
    public function create(int $amountCents, array $metadata, string $currency = 'eur'): object
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));

        return $stripe->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $metadata,
        ]);
    }
}
