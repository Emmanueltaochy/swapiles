<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Amélioration UX #2 : le formulaire Colissimo doit renvoyer une erreur PRÉCISE
 * par champ manquant (pas un message global), avec un texte explicite.
 */
class CheckoutColissimoValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_champ_manquant_a_son_erreur_precise(): void
    {
        $seller = User::create([
            'name' => 'V', 'email' => 's@ex.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'stripe_account_id' => 'acct_x',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
            'address_line1' => '1 rue X', 'postal_code' => '97400', 'city' => 'Saint-Denis',
        ]);
        $buyer = User::create([
            'name' => 'A', 'email' => 'a@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Sac', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_colissimo' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        // Colissimo sélectionné mais formulaire vide.
        $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'colissimo',
            'colissimo_delivery_type' => 'home',
        ]);

        $response->assertSessionHasErrors([
            'buyer_full_name', 'buyer_phone', 'shipping_address_line1',
            'shipping_postal_code', 'shipping_city', 'shipping_territory',
        ]);

        // Message précis (pas générique) sur le téléphone.
        $errors = session('errors');
        $this->assertStringContainsString('téléphone est obligatoire', $errors->first('buyer_phone'));
        $this->assertStringContainsString('adresse est obligatoire', $errors->first('shipping_address_line1'));
    }

    public function test_formulaire_colissimo_complet_passe_la_validation(): void
    {
        $seller = User::create([
            'name' => 'V', 'email' => 's2@ex.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'stripe_account_id' => 'acct_y',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
            'address_line1' => '1 rue X', 'postal_code' => '97400', 'city' => 'Saint-Denis',
        ]);
        $buyer = User::create([
            'name' => 'A', 'email' => 'a2@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Sac', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_colissimo' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
            'weight_kg' => 0.5,
        ]);

        $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'colissimo',
            'colissimo_delivery_type' => 'home',
            'buyer_full_name' => 'Jean Test',
            'buyer_phone' => '0692000000',
            'shipping_address_line1' => '3 rue des Îles',
            'shipping_postal_code' => '97410',
            'shipping_city' => 'Saint-Pierre',
            'shipping_territory' => 'reunion',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
