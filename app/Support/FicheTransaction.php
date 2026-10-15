<?php

namespace App\Support;

use App\Models\Message;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tableau de bord d'une vente, pour l'administration : où en est-elle,
 * qui sont l'acheteur et le vendeur, où va l'argent, qu'est-ce qui cloche.
 *
 * Montants : `amount` est ce que l'acheteur a payé en tout
 * (prix + protection + livraison + frais de point relais) ; `seller_amount`
 * est ce que touche le vendeur. Calculs faits en centimes.
 */
class FicheTransaction
{
    /** Estimation des frais Stripe (cartes européennes) : 1,5 % + 0,25 €. */
    public const FRAIS_STRIPE_POURCENT = 0.015;

    public const FRAIS_STRIPE_FIXE = 0.25;

    /** Au-delà, une vente payée non expédiée mérite un coup d'œil. */
    public const JOURS_AVANT_RELANCE_EXPEDITION = 5;

    public const JOURS_LIVRAISON_LONGUE = 15;

    // --- Argent --------------------------------------------------------------

    /** @return array<string,float|bool> en euros */
    public static function argent(Transaction $t): array
    {
        $c = fn ($euros) => (int) round(((float) $euros) * 100);

        $total = $c($t->amount);
        $vendeur = $c($t->seller_amount ?? $t->amount);
        $commission = $c($t->commission);
        $protection = $c($t->buyer_protection_fee);
        $livraison = $c($t->shipping_fee);
        $relais = $c($t->relay_fee);
        $partCommercant = min($relais, $c($t->relay_merchant_fee));
        $partRelaisSwapiles = $relais - $partCommercant;
        // Parrainage : crédit déduit du total (payé par Swap'Îles) et protection offerte.
        $credit = $c($t->credit_utilise ?? 0);
        $protectionOfferte = $c($t->protection_offerte ?? 0);

        $revenu = $protection + $commission + $partRelaisSwapiles - $credit;
        $enLigne = ! $t->estDeclaree();
        $fraisStripe = $enLigne && $total > 0
            ? (int) round($total * self::FRAIS_STRIPE_POURCENT + self::FRAIS_STRIPE_FIXE * 100)
            : 0;

        // Ce que l'acheteur aurait dû payer d'après le détail enregistré.
        $attendu = $vendeur + $commission + $protection + $livraison + $relais - $credit;

        $e = fn (int $centimes): float => round($centimes / 100, 2);

        return [
            'total_acheteur' => $e($total),
            'prix_article' => $e($vendeur + $commission),
            'pour_le_vendeur' => $e($vendeur),
            'commission' => $e($commission),
            'protection' => $e($protection),
            'livraison' => $e($livraison),
            'frais_relais' => $e($relais),
            'part_commercant' => $e($partCommercant),
            'part_relais_swapiles' => $e($partRelaisSwapiles),
            'credit_utilise' => $e($credit),
            'protection_offerte' => $e($protectionOfferte),
            'revenu_swapiles' => $e($revenu),
            'frais_stripe_estimes' => $e($fraisStripe),
            'marge_estimee' => $e($revenu - $fraisStripe),
            'ecart' => $e($total - $attendu),
            'en_ligne' => $enLigne,
        ];
    }

    // --- Où en est la vente ? ---------------------------------------------------

    /** @return array{libelle:string,couleur:string,depuis:?Carbon} */
    public static function etape(Transaction $t): array
    {
        $etape = fn (string $libelle, string $couleur, $depuis) => [
            'libelle' => $libelle,
            'couleur' => $couleur,
            'depuis' => $depuis ? Carbon::parse($depuis) : null,
        ];

        if ($t->status === 'refunded') {
            return $etape('Remboursée', 'danger', $t->updated_at);
        }
        if ($t->status === 'cancelled') {
            return $etape('Annulée', 'gray', $t->updated_at);
        }
        if ($t->estDeclaree()) {
            return $etape('Déclarée par le vendeur (main propre)', 'gray', $t->completed_at ?? $t->created_at);
        }
        if ($t->status === 'pending') {
            return $etape('En attente du paiement', 'warning', $t->created_at);
        }
        if ($t->transferred_at || $t->released_at || $t->wallet_status === 'paid') {
            return $etape('Terminée, vendeur payé', 'success', $t->transferred_at ?? $t->released_at);
        }
        if ($t->received_at || $t->status === 'completed') {
            return $etape('Reçue, versement au vendeur en attente', 'info', $t->received_at ?? $t->completed_at);
        }
        if ($t->delivery_method === 'relay' && $t->relay_deposited_at) {
            return $etape('Au point relais, en attente de retrait', 'info', $t->relay_deposited_at);
        }
        if ($t->shipped_at || $t->hasBeenShipped()) {
            return $etape('En cours de livraison', 'info', $t->shipped_at ?? $t->updated_at);
        }
        if ($t->delivery_method === 'hand_delivery') {
            return $etape('Payée, remise en main propre à faire', 'warning', $t->created_at);
        }

        return $etape('Payée, à expédier par le vendeur', 'warning', $t->created_at);
    }

