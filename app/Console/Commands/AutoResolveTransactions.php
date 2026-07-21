<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Notifications\TransactionCompletedNotification;
use App\Support\AdminEvent;
use Illuminate\Console\Command;

class AutoResolveTransactions extends Command
{
    protected $signature = 'transactions:auto-resolve {--dry-run : Simulation, ne modifie rien}';

    protected $description = 'Filets de sécurité paiement : versement auto si colis expédié depuis longtemps sans confirmation, et signalement des ventes non expédiées à rembourser.';

    /** Jours après expédition avant versement automatique au vendeur (acheteur passif). */
    private const AUTO_RELEASE_DAYS_AFTER_SHIP = 14;

    /** Jours après paiement sans expédition avant signalement pour remboursement. */
    private const REFUND_REVIEW_DAYS_AFTER_PAYMENT = 10;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('MODE SIMULATION (--dry-run) : aucune modification ne sera enregistrée.');
        }

        $this->autoCompleteShipped($dryRun);
        $this->flagUnshippedForRefund($dryRun);

        $this->info('Terminé.');

        return self::SUCCESS;
    }

    /**
     * FILET n°1 — Colis expédié mais acheteur n'a jamais confirmé « reçu ».
     * Après N jours depuis l'expédition, on finalise la transaction : elle passe
     * en "completed", ce qui déclenche le versement au vendeur (via
     * payouts:release-pending). Protège le vendeur qui a bien envoyé.
     */
    private function autoCompleteShipped(bool $dryRun): void
    {
        $limit = now()->subDays(self::AUTO_RELEASE_DAYS_AFTER_SHIP);

        $transactions = Transaction::query()
            ->where('status', 'paid')
            ->whereNotNull('shipped_at')
            ->where('shipped_at', '<=', $limit)
            ->get();

        $this->info("FILET 1 (versement auto, expédié depuis ≥ "
            . self::AUTO_RELEASE_DAYS_AFTER_SHIP . "j) : {$transactions->count()} transaction(s).");

        foreach ($transactions as $transaction) {
            $this->line("  #{$transaction->id} expédiée le {$transaction->shipped_at} -> finalisation");

            if ($dryRun) {
                continue;
            }

            $transaction->update([
                'status' => 'completed',
                'received_at' => $transaction->received_at ?? now(),
                'completed_at' => $transaction->completed_at ?? now(),
            ]);

            // Le versement Stripe est pris en charge par payouts:release-pending.
            if ($transaction->buyer) {
                try {
                    $transaction->buyer->notify(new TransactionCompletedNotification($transaction));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * FILET n°2 — Vendeur n'a pas expédié après N jours.
     * On signale l'acheteur à rembourser (validation manuelle depuis l'admin).
     * On exclut la remise en main propre (pas d'expédition attendue) et on ne
     * signale chaque transaction qu'une seule fois (auto_review_flagged_at).
     */
    private function flagUnshippedForRefund(bool $dryRun): void
    {
        $limit = now()->subDays(self::REFUND_REVIEW_DAYS_AFTER_PAYMENT);

        $transactions = Transaction::query()
            ->where('status', 'paid')
            ->whereNull('shipped_at')
            ->whereNull('auto_review_flagged_at')
            ->where('delivery_method', '!=', 'hand_delivery')
            ->where('created_at', '<=', $limit)
            ->with(['listing', 'seller', 'buyer'])
            ->get();

        $this->info("FILET 2 (à rembourser, non expédié depuis ≥ "
            . self::REFUND_REVIEW_DAYS_AFTER_PAYMENT . "j) : {$transactions->count()} transaction(s).");

        foreach ($transactions as $transaction) {
            $this->line("  #{$transaction->id} payée le {$transaction->created_at}, non expédiée -> signalement remboursement");

            if ($dryRun) {
                continue;
            }

            $transaction->update([
                'auto_review_flagged_at' => now(),
            ]);

            AdminEvent::notify(
                'Remboursement à valider (vendeur non expédié)',
                'La vente #' . $transaction->id . ' (' . ($transaction->listing->title ?? 'Annonce') . ', '
                    . number_format((float) $transaction->amount, 2, ',', ' ') . ' €) n\'a pas été expédiée '
                    . self::REFUND_REVIEW_DAYS_AFTER_PAYMENT . ' jours après le paiement. '
                    . 'Vendeur : ' . ($transaction->seller->name ?? '-') . ' | Acheteur : ' . ($transaction->buyer->name ?? '-')
                    . '. À rembourser si confirmé.',
                route('account.transactions.show', $transaction)
            );
        }
    }
}
