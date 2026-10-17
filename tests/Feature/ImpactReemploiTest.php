<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ImpactReemploi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpactReemploiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bilan_par_type_ile_et_categorie(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $lea = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $tom = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'Martinique']);
        $annonce = fn (string $cat, string $ile, ?float $poids = null, string $statut = 'sold') => Listing::create([
            'user_id' => $lea->id, 'title' => 'Article', 'description' => 'Description de test assez longue.', 'price' => 10,
            'status' => $statut, 'listing_type' => 'achat', 'territoire' => $ile, 'category_level1' => $cat, 'weight_kg' => $poids,
        ]);
        $vente = fn (Listing $l, string $mode, array $plus = []) => Transaction::create($plus + [
            'listing_id' => $l->id, 'seller_id' => $lea->id, 'buyer_id' => $tom->id, 'amount' => 10, 'seller_amount' => 10,
            'commission' => 0, 'buyer_protection_fee' => 0, 'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => $mode,
            'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'received',
        ]);

        $vente($annonce('femme', 'La Réunion'), 'cb');                    // 0,5 kg · 15 kg CO2
        $vente($annonce('high-tech', 'Martinique', 0.2), 'especes');      // 0,2 kg (saisi) · 60
        $vente($annonce('maison', 'La Réunion'), 'echange');              // 3 kg · 30
        $annonce('enfant', 'La Réunion');                                 // ancien « Vendue » : 0,4 kg · 8
        $vente($annonce('femme', 'La Réunion', null, 'published'), 'especes', ['acheteur_conteste_at' => now()]); // contestée : 0

        $b = ImpactReemploi::bilan();
        $this->assertSame(['articles' => 4, 'kg' => 4.1, 'co2' => 113.0], $b['total']);
        $this->assertSame(565, $b['km_voiture']);
        $this->assertSame(['en_ligne', 'main_propre', 'echange', 'ancien'], array_keys($b['par_type']));
        $this->assertSame(3, $b['par_ile']['La Réunion']['articles']);
        $this->assertEquals(60, $b['par_categorie']['High-tech']['co2']);

        $this->actingAs($admin)->get('/admin/impact-reemploi')->assertOk()
            ->assertSee('Articles réemployés')->assertSee('CO₂ évité')->assertSee('Méthode de calcul');
    }
}
