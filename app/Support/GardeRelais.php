<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Délai de garde au point relais (charte) : passé le délai sans retrait, le
 * colis est « non retiré ». Le vendeur vient le récupérer, l'acheteur est
 * prévenu et l'admin décide du remboursement (jamais automatique).
 */
class GardeRelais
{
    public static function delai(): int
    {
        return max(1, (int) config('relais.delai_garde_jours', 10));
    }

    /** Date limite de retrait d'un colis déposé. */
    public static function limite(Transaction $t): ?Carbon
    {
        return $t->relay_deposited_at ? Carbon::parse($t->relay_deposited_at)->addDays(self::delai()) : null;
    }

    /** Passe en « non retiré » les colis dont le délai est dépassé. Renvoie le nombre de colis. */
    public static function expirer(?Carbon $maintenant = null): int
    {
        $maintenant ??= now();

        $ventes = Transaction::query()
            ->where('delivery_method', 'relay')
            ->where('relay_status', 'deposited')
            ->whereNull('relay_collected_at')
            ->where('relay_deposited_at', '<=', $maintenant->copy()->subDays(self::delai()))
            ->whereNotIn('status', ['refunded', 'cancelled'])
            ->with(['listing', 'buyer', 'seller', 'relayPoint'])
            ->get();

        // Un lot = un seul colis : une seule série d'alertes.
        $colis = 0;
        foreach ($ventes->groupBy(fn (Transaction $t) => $t->lot_id ?? 't' . $t->id) as $lot) {
            foreach ($lot as $vente) {
                $vente->update([
                    'relay_status' => 'expired',
                    'relay_expired_at' => $maintenant,
                    'auto_review_flagged_at' => $vente->auto_review_flagged_at ?? $maintenant,
                ]);
            }
            self::prevenir($lot->first());
            $colis++;
        }

        return $colis;
    }

    private static function prevenir(Transaction $t): void
    {
        $titre = $t->listing?->title ?? 'ton article';
        $vente = route('account.transactions.show', $t, absolute: false);
        $relais = $t->relayPoint;
        $ou = $relais ? $relais->name . ($relais->city ? ', ' . $relais->city : '') : 'le point relais';
        $jours = self::delai();

        $notifs = [
            [$t->seller_id, 'relay_expired_seller', '📦 Colis non retiré : récupère-le',
                "« {$titre} » n'a pas été retiré chez {$ou} en {$jours} jours. Passe le récupérer, nous revenons vers toi pour la suite de la vente.", $vente],
            [$t->buyer_id, 'relay_expired_buyer', '⌛ Délai de retrait dépassé',
                "Tu n'as pas retiré « {$titre} » chez {$ou} dans les {$jours} jours. Le colis va être rendu au vendeur : notre équipe revient vers toi.", $vente],
            [$relais?->manager_user_id, 'relay_expired_merchant', '↩️ Colis à rendre au vendeur',
                "« {$titre} » n'a pas été retiré. Rends-le au vendeur quand il passe, puis confirme dans ton espace relais.", route('account.relay.dashboard', absolute: false)],
        ];

        foreach ($notifs as [$userId, $type, $title, $message, $url]) {
            if ($userId && ! Notification::where('user_id', $userId)->where('type', $type)->where('url', $url)->where('message', $message)->exists()) {
                Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url]);
            }
        }

        AdminEvent::notify(
            'Colis non retiré au point relais',
            'La vente #' . $t->id . ' (' . $titre . ') n\'a pas été retirée chez ' . $ou . ' en ' . $jours . ' jours. '
                . 'Le colis est à rendre au vendeur (' . ($t->seller?->name ?? '-') . '). Acheteur : ' . ($t->buyer?->name ?? '-')
                . '. Décider du remboursement depuis l\'admin.',
            route('account.transactions.show', $t),
            'refund_review'
        );
    }
}
