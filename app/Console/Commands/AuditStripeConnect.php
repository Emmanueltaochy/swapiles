<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Stripe\StripeClient;

/**
 * Point de contrôle #3 : audite les vendeurs « Paiements CB actifs » contre
 * l'état RÉEL de leur compte Stripe Connect.
 *
 * Un vendeur est « CB actif » quand isOnlinePayable() est vrai, càd :
 *   stripe_account_id != null && stripe_charges_enabled && stripe_payouts_enabled
 * (colonnes en cache). Cette commande interroge Stripe pour CHAQUE compte et
 * confronte l'état live (charges_enabled / payouts_enabled) au cache local.
 *
 * Usage (à lancer côté prod, clés live) :
 *   php artisan stripe:audit-connect            # rapport
 *   php artisan stripe:audit-connect --sync     # re-synchronise le cache local
 *   php artisan stripe:audit-connect --account=acct_xxx   # un seul vendeur
 *
 * Sortie : tableau + code retour 1 si au moins un compte « CB actif » n'est PAS
 * réellement charges_enabled && payouts_enabled (ta prod n'est alors pas prête
 * pour l'étape 7 du test).
 */
class AuditStripeConnect extends Command
{
    protected $signature = 'stripe:audit-connect {--sync : Met à jour les colonnes stripe_* locales depuis Stripe} {--account= : N\'auditer qu\'un stripe_account_id précis}';

    protected $description = 'Vérifie l\'état réel des comptes Stripe Connect des vendeurs CB actifs';

    public function handle(): int
    {
        $secret = env('STRIPE_SECRET');
        if (!$secret) {
            $this->error('STRIPE_SECRET absent : impossible d\'interroger Stripe.');
            return self::FAILURE;
        }

        $stripe = new StripeClient($secret);

        $query = User::whereNotNull('stripe_account_id');
        if ($account = $this->option('account')) {
            $query->where('stripe_account_id', $account);
        }
        $sellers = $query->orderBy('id')->get();

        $this->info("Comptes Connect à auditer : {$sellers->count()}");

        $rows = [];
        $broken = 0;

        foreach ($sellers as $seller) {
            try {
                $acct = $stripe->accounts->retrieve($seller->stripe_account_id, []);
                $liveCharges = (bool) $acct->charges_enabled;
                $livePayouts = (bool) $acct->payouts_enabled;
                $liveDetails = (bool) ($acct->details_submitted ?? false);
            } catch (\Throwable $e) {
                $broken++;
                $rows[] = [$seller->id, $seller->stripe_account_id, 'ERREUR', $e->getMessage()];
                continue;
            }

            $cachedOk = (bool) $seller->stripe_charges_enabled && (bool) $seller->stripe_payouts_enabled;
            $liveOk = $liveCharges && $livePayouts;
            $drift = $cachedOk !== $liveOk;

            if (!$liveOk) {
                $broken++;
            }

            $flag = $liveOk ? 'OK' : 'PAS PRÊT';
            if ($drift) {
                $flag .= ' / DRIFT cache';
            }

            $rows[] = [
                $seller->id,
                $seller->stripe_account_id,
                ($liveCharges ? 'oui' : 'non') . ' / ' . ($livePayouts ? 'oui' : 'non'),
                $flag,
            ];

            if ($this->option('sync')) {
                $seller->forceFill([
                    'stripe_charges_enabled' => $liveCharges,
                    'stripe_payouts_enabled' => $livePayouts,
                    'stripe_details_submitted' => $liveDetails,
                ])->save();
            }
        }

        $this->table(['User', 'Account', 'charges/payouts (live)', 'Verdict'], $rows);

        if ($broken > 0) {
            $this->error("{$broken} compte(s) NON opérationnel(s) ou en erreur — l'étape 7 (versement vendeur) échouerait pour ceux-là.");
            return self::FAILURE;
        }

        $this->info('Tous les comptes CB actifs sont réellement charges_enabled && payouts_enabled.');
        return self::SUCCESS;
    }
}
