<?php

namespace Tests\Feature;

use App\Filament\Resources\RelayListings\RelayListingResource;
use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Onglet admin « Produits par relais » : liste les annonces pour lesquelles
 * un point relais est retenu (surcharge annonce ou défaut vendeur).
 */
class RelayListingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::create([
            'name' => 'V '.uniqid(), 'email' => uniqid().'@ex.com',
            'password' => bcrypt('x'), 'territoire' => 'La Réunion',
        ]);
    }

    private function cbListing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true,
        ]);
    }

    private function relay(string $name): RelayPoint
    {
        return RelayPoint::create(['name' => $name, 'territoire' => 'La Réunion', 'is_active' => true]);
    }

    public function test_liste_les_annonces_avec_relais_retenu_et_exclut_les_autres(): void
    {
        $r1 = $this->relay('R1');
        $r2 = $this->relay('R2');

        // L1 : défaut vendeur = R1.
        $s1 = $this->seller();
        $s1->acceptedRelayPoints()->sync([$r1->id]);
        $l1 = $this->cbListing($s1);

        // L2 : surcharge annonce = R2 (vendeur sans défaut).
        $s2 = $this->seller();
        $l2 = $this->cbListing($s2);
        $l2->relayPoints()->sync([$r2->id]);

        // L3 : CB mais aucun relais retenu -> exclue.
        $l3 = $this->cbListing($this->seller());

        // L4 : relais coché mais annonce NON CB -> exclue.
        $s4 = $this->seller();
        $s4->acceptedRelayPoints()->sync([$r1->id]);
        $l4 = Listing::create([
            'user_id' => $s4->id, 'title' => 'Espèces', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => false,
        ]);

        $ids = RelayListingResource::getEloquentQuery()->pluck('listings.id')->all();

        $this->assertContains($l1->id, $ids);
        $this->assertContains($l2->id, $ids);
        $this->assertNotContains($l3->id, $ids);
        $this->assertNotContains($l4->id, $ids);
    }

    public function test_relais_retenus_affiches_par_annonce(): void
    {
        $r1 = $this->relay('R1');
        $r2 = $this->relay('R2');

        // Surcharge annonce (R2) prioritaire sur le défaut vendeur (R1).
        $seller = $this->seller();
        $seller->acceptedRelayPoints()->sync([$r1->id]);
        $listing = $this->cbListing($seller);
        $listing->relayPoints()->sync([$r2->id]);

        $names = $listing->selectedRelayPoints()->pluck('name')->all();
        $this->assertSame(['R2'], $names);
    }
}
