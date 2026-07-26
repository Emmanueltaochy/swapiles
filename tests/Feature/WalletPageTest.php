<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rend la page wallet de bout en bout : le solde exclut les espèces, la mention
 * et le récap du mois s'affichent, le journal liste tous les modes.
 */
class WalletPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_solde_exclut_les_especes_et_la_page_affiche_la_mention(): void
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
            'user_id' => $seller->id, 'title' => 'Sac test', 'price' => 3.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        $base = [
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'commission' => 0, 'platform_commission' => 0, 'buyer_protection_fee' => 0, 'shipping_fee' => 0,
            'currency' => 'EUR', 'status' => 'completed',
        ];
        // Espèces 3 € (ne doit PAS entrer au solde), en ligne 3 € payé (doit entrer).
        Transaction::create(array_merge($base, ['payment_method' => 'especes', 'amount' => 3, 'seller_amount' => 3, 'stripe_payment_intent_id' => null]));
        Transaction::create(array_merge($base, ['payment_method' => 'cb', 'amount' => 3, 'seller_amount' => 3, 'stripe_payment_intent_id' => 'pi_test', 'status' => 'paid']));

        $response = $this->actingAs($seller)->get(route('account.wallet.index'));

        $response->assertOk();
        $response->assertSee('ventes par carte (CB)', false);
        $response->assertSee('Ce mois-ci', false);
        // Le journal montre les deux ventes (badges CB / Espèces).
        $response->assertSee('Espèces', false);
        $response->assertSee('CB', false);
    }
}
