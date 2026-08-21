<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page vitrine « Devenir point relais ».
 */
class RelayPartnerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_est_accessible_publiquement(): void
    {
        config()->set('pricing.relay_merchant_fee', 1.00);

        $this->get(route('relay.partner'))
            ->assertOk()
            ->assertSee('Devenir point relais', false)
            ->assertSee('1 € par colis', false);
    }

    public function test_les_stats_sont_masquees_quand_trop_peu_de_colis(): void
    {
        // Aucun colis remis : la bande de statistiques ne doit pas apparaître.
        $this->get(route('relay.partner'))
            ->assertOk()
            ->assertDontSee('colis remis par nos partenaires', false);
    }

    public function test_les_stats_apparaissent_au_dela_du_seuil(): void
    {
        $seller = User::create(['name' => 'S', 'email' => 's@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        $buyer = User::create(['name' => 'B', 'email' => 'b@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        $relay = RelayPoint::create(['name' => 'R', 'territoire' => 'La Réunion', 'is_active' => true]);

        // 20 colis remis (seuil atteint).
        for ($i = 0; $i < 20; $i++) {
            $listing = Listing::create([
                'user_id' => $seller->id, 'title' => 'A'.$i, 'price' => 20,
                'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            ]);
            Transaction::create([
                'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
                'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
                'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
                'delivery_method' => 'relay', 'relay_point_id' => $relay->id, 'relay_fee' => 3,
                'relay_merchant_fee' => 1, 'relay_status' => 'collected',
                'status' => 'completed', 'shipping_status' => 'received',
            ]);
        }

        $this->get(route('relay.partner'))
            ->assertOk()
            ->assertSee('colis remis par nos partenaires', false);
    }
}
