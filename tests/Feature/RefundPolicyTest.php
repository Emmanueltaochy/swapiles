<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Décision produit sur le remboursement admin :
 *   - jamais expédié (pas de tracking) -> remboursement INTÉGRAL (port compris) ;
 *   - déjà expédié (tracking présent)  -> prix + protection uniquement.
 */
class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function tx(array $overrides): Transaction
    {
        $seller = User::create([
            'name' => 'V', 'email' => 's_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $buyer = User::create([
            'name' => 'A', 'email' => 'b_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'X', 'price' => 20.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        return Transaction::create(array_merge([
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'amount' => 29.42,            // prix 20 + protection 2 + port 7,42
            'buyer_protection_fee' => 2.00,
            'shipping_fee' => 7.42,
            'seller_amount' => 20.00,
            'currency' => 'EUR',
        ], $overrides));
    }

    public function test_jamais_expedie_remboursement_integral_port_compris(): void
    {
        $tx = $this->tx(['tracking_number' => null]);

        $this->assertFalse($tx->hasBeenShipped());
        // 29,42 € intégral (le port est rendu).
        $this->assertEqualsWithDelta(29.42, $tx->refundAmountEuros(), 0.001);
    }

    public function test_expedie_remboursement_prix_plus_protection_sans_port(): void
    {
        $tx = $this->tx(['tracking_number' => '6A123456789']);

        $this->assertTrue($tx->hasBeenShipped());
        // 29,42 − 7,42 (port) = 22,00 € (prix 20 + protection 2).
        $this->assertEqualsWithDelta(22.00, $tx->refundAmountEuros(), 0.001);
    }

    public function test_remboursement_jamais_negatif(): void
    {
        $tx = $this->tx(['tracking_number' => 'X', 'amount' => 1.00, 'shipping_fee' => 5.00]);
        $this->assertEqualsWithDelta(0.0, $tx->refundAmountEuros(), 0.001);
    }
}
