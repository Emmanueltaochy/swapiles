<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;

/**
 * Point #1 : les PaymentIntents non finalisés créés AVANT le déploiement ont été
 * calculés sur l'ANCIENNE grille (ex. pi_3Tx6P1... transaction #132 : 700 c en
 * requires_payment_method). Tant qu'ils restent ouverts, un acheteur peut y
 * revenir et payer un montant faux. On les annule.
 *
 * « Non finalisé » = statut annulable : requires_payment_method,
 * requires_confirmation, requires_action. On NE touche jamais un PI
 * succeeded / processing / canceled.
 *
 * Usage :
 *   php artisan stripe:cancel-stale-intents --before="2026-07-25 19:00:00"   # dry-run
 *   php artisan stripe:cancel-stale-intents --before=1753466400 --apply       # annule
 *
 * --before accepte un timestamp Unix ou toute date parsable (strtotime).
 * Par défaut : maintenant (annule tout PI ouvert antérieur à l'instant présent).
 */
class CancelStalePaymentIntents extends Command
{
    protected $signature = 'stripe:cancel-stale-intents {--before= : Date/timestamp limite (défaut: maintenant)} {--apply : Annule réellement (sinon dry-run)}';

    protected $description = 'Annule les PaymentIntents non finalisés créés avant le déploiement (montants ancienne grille)';

    private const CANCELABLE = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    public function handle(): int
    {
        $secret = env('STRIPE_SECRET');
        if (!$secret) {
            $this->error('STRIPE_SECRET absent.');
            return self::FAILURE;
        }

        $beforeOpt = $this->option('before');
        if ($beforeOpt === null || $beforeOpt === '') {
            $cutoff = time();
        } elseif (ctype_digit((string) $beforeOpt)) {
            $cutoff = (int) $beforeOpt;
        } else {
            $cutoff = strtotime($beforeOpt);
            if ($cutoff === false) {
                $this->error("--before illisible : {$beforeOpt}");
                return self::FAILURE;
            }
        }

        $apply = (bool) $this->option('apply');
        $stripe = new StripeClient($secret);

        $this->info(($apply ? 'ANNULATION' : 'DRY-RUN') . ' des PaymentIntents ouverts créés avant ' . date('Y-m-d H:i:s', $cutoff));

        $rows = [];
        $canceled = 0;
        $errors = 0;

        // Pagination auto : tous les PI créés strictement avant le cutoff.
        $params = ['created' => ['lt' => $cutoff], 'limit' => 100];
        foreach ($stripe->paymentIntents->all($params)->autoPagingIterator() as $pi) {
            if (!in_array($pi->status, self::CANCELABLE, true)) {
                continue;
            }

            $line = [$pi->id, $pi->amount, $pi->currency, $pi->status, date('Y-m-d H:i', $pi->created)];

            if ($apply) {
                try {
                    $stripe->paymentIntents->cancel($pi->id, [
                        'cancellation_reason' => 'abandoned',
                    ]);
                    $line[] = 'ANNULÉ';
                    $canceled++;
                } catch (\Throwable $e) {
                    $line[] = 'ERREUR: ' . $e->getMessage();
                    $errors++;
                }
            } else {
                $line[] = 'à annuler';
            }

            $rows[] = $line;
        }

        $this->table(['PaymentIntent', 'amount(c)', 'cur', 'status', 'créé', 'action'], $rows);

        if (!$apply) {
            $this->warn(count($rows) . ' PaymentIntent(s) seraient annulés. Relance avec --apply pour exécuter.');
            return self::SUCCESS;
        }

        $this->info("Annulés : {$canceled}. Erreurs : {$errors}.");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
