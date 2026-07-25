<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Point de contrôle #2 : les lignes héritées portent seller_amount = 0 (DEFAULT,
 * pas NULL). Le backfill doit les recalculer en amount - protection - livraison
 * SANS jamais toucher une ligne déjà correctement renseignée.
 */
class SellerAmountBackfillTest extends TestCase
{
    use RefreshDatabase;

    private array $parents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parents = $this->seedParents();
    }

    private function seedParents(): array
    {
        $seller = User::create([
            'name' => 'Vendeur', 'email' => 'seller_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $buyer = User::create([
            'name' => 'Acheteur', 'email' => 'buyer_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 3.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        return [$listing->id, $seller->id, $buyer->id];
    }

    private function insertTx(array $overrides): int
    {
        [$listingId, $sellerId, $buyerId] = $this->parents;

        return DB::table('transactions')->insertGetId(array_merge([
            'listing_id' => $listingId,
            'seller_id' => $sellerId,
            'buyer_id' => $buyerId,
            'amount' => 0,
            'commission' => 0,
            'platform_commission' => 0,
            'buyer_protection_fee' => 0,
            'shipping_fee' => 0,
            'seller_amount' => 0,
            'currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_backfill_corrige_les_lignes_legacy_et_preserve_les_bonnes(): void
    {
        // Ligne héritée : total 3,50 € (prix 3 + protection 0,50), seller_amount=0.
        $legacy = $this->insertTx([
            'amount' => 3.50,
            'buyer_protection_fee' => 0.50,
            'shipping_fee' => 0,
            'seller_amount' => 0,
        ]);

        // Ligne héritée avec livraison : total 22 € = prix 15 + prot 2 + port 5.
        $legacyShipping = $this->insertTx([
            'amount' => 22.00,
            'buyer_protection_fee' => 2.00,
            'shipping_fee' => 5.00,
            'seller_amount' => 0,
        ]);

        // Ligne déjà correcte : ne doit PAS être modifiée.
        $good = $this->insertTx([
            'amount' => 11.00,
            'buyer_protection_fee' => 1.00,
            'shipping_fee' => 0,
            'seller_amount' => 10.00,
        ]);

        $migration = require database_path('migrations/2026_07_25_190000_backfill_seller_amount_on_transactions.php');
        $migration->up();

        // Legacy sans port : vendeur = 3,50 - 0,50 = 3,00.
        $this->assertEqualsWithDelta(3.00, (float) DB::table('transactions')->where('id', $legacy)->value('seller_amount'), 0.001);

        // Legacy avec port : vendeur = 22 - 2 - 5 = 15,00 (le port ne va pas au vendeur ici).
        $this->assertEqualsWithDelta(15.00, (float) DB::table('transactions')->where('id', $legacyShipping)->value('seller_amount'), 0.001);

        // Ligne correcte : inchangée.
        $this->assertEqualsWithDelta(10.00, (float) DB::table('transactions')->where('id', $good)->value('seller_amount'), 0.001);
    }

    public function test_backfill_ne_descend_jamais_sous_zero(): void
    {
        // Donnée aberrante : protection+port > amount -> vendeur borné à 0 (pas négatif).
        $weird = $this->insertTx([
            'amount' => 1.00,
            'buyer_protection_fee' => 2.00,
            'shipping_fee' => 0,
            'seller_amount' => 0,
        ]);

        $migration = require database_path('migrations/2026_07_25_190000_backfill_seller_amount_on_transactions.php');
        $migration->up();

        $this->assertEqualsWithDelta(0.0, (float) DB::table('transactions')->where('id', $weird)->value('seller_amount'), 0.001);
    }
}
