<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Page produit : le nombre de ventes du vendeur compte aussi les ventes en main propre. */
class VentesVendeurPageProduitTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_ventes_en_main_propre_sont_comptees_sur_la_page_produit(): void
    {
        $vendeuse = User::create(['name' => 'Vendeuse', 'email' => 'v@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'transactions_count' => 0]);
        $acheteur = User::create(['name' => 'Acheteur', 'email' => 'a@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $champ = fn (string $titre, string $statut) => Listing::create(['user_id' => $vendeuse->id, 'title' => $titre, 'description' => 'Description de test assez longue.',
            'price' => 30, 'status' => $statut, 'listing_type' => 'achat', 'territoire' => 'La Réunion']);

        foreach (['Robe', 'Sac'] as $titre) {
            $vendue = $champ($titre, 'sold');
            Transaction::create(['listing_id' => $vendue->id, 'seller_id' => $vendeuse->id, 'buyer_id' => $acheteur->id, 'amount' => 30, 'commission' => 0,
                'buyer_protection_fee' => 0, 'shipping_fee' => 0, 'seller_amount' => 30, 'currency' => 'EUR', 'payment_method' => 'especes',
                'delivery_method' => 'hand_delivery', 'status' => 'completed', 'completed_at' => now()]);
        }
        $enLigne = $champ('Chaussures', 'published');

        $this->get(route('listings.show', $enLigne))->assertOk()->assertSee('2 vendues')->assertDontSee('0 transactions');
        $this->get(route('profiles.show', $vendeuse))->assertOk()->assertSee('2 vendues');
    }
}
