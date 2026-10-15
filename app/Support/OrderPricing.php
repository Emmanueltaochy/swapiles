<?php

namespace App\Support;

/**
 * Source de vérité UNIQUE des montants d'une commande, en CENTIMES (entiers).
 *
 * Règles (validées produit) :
 *   protection = clamp( round(prix * rate, 2, HALF_UP), floor, cap )
 *   commission vendeur = 0  -> le vendeur reçoit exactement le prix affiché
 *   total = prix + protection + livraison
 *   remboursement acheteur = prix + protection (intégral ; les frais Stripe
 *                            ne sont pas restitués par Stripe -> coût variable)
 *
 * Cette valeur est calculée UNE SEULE FOIS côté serveur. Elle alimente à la
 * fois l'affichage (fiche, récap tunnel) ET le montant du PaymentIntent. Elle
 * n'est jamais recalculée côté client : le montant envoyé à Stripe est
 * TOUJOURS égal au « Total à payer » affiché.
 */
class OrderPricing
{
    private function __construct(
        public readonly int $itemCents,
        public readonly int $protectionCents,
        public readonly int $shippingCents,
        public readonly int $relayFeeCents = 0,
        // Parrainage : protection acheteur offerte (montant non facturé).
        public readonly int $protectionOfferteCents = 0,
        // Crédit du portefeuille déduit du total (payé par Swap'Îles).
        public readonly int $creditCents = 0,
    ) {
    }

    /**
     * Parrainage : la protection acheteur est offerte au filleul. Le montant
     * offert est gardé pour l'affichage et le suivi.
     */
    public function sansProtection(): self
    {
        return new self($this->itemCents, 0, $this->shippingCents, $this->relayFeeCents, $this->protectionCents + $this->protectionOfferteCents, 0);
    }

    /**
     * Crédit du portefeuille, plafonné pour laisser au moins le minimum
     * accepté par Stripe (0,50 €) à payer par carte.
     */
    public function avecCredit(int $disponibleCents): self
    {
        $minimum = (int) config('referral.paiement_minimum_centimes', 50);
        $credit = max(0, min($disponibleCents, $this->totalCents() - $minimum));

        return new self($this->itemCents, $this->protectionCents, $this->shippingCents, $this->relayFeeCents, $this->protectionOfferteCents, $credit);
    }

    public function protectionOfferteCents(): int
    {
        return $this->protectionOfferteCents;
    }

    public function creditCents(): int
    {
        return $this->creditCents;
    }

    /** Montant réellement débité sur la carte (total − crédit). */
    public function aPayerCents(): int
    {
        return $this->totalCents() - $this->creditCents;
    }

    public function protectionOfferteEuros(): float
    {
        return $this->protectionOfferteCents / 100;
    }

    public function creditEuros(): float
    {
        return $this->creditCents / 100;
    }

    public function aPayerEuros(): float
    {
        return $this->aPayerCents() / 100;
    }

    /** Construit depuis des montants en CENTIMES. */
    public static function make(int $itemCents, int $shippingCents = 0, int $relayFeeCents = 0): self
    {
        $itemCents = max(0, $itemCents);
        $shippingCents = max(0, $shippingCents);
        $relayFeeCents = max(0, $relayFeeCents);

        return new self($itemCents, self::protectionFor($itemCents), $shippingCents, $relayFeeCents);
    }

    /** Construit depuis des montants en EUROS (float). */
    public static function fromEuros(float $itemEuros, float $shippingEuros = 0.0, float $relayFeeEuros = 0.0): self
    {
        return self::make(
            self::eurosToCents($itemEuros),
            self::eurosToCents($shippingEuros),
            self::eurosToCents($relayFeeEuros),
        );
    }

    /**
     * Protection acheteur, en centimes.
     * Ordre STRICT : round(prix * rate, 2, HALF_UP) PUIS clamp(floor, cap).
     */
    public static function protectionFor(int $itemCents): int
    {
        $rate = (float) config('pricing.protection_rate', 0.10);
        $floorCents = self::eurosToCents((float) config('pricing.protection_floor', 0.50));
        $capCents = self::eurosToCents((float) config('pricing.protection_cap', 15.00));

        // 1) round au centime, HALF_UP (comportement par défaut de round() en PHP).
        $rawEuros = round(($itemCents / 100) * $rate, 2, PHP_ROUND_HALF_UP);
        $rawCents = self::eurosToCents($rawEuros);

        // 2) clamp.
        return max($floorCents, min($capCents, $rawCents));
    }

    // ----- Montants dérivés (centimes) -----

    public function itemCents(): int
    {
        return $this->itemCents;
    }

    public function protectionCents(): int
    {
        return $this->protectionCents;
    }

    public function shippingCents(): int
    {
        return $this->shippingCents;
    }

    /** Frais de point relais (commerçant + plateforme), ajoutés au total. */
    public function relayFeeCents(): int
    {
        return $this->relayFeeCents;
    }

    public function totalCents(): int
    {
        return $this->itemCents + $this->protectionCents + $this->shippingCents + $this->relayFeeCents;
    }

    /** Commission vendeur = 0 -> le vendeur touche exactement le prix de l'article. */
    public function commissionCents(): int
    {
        return 0;
    }

    public function sellerCents(): int
    {
        return $this->itemCents;
    }

    /** Remboursement acheteur en cas d'annulation : prix + protection (intégral). */
    public function refundCents(): int
    {
        return $this->itemCents + $this->protectionCents;
    }

    // ----- Accès en euros (affichage / stockage colonnes DECIMAL) -----

    public function itemEuros(): float
    {
        return $this->itemCents / 100;
    }

    public function protectionEuros(): float
    {
        return $this->protectionCents / 100;
    }

    public function shippingEuros(): float
    {
        return $this->shippingCents / 100;
    }

    public function relayFeeEuros(): float
    {
        return $this->relayFeeCents / 100;
    }

    public function totalEuros(): float
    {
        return $this->totalCents() / 100;
    }

    public function sellerEuros(): float
    {
        return $this->sellerCents() / 100;
    }

    public function refundEuros(): float
    {
        return $this->refundCents() / 100;
    }

    // ----- Conversion -----

    public static function eurosToCents(float $euros): int
    {
        return (int) round($euros * 100, 0, PHP_ROUND_HALF_UP);
    }
}
