<?php

namespace App\Support;

use App\Jobs\SendTransactionStatusEmails;
use App\Models\Notification;
use App\Models\Transaction;

/**
 * Transition « payé » d'une transaction, UNIQUE et idempotente.
 *
 * Deux chemins peuvent constater le paiement quasi simultanément :
 *   - la page de succès (retour navigateur après confirmPayment) ;
 *   - le webhook Stripe (payment_intent.succeeded / checkout.session.completed).
 *
 * Sans garde, les deux créaient chacun la notification de vente (doublon) et
 * seul le chemin « page de succès » envoyait l'e-mail vendeur (donc pas d'e-mail
 * quand le webhook gagnait la course). On centralise : une transition ATOMIQUE
 * pending -> paid, et seul l'appelant qui l'a réellement effectuée notifie et
 * déclenche les e-mails. L'autre appel est un no-op.
 */
class TransactionPayment
{
    /**
     * @return bool  true seulement si CET appel a fait passer la transaction à
     *               « paid » (et a donc créé les notifications + e-mails).
     */
    public static function markPaidOnce(Transaction $transaction): bool
    {
        // Verrou par mise à jour conditionnelle : une seule requête peut faire
        // passer la ligne de pending à paid ; les suivantes affectent 0 ligne.
        $claimed = Transaction::whereKey($transaction->getKey())
            ->where('status', 'pending')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        if ($claimed === 0) {
            return false; // déjà traité par l'autre chemin.
        }

        $fresh = $transaction->fresh(['listing', 'seller', 'buyer']);

        if ($fresh->listing && $fresh->listing->status !== 'sold') {
            $fresh->listing->update(['status' => 'sold']);
        }

        self::createOnSiteNotifications($fresh);

        // E-mails fiables (Mail::raw) à l'acheteur ET au vendeur — même
        // mécanisme que les autres étapes (« expédié », « reçu »…), qui, lui,
        // fonctionne bien en prod.
        try {
            SendTransactionStatusEmails::dispatch($fresh->id, 'paid');
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
    }

    private static function createOnSiteNotifications(Transaction $t): void
    {
        if ($t->seller) {
            Notification::create([
                'user_id' => $t->seller_id,
                'type' => 'transaction_paid_seller',
                'title' => 'Nouvelle vente 🎉',
                'message' => 'Votre article "' . ($t->listing->title ?? 'Annonce') . '" a été acheté.',
                'url' => route('account.transactions.show', $t, absolute: false),
            ]);
        }

        if ($t->buyer) {
            Notification::create([
                'user_id' => $t->buyer_id,
                'type' => 'transaction_paid_buyer',
                'title' => 'Achat confirmé ✅',
                'message' => 'Votre paiement a été validé pour "' . ($t->listing->title ?? 'Annonce') . '".',
                'url' => route('account.transactions.show', $t, absolute: false),
            ]);
        }
    }
}
