<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RapportPartenaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartenairesRseTest extends TestCase
{
    use RefreshDatabase;

    private function relais(array $plus = []): RelayPoint
    {
        return RelayPoint::create($plus + ['name' => 'Accueil hypermarché', 'territoire' => 'La Réunion', 'city' => 'Saint-Denis', 'is_active' => true]);
    }

    public function test_rapport_d_impact_mensuel_sans_donnees_personnelles(): void
    {
        $point = $this->relais(['partenaire_enseigne' => 'Super Enseigne', 'partenaire_depuis' => now()->subMonth()]);
        $lea = User::create(['name' => 'Lea Martin', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $tom = User::create(['name' => 'Tom Durand', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        foreach (['collected', 'deposited', null] as $i => $etat) {
            $l = Listing::create(['user_id' => $lea->id, 'title' => 'Robe ' . $i, 'description' => 'Description de test assez longue.', 'price' => 10, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'femme']);
            Transaction::create([
                'listing_id' => $l->id, 'seller_id' => $lea->id, 'buyer_id' => $tom->id, 'amount' => 12, 'seller_amount' => 10,
                'commission' => 0, 'buyer_protection_fee' => 1, 'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
                'delivery_method' => 'relay', 'relay_point_id' => $point->id, 'relay_status' => $etat, 'status' => 'paid', 'shipping_status' => 'pending',
                'stripe_payment_intent_id' => 'pi_' . $i,
            ]);
        }

        $c = RapportPartenaire::mois($point, now());
        $this->assertSame(3, $c['colis']);
        $this->assertSame(3, $c['passages'], '2 dépôts + 1 retrait.');
        $this->assertEquals(45, $c['co2'], '3 vêtements × 15 kg CO2e.');

        $this->get($point->lienRapport())->assertOk()
            ->assertSee('Super Enseigne')->assertSee('Passages en magasin')
            ->assertDontSee('Lea Martin')->assertDontSee('tom@ex.com');

        $this->get('/partenaires/rapport/' . str_repeat('x', 40))->assertNotFound();
    }

    public function test_badge_partenaire_et_page_enseignes(): void
    {
        $this->relais(['partenaire_enseigne' => 'Super Enseigne']);
        $this->relais(['name' => 'Épicerie du coin']);

        $this->get(route('relay.points', ['ile' => 'La Réunion']))->assertOk()
            ->assertSee('🤝 Partenaire Super Enseigne')->assertSeeInOrder(['Accueil hypermarché', 'Épicerie du coin']);
        $this->get(route('partenaires.index'))->assertOk()
            ->assertSee('Faites de votre magasin un acteur du réemploi local')->assertSee('Super Enseigne');
    }
}
