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

        $transactions = Transaction::whereNull('released_at')
            ->where('shipping_status', 'received')
            ->whereNotNull('seller_amount')
            ->get();

        foreach ($transactions as $transaction) {

            $seller = $transaction->seller;

            if (!$seller?->stripe_account_id) {
                $this->error("No Stripe account for seller #{$seller?->id}");
                continue;
            }

            try {

                $transfer = $stripe->transfers->create([
                    'amount' => $transaction->seller_amount * 100,
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
