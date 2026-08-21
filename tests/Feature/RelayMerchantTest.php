<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Espace commerçant : réception, remise avec code, solde (1 € par colis remis).
 */
class RelayMerchantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.relay_points', true);
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U '.$email, 'email' => $email,
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
    }

    private function relay(?User $manager = null): RelayPoint
    {
        return RelayPoint::create([
            'name' => 'Boutique', 'territoire' => 'La Réunion', 'city' => 'Saint-Denis',
            'is_active' => true, 'manager_user_id' => $manager?->id,
        ]);
    }

    private function tx(RelayPoint $relay, User $seller, User $buyer, string $relayStatus, string $code = 'ABC234'): Transaction
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 20,
            'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);

        return Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'relay', 'relay_point_id' => $relay->id, 'relay_fee' => 3,
            'relay_merchant_fee' => 1, 'relay_status' => $relayStatus, 'relay_pickup_code' => $code,
            'status' => $relayStatus === 'collected' ? 'completed' : 'paid',
            'shipping_status' => $relayStatus === 'awaiting_deposit' ? 'pending' : 'shipped',
        ]);
    }

    public function test_le_solde_somme_les_colis_remis_uniquement(): void
    {
        $manager = $this->user('m@ex.com');
        $seller = $this->user('s@ex.com');
        $buyer = $this->user('b@ex.com');
        $relay = $this->relay($manager);

        // 3 colis remis (collected) + 1 en attente : solde = 3 × 1 € = 3 €.
        $this->tx($relay, $seller, $buyer, 'collected');
        $this->tx($relay, $seller, $buyer, 'collected');
        $this->tx($relay, $seller, $buyer, 'collected');
        $this->tx($relay, $seller, $buyer, 'deposited');

        $this->assertSame(3.0, $relay->earnedBalance());
        $this->assertSame(3, $relay->collectedCount());
    }

    public function test_reception_confirmee_par_le_commercant(): void
    {
        $manager = $this->user('m2@ex.com');
        $relay = $this->relay($manager);
        $tx = $this->tx($relay, $this->user('s2@ex.com'), $this->user('b2@ex.com'), 'awaiting_deposit');

        $this->actingAs($manager)->patch(route('account.relay.deposit', $tx))->assertRedirect();
        $tx->refresh();
        $this->assertSame('deposited', $tx->relay_status);
        $this->assertNotNull($tx->relay_deposited_at);
    }

    public function test_remise_avec_bon_code_finalise_et_marque_collected(): void
    {
        $manager = $this->user('m3@ex.com');
        $relay = $this->relay($manager);
        $tx = $this->tx($relay, $this->user('s3@ex.com'), $this->user('b3@ex.com'), 'deposited', 'XYZ789');

        // Code saisi avec espaces / minuscules : doit être normalisé.
        $this->actingAs($manager)
            ->patch(route('account.relay.pickup', $tx), ['pickup_code' => ' xyz789 '])
            ->assertRedirect();

        $tx->refresh();
        $this->assertSame('collected', $tx->relay_status);
        $this->assertSame('completed', $tx->status);
        $this->assertNotNull($tx->relay_collected_at);
        // Le solde du relais intègre maintenant ce colis.
        $this->assertSame(1.0, $relay->earnedBalance());
    }

    public function test_remise_avec_mauvais_code_refusee(): void
    {
        $manager = $this->user('m4@ex.com');
        $relay = $this->relay($manager);
        $tx = $this->tx($relay, $this->user('s4@ex.com'), $this->user('b4@ex.com'), 'deposited', 'GOOD11');

        $this->actingAs($manager)
            ->patch(route('account.relay.pickup', $tx), ['pickup_code' => 'WRONG9'])
            ->assertSessionHasErrors('pickup_code');

        $tx->refresh();
        $this->assertSame('deposited', $tx->relay_status);
        $this->assertNotSame('completed', $tx->status);
    }

    public function test_un_commercant_ne_touche_pas_au_relais_d_un_autre(): void
    {
        $manager = $this->user('m5@ex.com');
        $autre = $this->user('autre@ex.com');
        $relay = $this->relay($manager);
        $tx = $this->tx($relay, $this->user('s5@ex.com'), $this->user('b5@ex.com'), 'deposited', 'CODE55');

        // Un utilisateur qui ne gère PAS ce relais est interdit.
        $this->actingAs($autre)
            ->patch(route('account.relay.pickup', $tx), ['pickup_code' => 'CODE55'])
            ->assertForbidden();
    }

    public function test_dashboard_inaccessible_sans_relais(): void
    {
        $sansRelais = $this->user('norelay@ex.com');
        $this->actingAs($sansRelais)->get(route('account.relay.dashboard'))->assertForbidden();
    }

    public function test_dashboard_accessible_au_commercant(): void
    {
        $manager = $this->user('m6@ex.com');
        $this->relay($manager);
        $this->actingAs($manager)->get(route('account.relay.dashboard'))->assertOk();
    }
}
