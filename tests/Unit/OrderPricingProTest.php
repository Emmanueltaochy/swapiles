<?php

namespace Tests\Unit;

use App\Support\OrderPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Montants d'une vente de PROFESSIONNEL : prix affiché = prix payé, commission
 * Swap'Îles retenue sur la part du pro. Tests obligatoires (calcul d'argent).
 */
class OrderPricingProTest extends TestCase
{
    /** @return array<string,array{0:int,1:float,2:int,3:int}> [prix, taux, commission, part pro] en centimes */
    public static function cas(): array
    {
        return [
            '89 € à 5 %' => [8900, 0.05, 445, 8455],
            '10 € à 5 %' => [1000, 0.05, 50, 950],
            '0,99 € à 5 % (arrondi 0,0495 → 0,05)' => [99, 0.05, 5, 94],
            '19,90 € à 8 %' => [1990, 0.08, 159, 1831],
            '0,10 € à 5 % (0,005 → 0,01)' => [10, 0.05, 1, 9],
            'taux 0 %' => [5000, 0.0, 0, 5000],
        ];
    }

    #[DataProvider('cas')]
    public function test_commission_et_part_du_pro(int $prix, float $taux, int $commission, int $partPro): void
    {
        $p = OrderPricing::pro($prix, 0, 0, $taux, false);

        $this->assertSame(0, $p->protectionCents(), 'Pas de protection acheteur chez un pro.');
        $this->assertSame($prix, $p->totalCents(), 'Prix affiché = prix payé.');
        $this->assertSame($commission, $p->commissionCents());
        $this->assertSame($partPro, $p->sellerCents());
        $this->assertSame($prix, $p->commissionCents() + $p->sellerCents(), 'Rien ne se perd : commission + part du pro = prix.');
    }

    public function test_livraison_du_pro_lui_revient(): void
    {
        // Basket 89 €, livraison du pro 5 € : le client paie 94 €.
        $p = OrderPricing::pro(8900, 500, 0, 0.05, true);

        $this->assertSame(9400, $p->totalCents());
        $this->assertSame(445, $p->commissionCents(), 'Commission sur le prix de l’article seulement, pas sur la livraison.');
        $this->assertSame(8455 + 500, $p->sellerCents());
    }

    public function test_colissimo_ne_revient_pas_au_pro(): void
    {
        // Colissimo : le port payé par le client sert à l'étiquette, pas au pro.
        $p = OrderPricing::pro(8900, 1250, 0, 0.05, false);

        $this->assertSame(10150, $p->totalCents());
        $this->assertSame(8455, $p->sellerCents());
    }

    public function test_point_relais_frais_en_plus_et_hors_part_du_pro(): void
    {
        $p = OrderPricing::pro(2000, 0, 300, 0.05, false);

        $this->assertSame(2300, $p->totalCents());
        $this->assertSame(100, $p->commissionCents());
        $this->assertSame(1900, $p->sellerCents());
    }

    public function test_credit_portefeuille_ne_change_pas_la_part_du_pro(): void
    {
        $p = OrderPricing::pro(8900, 0, 0, 0.05, false)->avecCredit(1000);

        $this->assertSame(1000, $p->creditCents());
        $this->assertSame(7900, $p->aPayerCents(), 'Le crédit est payé par Swap’Îles, pas par le pro.');
        $this->assertSame(445, $p->commissionCents());
        $this->assertSame(8455, $p->sellerCents());
    }

    public function test_euros_et_bornes(): void
    {
        $p = OrderPricing::proFromEuros(89.0, 5.0, 0.0, 0.05, true);
        $this->assertSame(94.0, $p->totalEuros());
        $this->assertSame(4.45, $p->commissionEuros());
        $this->assertSame(89.55, $p->sellerEuros());

        // Taux absurde : la commission ne dépasse jamais le prix.
        $this->assertSame(1000, OrderPricing::pro(1000, 0, 0, 3.0, false)->commissionCents());
        $this->assertSame(0, OrderPricing::pro(1000, 0, 0, -1.0, false)->commissionCents());
    }

    public function test_entre_particuliers_rien_ne_change(): void
    {
        $p = OrderPricing::fromEuros(89.0);

        $this->assertSame(0, $p->commissionCents());
        $this->assertSame(8900, $p->sellerCents());
        $this->assertSame(890, $p->protectionCents());
        $this->assertSame(9790, $p->totalCents());
    }
}
