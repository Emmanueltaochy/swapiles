<?php

namespace Tests\Unit;

use App\Support\OrderPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Valeurs par défaut explicites (rate 10 %, plancher 0,50 €, plafond 15,00 €).
        config()->set('pricing.protection_rate', 0.10);
        config()->set('pricing.protection_floor', 0.50);
        config()->set('pricing.protection_cap', 15.00);
    }

    /**
     * Table de bornes fournie par le produit (là où l'écran et Stripe divergent).
     *
     * @return array<string,array{0:float,1:float,2:float}> [prix, protectionAttendue, totalAttendu]
     */
    public static function pricingCases(): array
    {
        return [
            '3,00 € -> plancher' => [3.00, 0.50, 3.50],
            '5,00 € -> plancher' => [5.00, 0.50, 5.50],
            '10,00 €' => [10.00, 1.00, 11.00],
            '20,00 €' => [20.00, 2.00, 22.00],
            '150,00 € -> plafond' => [150.00, 15.00, 165.00],
            '300,00 € -> plafond' => [300.00, 15.00, 315.00],
        ];
    }

    #[DataProvider('pricingCases')]
    public function test_protection_et_total_remise_en_main_propre(float $prix, float $protection, float $total): void
    {
        $p = OrderPricing::fromEuros($prix); // main propre : livraison 0

        $this->assertSame((int) round($protection * 100), $p->protectionCents(), "protection pour {$prix} €");
        $this->assertSame((int) round($total * 100), $p->totalCents(), "total pour {$prix} €");
        // Vendeur : 0 % de commission -> exactement le prix affiché.
        $this->assertSame(0, $p->commissionCents());
        $this->assertSame((int) round($prix * 100), $p->sellerCents(), "vendeur pour {$prix} €");
        // Remboursement acheteur = prix + protection.
        $this->assertSame((int) round(($prix + $protection) * 100), $p->refundCents());
    }

    public function test_protectionCents_est_un_alias_public(): void
    {
        // protectionCents lisible directement (source de vérité d'affichage).
        $this->assertSame(50, OrderPricing::fromEuros(3.00)->protectionCents);
        $this->assertSame(100, OrderPricing::fromEuros(10.00)->protectionCents);
    }

    public function test_livraison_colissimo_ajoutee_au_total_sans_toucher_vendeur(): void
    {
        $p = OrderPricing::fromEuros(10.00, 7.42);

        $this->assertSame(100, $p->protectionCents(), 'protection inchangée par la livraison');
        $this->assertSame(742, $p->shippingCents);
        $this->assertSame(1000 + 100 + 742, $p->totalCents(), 'total = prix + protection + livraison');
        $this->assertSame(1000, $p->sellerCents(), 'le vendeur touche le prix, jamais la livraison ni la protection');
    }

    public function test_ordre_strict_round_half_up_puis_clamp(): void
    {
        // 25,55 € * 10 % = 2,555 -> round HALF_UP 2 déc. = 2,56 € (256 c), sous le plafond.
        $this->assertSame(256, OrderPricing::fromEuros(25.55)->protectionCents());

        // 2,55 € * 10 % = 0,255 -> round HALF_UP = 0,26 € PUIS clamp plancher -> 0,50 €.
        $this->assertSame(50, OrderPricing::fromEuros(2.55)->protectionCents());
    }

    public function test_constantes_configurables_sans_redeploiement(): void
    {
        config()->set('pricing.protection_rate', 0.05);
        config()->set('pricing.protection_floor', 1.00);
        config()->set('pricing.protection_cap', 8.00);

        // 10 € * 5 % = 0,50 -> clamp plancher 1,00 €.
        $this->assertSame(100, OrderPricing::fromEuros(10.00)->protectionCents());
        // 200 € * 5 % = 10,00 -> clamp plafond 8,00 €.
        $this->assertSame(800, OrderPricing::fromEuros(200.00)->protectionCents());
    }

    /**
     * Item 8 — marge nette plateforme sur un panier à 10 € : doit être positive.
     *
     * Revenu plateforme = protection acheteur (commission vendeur = 0 %).
     * Coût = frais Stripe (~1,5 % + 0,25 € par transaction, cartes EEA) prélevés
     * sur la TOTALITÉ du débit (prix + protection).
     *
     * 10 € : total débité 11,00 € -> Stripe ≈ 11,00*1,5% + 0,25 = 0,415 €
     *        marge nette = protection 1,00 € − 0,415 € = +0,585 € (POSITIVE).
     */
    public function test_marge_nette_positive_sur_panier_10_euros(): void
    {
        $p = OrderPricing::fromEuros(10.00);

        $stripeFeeCents = (int) round($p->totalCents() * 0.015) + 25;
        $platformRevenueCents = $p->protectionCents();          // commission 0
        $netCents = $platformRevenueCents - $stripeFeeCents;

        $this->assertSame(100, $platformRevenueCents);
        $this->assertSame(42, $stripeFeeCents);                 // 17 + 25
        $this->assertGreaterThan(0, $netCents, 'la marge nette doit être positive');
        $this->assertSame(58, $netCents);                       // +0,58 €
    }

    public function test_pas_d_artefact_de_flottant_sur_le_total(): void
    {
        // Les montants sont en centimes entiers : jamais de 11.000000001.
        $p = OrderPricing::fromEuros(19.99);
        $this->assertIsInt($p->totalCents());
        $this->assertSame(1999 + $p->protectionCents(), $p->totalCents());
    }
}
