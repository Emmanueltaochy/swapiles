<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 tunnel : main propre par défaut + gratuite, Colissimo masqué si le vendeur
 * n'a pas d'adresse, lien retour.
 */
class CheckoutTunnelTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::create([
            'name' => 'Acheteur', 'email' => 'b_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
    }

    private function seller(bool $withAddress): User
    {
        return User::create([
            'name' => 'Vendeur', 'email' => 's_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
            'stripe_account_id' => 'acct_x', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
            'address_line1' => $withAddress ? '10 rue des Îles' : null,
            'postal_code' => $withAddress ? '97400' : null,
            'city' => $withAddress ? 'Saint-Denis' : null,
        ]);
    }

    private function listing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Sac', 'price' => 7.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true,
            'allows_colissimo' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_main_propre_par_defaut_et_gratuite(): void
    {
        $seller = $this->seller(withAddress: true);
        $listing = $this->listing($seller);

        $response = $this->actingAs($this->buyer())->get(route('checkout.show', $listing));

        $response->assertOk();
        $response->assertSee('Retour à l\'annonce', false);
        $response->assertSee('Remise en main propre', false);
        $response->assertSee('Gratuit', false);
        // Le radio main propre est présélectionné.
        $response->assertSee('value="hand_delivery" data-delivery', false);
        $this->assertMatchesRegularExpression(
            '/value="hand_delivery"[^>]*checked/s',
            $response->getContent(),
            'La remise en main propre doit être cochée par défaut.'
        );
    }

    public function test_colissimo_masque_si_vendeur_sans_adresse(): void
    {
        $seller = $this->seller(withAddress: false);
        $listing = $this->listing($seller);

        $response = $this->actingAs($this->buyer())->get(route('checkout.show', $listing));

        $response->assertOk();
        $response->assertDontSee('Livraison Colissimo', false);
    }

    public function test_colissimo_visible_si_vendeur_avec_adresse(): void
    {
        $seller = $this->seller(withAddress: true);
        $listing = $this->listing($seller);

        $response = $this->actingAs($this->buyer())->get(route('checkout.show', $listing));

        $response->assertOk();
        $response->assertSee('Livraison Colissimo', false);
        $response->assertSee('Frais en plus', false);
    }
}
