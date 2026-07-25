<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;
use App\Models\Transaction;

class ReleaseMarketplaceFunds extends Command
{
    protected $signature = 'swapiles:release-funds';

    protected $description = 'Release seller funds after buyer confirmation';

    public function handle()
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));

        // NB : commande NON planifiée (le cron actif est payouts:release-pending).
        // Conservée pour usage manuel — mais durcie pour ne JAMAIS virer un
        // montant vendeur invalide (cf. point de contrôle #2 : les lignes
        // héritées ont seller_amount = 0, pas NULL, donc whereNotNull ne suffit
        // pas). On lit seller_amount ; à défaut, fallback = prix affiché =
        // amount - protection - livraison (commission 0 %).
        $transactions = Transaction::whereNull('released_at')
            ->where('shipping_status', 'received')
            ->get();

        foreach ($transactions as $transaction) {

            $seller = $transaction->seller;

            if (!$seller?->stripe_account_id || !$seller->stripe_payouts_enabled) {
                $this->error("Seller #{$seller?->id} : compte Stripe non opérationnel — ignoré.");
                continue;
            }

            $sellerAmount = $transaction->seller_amount > 0
                ? (float) $transaction->seller_amount
                : max(0, (float) $transaction->amount
                    - (float) $transaction->buyer_protection_fee
                    - (float) $transaction->shipping_fee);

            if ($sellerAmount <= 0) {
                $this->warn("Transaction #{$transaction->id} ignorée : part vendeur invalide (0 €).");
                continue;
            }

            try {

                $transfer = $stripe->transfers->create([
                    'amount' => (int) round($sellerAmount * 100),
                    'currency' => 'eur',
                    'destination' => $seller->stripe_account_id,
                    'description' => 'Paiement vendeur Swap’Îles',
                ]);

                $transaction->update([
                    'released_at' => now(),
                    'stripe_transfer_id' => $transfer->id,
                    'status' => 'completed',
                ]);

                $this->info("Transfer OK transaction #{$transaction->id}");

            } catch (\Exception $e) {

                $this->error($e->getMessage());

            }
        }

        return self::SUCCESS;
    }
}
