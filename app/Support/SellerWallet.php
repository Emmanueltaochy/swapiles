<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Règles d'argent du wallet vendeur — centralisées et testées.
 *
 * INVARIANT CLÉ (bug du solde fictif) : le solde ne contient QUE les ventes
 * SÉCURISÉES, c'est-à-dire celles dont l'argent a réellement transité par
 * Stripe (PaymentIntent présent). Les ventes en espèces, dons et échanges ne
 * créditent JAMAIS le wallet : le vendeur n'a rien à retirer côté Stripe pour
 * elles. Elles restent visibles dans le journal « Toutes mes ventes », jamais
 * dans le solde.
 */
class SellerWallet
{
    /** L'argent a-t-il transité par Stripe (donc retirable) ? */
    public static function isSecured(Transaction $t): bool
    {
        return filled($t->stripe_payment_intent_id);
    }

    /** Part nette vendeur (commission 0 % en Block 1 ; fallback défensif). */
    public static function net(Transaction $t): float
    {
        if ((float) $t->seller_amount > 0) {
            return (float) $t->seller_amount;
        }

        return max(0.0, (float) $t->amount
            - (float) $t->commission
            - (float) $t->buyer_protection_fee
            - (float) $t->shipping_fee);
    }

    /** Mode de paiement normalisé pour l'affichage : online|cash|gift|exchange|other. */
    public static function mode(Transaction $t): string
    {
        return match ($t->payment_method) {
            'cb', 'card', 'online', 'en_ligne' => 'online',
            'especes' => 'cash',
            'don' => 'gift',
            'echange' => 'exchange',
            default => self::isSecured($t) ? 'online' : 'other',
        };
    }

    public static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'online' => 'CB',
            'cash' => 'Espèces',
            'gift' => 'Don',
            'exchange' => 'Échange',
            default => 'Autre',
        };
    }

    /** Ne conserve que les ventes sécurisées — la SEULE source du solde. */
    public static function securedOnly(iterable $sales): Collection
    {
        return collect($sales)
            ->filter(fn (Transaction $t) => self::isSecured($t))
            ->values();
    }

    /**
     * Trois compartiments du solde, calculés UNIQUEMENT sur les ventes
     * sécurisées :
     *   pending    = payé, réception non confirmée
     *   processing = réception confirmée, versement pas encore émis
     *   paid       = déjà versé au vendeur
     *
     * @return array{pending: float, processing: float, paid: float}
     */
    public static function balances(iterable $sales): array
    {
        $secured = self::securedOnly($sales);

        $pending = $secured
            ->where('status', 'paid')
            ->sum(fn (Transaction $t) => self::net($t));

        $processing = $secured
            ->where('status', 'completed')
            ->filter(fn (Transaction $t) => empty($t->released_at))
            ->sum(fn (Transaction $t) => self::net($t));

        $paid = $secured
            ->where('status', 'completed')
            ->filter(fn (Transaction $t) => !empty($t->released_at))
            ->sum(fn (Transaction $t) => self::net($t));

        return [
            'pending' => round($pending, 2),
            'processing' => round($processing, 2),
            'paid' => round($paid, 2),
        ];
    }

    /**
     * Récapitulatif d'un mois : montant total de ventes (tous modes, argent
     * réel — espèces incluses) et part sécurisée (en ligne).
     *
     * @return array{sales: float, secured: float}
     */
    public static function monthlyTotals(iterable $sales, int $year, int $month): array
    {
        $inMonth = collect($sales)->filter(function (Transaction $t) use ($year, $month) {
            $d = $t->created_at;

            return $d && (int) $d->year === $year && (int) $d->month === $month;
        });

        return [
            'sales' => round($inMonth->sum(fn (Transaction $t) => self::net($t)), 2),
            'secured' => round(self::securedOnly($inMonth)->sum(fn (Transaction $t) => self::net($t)), 2),
        ];
    }

    /**
     * Totaux d'argent par mode sur une collection (journal / admin).
     *
     * @return array<string, float>
     */
    public static function totalsByMode(iterable $sales): array
    {
        $out = ['online' => 0.0, 'cash' => 0.0, 'gift' => 0.0, 'exchange' => 0.0, 'other' => 0.0];

        foreach ($sales as $t) {
            $out[self::mode($t)] += self::net($t);
        }

        return array_map(fn ($v) => round($v, 2), $out);
    }
}
