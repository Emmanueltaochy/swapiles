<?php

namespace App\Support;

use App\Jobs\SendTransactionStatusEmails;
use App\Models\Transaction;
use Stripe\StripeClient;

/**
 * Versement du montant vendeur après finalisation d'une transaction.
 *
 * Extrait pour être appelé depuis plusieurs points d'entrée à l'identique :
 *  - l'acheteur confirme la réception (livraison / main propre) ;
 *  - le commerçant confirme le retrait au point relais.
 *
 * Le vendeur touche EXACTEMENT le prix affiché (commission 0 %). Idempotent :
 * si un transfert existe déjà (stripe_transfer_id ou released_at), on ne fait
 * rien — jamais de double versement.
 */
class SellerPayout
{
    public static function release(Transaction $transaction): void
    {
        $transaction->refresh();

        if ($transaction->stripe_transfer_id || $transaction->released_at) {
            return;
        }

        $seller = $transaction->seller;

        if (! $seller || ! $seller->stripe_account_id || ! $seller->stripe_payouts_enabled) {
            return;
        }

        // Vendeur = prix affiché (commission 0 %). On lit seller_amount ; le
        // fallback retire protection + livraison, jamais seulement la commission.
        $sellerAmount = $transaction->seller_amount > 0
            ? (float) $transaction->seller_amount
            : max(0, (float) $transaction->amount - (float) $transaction->buyer_protection_fee - (float) $transaction->shipping_fee);

        if ($sellerAmount <= 0) {
            return;
        }

        try {
            $stripe = new StripeClient(env('STRIPE_SECRET'));

            $transfer = $stripe->transfers->create([
                'amount' => (int) round($sellerAmount * 100),
                'currency' => 'eur',
                'destination' => $seller->stripe_account_id,
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'listing_id' => $transaction->listing_id,
                    'seller_id' => $transaction->seller_id,
                    'buyer_id' => $transaction->buyer_id,
                    'platform_commission_eur' => $transaction->commission,
                ],
            ]);

            $transaction->update([
                'stripe_transfer_id' => $transfer->id,
                'released_at' => now(),
                'wallet_status' => 'paid',
                'transfer_started_at' => now(),
                'transferred_at' => now(),
                'estimated_payout_date' => now()->addDays(2),
            ]);

            SendTransactionStatusEmails::dispatch($transaction->id, 'released');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
