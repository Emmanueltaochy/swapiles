<?php

namespace App\Services;

use App\Models\User;
use Stripe\StripeClient;

/**
 * Création du PaymentIntent, isolée pour être mockable en test (on capture le
 * montant exact envoyé à Stripe) et pour centraliser la configuration Stripe.
 *
 * Item 9 :
 *   - moyens de paiement restreints à carte + Link (pas de virement / redirects) ;
 *   - receipt_email renseigné (reçu Stripe automatique à l'acheteur) ;
 *   - Customer Stripe créé une fois puis réutilisé (rattachement des paiements).
 */
class StripePaymentIntentService
{
    private function client(): StripeClient
    {
        return new StripeClient(env('STRIPE_SECRET'));
    }

    /**
     * @param  int  $amountCents  Montant EXACT à débiter (centimes entiers) — doit
     *                            toujours être égal au « Total à payer » affiché.
     * @param  array<string,mixed>  $metadata
     * @param  array{receipt_email?:string|null, customer?:string|null}  $options
     * @return object  PaymentIntent Stripe (->id, ->client_secret)
     */
    public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
    {
        $params = [
            'amount' => $amountCents,
            'currency' => $currency,
            // Restreint explicitement les moyens de paiement à carte + Link.
            'payment_method_types' => ['card', 'link'],
            'metadata' => $metadata,
        ];

        if (!empty($options['customer'])) {
            $params['customer'] = $options['customer'];
        }

        if (!empty($options['receipt_email'])) {
            $params['receipt_email'] = $options['receipt_email'];
        }

        return $this->client()->paymentIntents->create($params);
    }

    /**
     * Le paiement a-t-il VRAIMENT réussi chez Stripe, pour au moins ce montant ?
     * On ne croit jamais la page de retour ni un message non signé sur parole.
     */
    public function paiementReussi(string $paymentIntentId, int $montantCents): bool
    {
        if (blank(env('STRIPE_SECRET'))) {
            return false;
        }

        try {
            $pi = $this->client()->paymentIntents->retrieve($paymentIntentId);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return ($pi->status ?? null) === 'succeeded'
            && (int) ($pi->amount_received ?? 0) >= $montantCents;
    }

    /** Empreinte de la carte utilisée (même carte = même personne), ou null. */
    public function carteEmpreinte(string $paymentIntentId): ?string
    {
        if (blank(env('STRIPE_SECRET'))) {
            return null;
        }

        $pi = $this->client()->paymentIntents->retrieve($paymentIntentId, ['expand' => ['payment_method']]);

        return $pi->payment_method->card->fingerprint ?? null;
    }

    /** Annule un paiement en attente (ignoré si Stripe n'est pas configuré). */
    public function annuler(string $paymentIntentId): void
    {
        if (blank(env('STRIPE_SECRET'))) {
            return;
        }

        try {
            $this->client()->paymentIntents->cancel($paymentIntentId);
        } catch (\Throwable $e) {
            // Déjà annulé, ou payé entre-temps : le webhook « payé » ne
            // trouvera plus la commande en attente ; on le signale à l'admin.
            report($e);
        }
    }

    /**
     * Renvoie l'id du Customer Stripe de l'acheteur, en le créant au besoin puis
     * en le mémorisant sur le user pour réutilisation. Renvoie null si Stripe
     * échoue (le paiement doit rester possible sans Customer).
     */
    public function resolveCustomerId(User $buyer): ?string
    {
        if (!empty($buyer->stripe_customer_id)) {
            return $buyer->stripe_customer_id;
        }

        try {
            $customer = $this->client()->customers->create([
                'email' => $buyer->email,
                'name' => $buyer->name,
                'metadata' => ['user_id' => (string) $buyer->id],
            ]);

            $buyer->forceFill(['stripe_customer_id' => $customer->id])->save();

            return $customer->id;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
