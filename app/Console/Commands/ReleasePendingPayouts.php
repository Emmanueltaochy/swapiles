<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class ReleasePendingPayouts extends Command
{
    protected $signature = 'payouts:release-pending {--dry-run : Affiche sans transférer}';

    protected $description = 'Libère les paiements vendeurs pour les transactions terminées sans transfert Stripe';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $transactions = Transaction::query()
            ->with('seller')
            ->where('status', 'completed')
            ->whereNull('stripe_transfer_id')
            ->whereNull('released_at')
            ->get();

        $this->info("Transactions à vérifier : " . $transactions->count());

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        foreach ($transactions as $transaction) {
            $seller = $transaction->seller;

            if (!$seller || !$seller->stripe_account_id || !$seller->stripe_payouts_enabled) {
                $this->warn("Transaction #{$transaction->id} ignorée : vendeur Stripe non configuré.");
                continue;
            }

            $sellerAmount = max(0, $transaction->amount - $transaction->commission);

            if ($sellerAmount <= 0) {
                $this->warn("Transaction #{$transaction->id} ignorée : montant vendeur invalide.");
                continue;
            }

            $this->line("Transaction #{$transaction->id} : {$sellerAmount} € vers vendeur {$seller->id}");

            if ($dryRun) {
                continue;
            }

            try {
                $transfer = $stripe->transfers->create([
                    'amount' => $sellerAmount * 100,
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
                ]);

                $this->info("✅ Transfert créé : {$transfer->id}");
            } catch (\Throwable $e) {
                $this->error("❌ Transaction #{$transaction->id} non versée : {$e->getMessage()}");
                continue;
            }
        }

        $this->info('Terminé.');

        return self::SUCCESS;
    }
}
