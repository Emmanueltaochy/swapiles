<?php

namespace App\Support;

use App\Models\Parrainage;
use App\Models\PortefeuilleMouvement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Portefeuille de crédits (en centimes).
 *
 * Chaque crédit est un « lot » avec un reste et une date d'expiration. On
 * dépense toujours d'abord le lot qui expire le plus tôt. Un crédit n'est
 * jamais converti en argent : il ne sert qu'à payer un achat sur Swap'Îles.
 */
class Portefeuille
{
    /** Lots encore utilisables. */
    private static function lotsActifs(int $userId)
    {
        return PortefeuilleMouvement::query()
            ->where('user_id', $userId)
            ->whereIn('type', PortefeuilleMouvement::LOTS)
            ->where('restant_centimes', '>', 0)
            ->where(fn ($q) => $q->whereNull('expire_at')->orWhere('expire_at', '>', now()));
    }

    public static function solde(User|int $user): int
    {
        return (int) self::lotsActifs($user instanceof User ? $user->id : $user)->sum('restant_centimes');
    }

    /** Ajoute un lot de crédit. */
    public static function crediter(User $user, int $centimes, string $type, ?Carbon $expire = null, ?Parrainage $parrainage = null, ?string $note = null): ?PortefeuilleMouvement
    {
        if ($centimes <= 0) {
            return null;
        }

        return PortefeuilleMouvement::create([
            'user_id' => $user->id,
            'type' => $type,
            'montant_centimes' => $centimes,
            'restant_centimes' => $centimes,
            'expire_at' => $expire,
            'parrainage_id' => $parrainage?->id,
            'note' => $note,
        ]);
    }

    /**
     * Dépense jusqu'à $centimes pour une commande (lots qui expirent le plus
     * tôt d'abord). Renvoie ce qui a réellement été dépensé.
     */
    public static function utiliser(User $user, int $centimes, Transaction $transaction): int
    {
        if ($centimes <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($user, $centimes, $transaction) {
            $reste = $centimes;
            $lots = self::lotsActifs($user->id)->orderByRaw('expire_at IS NULL')->orderBy('expire_at')->orderBy('id')
                ->lockForUpdate()->get();

            foreach ($lots as $lot) {
                if ($reste <= 0) {
                    break;
                }
                $pris = min($reste, (int) $lot->restant_centimes);
                $lot->decrement('restant_centimes', $pris);
                PortefeuilleMouvement::create([
                    'user_id' => $user->id,
                    'type' => 'utilisation',
                    'montant_centimes' => -$pris,
                    'lot_id' => $lot->id,
                    'transaction_id' => $transaction->id,
                    'expire_at' => $lot->expire_at,
                ]);
                $reste -= $pris;
            }

            return $centimes - $reste;
        });
    }

    /**
     * Commande annulée ou remboursée : le crédit utilisé revient dans le
     * portefeuille (une seule fois), avec au moins 7 jours pour s'en servir.
     */
    public static function restituer(Transaction $transaction): int
    {
        return DB::transaction(function () use ($transaction) {
            $total = 0;
            $lignes = PortefeuilleMouvement::where('transaction_id', $transaction->id)
                ->where('type', 'utilisation')->whereNull('restitue_at')->lockForUpdate()->get();

            foreach ($lignes as $ligne) {
                $montant = -(int) $ligne->montant_centimes;
                $expire = $ligne->expire_at && $ligne->expire_at->gt(now()->addDays(7)) ? $ligne->expire_at : now()->addDays(7);
                PortefeuilleMouvement::create([
                    'user_id' => $ligne->user_id,
                    'type' => 'restitution',
                    'montant_centimes' => $montant,
                    'restant_centimes' => $montant,
                    'expire_at' => $expire,
                    'transaction_id' => $transaction->id,
                    'note' => 'Commande #' . $transaction->id . ' annulée ou remboursée',
                ]);
                $ligne->forceFill(['restitue_at' => now()])->save();
                $total += $montant;
            }

            return $total;
        });
    }

    /** Fait expirer les lots arrivés à échéance. Renvoie le nombre de lots expirés. */
    public static function expirer(): int
    {
        $n = 0;
        PortefeuilleMouvement::query()
            ->whereIn('type', PortefeuilleMouvement::LOTS)
            ->where('restant_centimes', '>', 0)
            ->whereNotNull('expire_at')->where('expire_at', '<=', now())
            ->get()
            ->each(function (PortefeuilleMouvement $lot) use (&$n) {
                DB::transaction(function () use ($lot) {
                    $restant = (int) $lot->restant_centimes;
                    $lot->forceFill(['restant_centimes' => 0])->save();
                    PortefeuilleMouvement::create([
                        'user_id' => $lot->user_id, 'type' => 'expiration', 'montant_centimes' => -$restant, 'lot_id' => $lot->id,
                    ]);
                });
                $n++;
            });

        return $n;
    }

    /**
     * Annule le crédit d'un parrainage (fraude) : retire ce qui reste.
     *
     * @return array{retire:int,deja_depense:int}
     */
    public static function annulerParrainage(Parrainage $parrainage): array
    {
        $lot = PortefeuilleMouvement::where('parrainage_id', $parrainage->id)->where('type', 'credit_parrainage')->first();
        if (! $lot) {
            return ['retire' => 0, 'deja_depense' => 0];
        }

        return DB::transaction(function () use ($lot) {
            $lot = PortefeuilleMouvement::whereKey($lot->id)->lockForUpdate()->first();
            $restant = (int) $lot->restant_centimes;
            $expire = (int) PortefeuilleMouvement::where('lot_id', $lot->id)->where('type', 'expiration')->sum('montant_centimes');

            if ($restant > 0) {
                $lot->forceFill(['restant_centimes' => 0])->save();
                PortefeuilleMouvement::create([
                    'user_id' => $lot->user_id, 'type' => 'annulation', 'montant_centimes' => -$restant, 'lot_id' => $lot->id,
                    'note' => 'Récompense de parrainage annulée',
                ]);
            }

            return ['retire' => $restant, 'deja_depense' => max(0, (int) $lot->montant_centimes - $restant + $expire)];
        });
    }

    /** Lots qui expirent dans N jours et dont le rappel n'est pas parti. */
    public static function envoyerRappelsExpiration(): int
    {
        $jours = (int) config('referral.rappel_expiration_jours', 7);
        $n = 0;

        PortefeuilleMouvement::query()
            ->whereIn('type', PortefeuilleMouvement::LOTS)
            ->where('restant_centimes', '>', 0)
            ->whereNull('rappel_envoye_at')
            ->whereBetween('expire_at', [now(), now()->addDays($jours)])
            ->get()
            ->groupBy('user_id')
            ->each(function ($lots, $userId) use (&$n) {
                $montant = (int) $lots->sum('restant_centimes');
                $date = $lots->min('expire_at');
                \App\Models\Notification::create([
                    'user_id' => $userId,
                    'type' => 'wallet_expiring',
                    'title' => '⏳ Ton crédit expire bientôt',
                    'message' => TableauDeBord::euros($montant / 100) . ' de crédit expirent le ' . Carbon::parse($date)->format('d/m') . '. Profite-en pour te faire plaisir 🌴',
                    'url' => route('parrainage.portefeuille', absolute: false),
                ]);
                PortefeuilleMouvement::whereIn('id', $lots->pluck('id'))->update(['rappel_envoye_at' => now()]);
                $n++;
            });

        return $n;
    }
}
