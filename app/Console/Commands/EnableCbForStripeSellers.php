<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

class EnableCbForStripeSellers extends Command
{
    protected $signature = 'listings:enable-cb-for-stripe-sellers
        {--dry-run : Affiche les annonces concernées sans rien modifier}';

    protected $description = 'Active le paiement par carte (requires_online_payment) sur les annonces de vente des vendeurs ayant un compte Stripe opérationnel (IBAN OK) mais dont l\'annonce ne proposait pas encore la CB.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Listing::query()
            ->where('requires_online_payment', false)
            ->whereIn('status', ['published', 'draft'])
            ->whereIn('listing_type', ['achat', 'negoce-prix'])
            ->where('price', '>', 0)
            // Vendeur avec compte Stripe opérationnel (encaissements + versements).
            ->whereHas('user', function ($q) {
                $q->whereNotNull('stripe_account_id')
                    ->where('stripe_charges_enabled', true)
                    ->where('stripe_payouts_enabled', true);
            });

        $total = (clone $query)->count();
        $this->info("Annonces à passer en paiement CB : {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $done = 0;

        $query->with('user:id,name,email')->chunkById(300, function ($listings) use (&$done, $dryRun) {
            foreach ($listings as $listing) {
                if ($dryRun) {
                    $this->line("→ [dry-run] #{$listing->id} · {$listing->title} · " . ($listing->user?->email ?? '—'));
                    $done++;

                    continue;
                }

                // On active la CB. Colissimo N'EST PAS activé (pas d'adresse d'expédition
                // pour la plupart) : reste la remise en main propre + paiement carte
                // protégé pour les acheteurs de la même île.
                $listing->forceFill([
                    'requires_online_payment' => true,
                    'allows_hand_delivery' => true,
                    'pickup_enabled' => true,
                ])->save();

                $done++;
            }
        });

        $this->info($dryRun
            ? "Total qui seraient activées : {$done}"
            : "Annonces passées en paiement CB : {$done}");

        return self::SUCCESS;
    }
}