    /**
     * Ce qui mérite l'attention de l'équipe, du plus grave au moins grave.
     *
     * @return array<int,array{niveau:string,texte:string}>
     */
    public static function alertes(Transaction $t): array
    {
        $alertes = [];
        $argent = self::argent($t);
        $etape = self::etape($t);
        $jours = $etape['depuis'] ? (int) $etape['depuis']->diffInDays(now()) : 0;

        if (abs($argent['ecart']) >= 0.01) {
            $alertes[] = ['niveau' => 'danger', 'texte' => 'Les montants ne tombent pas juste : écart de ' . TableauDeBord::euros($argent['ecart']) . ' entre le total payé et son détail.'];
        }
        if ($t->acheteur_conteste_at) {
            $alertes[] = ['niveau' => 'danger', 'texte' => "Le membre désigné comme acheteur dit ne pas avoir acheté cet article."];
        }
        if ($t->auto_review_flagged_at && ! in_array($t->status, ['completed', 'refunded', 'cancelled'], true)) {
            $alertes[] = ['niveau' => 'danger', 'texte' => 'Vente signalée automatiquement : à vérifier.'];
        }
        if (str_starts_with($etape['libelle'], 'Payée, à expédier') && $jours >= self::JOURS_AVANT_RELANCE_EXPEDITION) {
            $alertes[] = ['niveau' => 'warning', 'texte' => "Payée il y a {$jours} jours et toujours pas expédiée."];
        }
        if ($etape['libelle'] === 'En cours de livraison' && $jours >= self::JOURS_LIVRAISON_LONGUE) {
            $alertes[] = ['niveau' => 'warning', 'texte' => "En livraison depuis {$jours} jours : vérifier le suivi."];
        }
        if ($t->attendReponseAcheteur()) {
            $alertes[] = ['niveau' => 'warning', 'texte' => "L'acheteur désigné n'a pas encore confirmé l'achat."];
        }
        if ($t->fiabilite() === 'declaree_inconnu') {
            $alertes[] = ['niveau' => 'info', 'texte' => "Acheteur inconnu : seulement la parole du vendeur (peut-être une annonce retirée)."];
        }

        return $alertes;
    }

    // --- Les personnes ---------------------------------------------------------

    /** Repères sur l'acheteur ou le vendeur. @return array<string,mixed> */
    public static function profil(User $membre): array
    {
        $avis = Review::where('reviewed_id', $membre->id);

        return [
            'achats' => Transaction::where('buyer_id', $membre->id)->whereIn('status', TableauDeBord::STATUTS_VENDUS)->count(),
            'ventes' => Transaction::where('seller_id', $membre->id)->whereIn('status', TableauDeBord::STATUTS_VENDUS)->count(),
            'remboursements' => Transaction::where(fn ($q) => $q->where('buyer_id', $membre->id)->orWhere('seller_id', $membre->id))->where('status', 'refunded')->count(),
            'nb_avis' => (clone $avis)->count(),
            'note' => (clone $avis)->count() ? round((float) (clone $avis)->avg('rating'), 1) : null,
            'membre_depuis' => $membre->created_at,
        ];
    }

    /** Messages échangés entre l'acheteur et le vendeur à propos de l'annonce. */
    public static function conversation(Transaction $t, int $limite = 30): Collection
    {
        if (! $t->buyer_id || ! $t->listing_id) {
            return collect();
        }

        return Message::query()
            ->where('listing_id', $t->listing_id)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('sender_id', $t->seller_id)->where('receiver_id', $t->buyer_id))
                ->orWhere(fn ($q) => $q->where('sender_id', $t->buyer_id)->where('receiver_id', $t->seller_id)))
            ->latest()
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->reverse()
            ->values();
    }
}
