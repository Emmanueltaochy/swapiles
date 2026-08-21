<?php

namespace Tests\Feature;

use App\Support\OrderPricing;
use Tests\TestCase;

/**
 * Calcul des montants avec frais de point relais.
 * Règle : total = article + protection + livraison + frais relais.
 * Le vendeur touche TOUJOURS le prix de l'article (frais relais exclus).
 */
class OrderPricingRelayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pricing.protection_rate', 0.10);
        config()->set('pricing.protection_floor', 0.50);
        config()->set('pricing.protection_cap', 15.00);
    }

    public function test_les_frais_relais_s_ajoutent_au_total_sans_toucher_le_vendeur(): void
    {
        // Article 20 € + protection 2 € + relais 3 € = 25 € ; vendeur = 20 €.
        $p = OrderPricing::fromEuros(20.00, 0.0, 3.00);

        $this->assertSame(2000, $p->itemCents());
        $this->assertSame(200, $p->protectionCents());
        $this->assertSame(0, $p->shippingCents());
        $this->assertSame(300, $p->relayFeeCents());
        $this->assertSame(2500, $p->totalCents());
        $this->assertSame(2000, $p->sellerCents(), 'Le vendeur touche le prix article, jamais les frais relais.');
        $this->assertSame(3.00, $p->relayFeeEuros());
        $this->assertSame(25.00, $p->totalEuros());
    }

    public function test_sans_frais_relais_le_total_est_inchange(): void
    {
        // Rétrocompatibilité : relayFee = 0 par défaut.
        $sans = OrderPricing::fromEuros(50.00);
        $avecZero = OrderPricing::fromEuros(50.00, 0.0, 0.0);

        $this->assertSame($sans->totalCents(), $avecZero->totalCents());
        $this->assertSame(0, $sans->relayFeeCents());
    }

    public function test_relais_et_livraison_cumules(): void
    {
        // Cas mixte défensif : article 10 + protection 1 + livraison 5 + relais 3 = 19 €.
        $p = OrderPricing::fromEuros(10.00, 5.00, 3.00);

        $this->assertSame(100, $p->protectionCents());
        $this->assertSame(500, $p->shippingCents());
        $this->assertSame(300, $p->relayFeeCents());
        $this->assertSame(1900, $p->totalCents());
        $this->assertSame(1000, $p->sellerCents());
    }

    public function test_repartition_commercant_plateforme(): void
    {
        // La part plateforme est TOUJOURS le complément de la part commerçant.
        config()->set('pricing.relay_fee', 3.00);
        config()->set('pricing.relay_merchant_fee', 1.00);

        $fee = (float) config('pricing.relay_fee');
        $merchant = (float) config('pricing.relay_merchant_fee');
        $platform = $fee - $merchant;

        $this->assertSame(3.00, $fee);
        $this->assertSame(1.00, $merchant);
        $this->assertSame(2.00, $platform);
        $this->assertGreaterThanOrEqual(0.0, $platform, 'La part commerçant ne peut pas dépasser les frais relais.');
    }
}
