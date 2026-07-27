<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bouton « Supprimer mon produit » sur la fiche, visible uniquement par le
 * propriétaire de l'annonce.
 */
class ListingDeleteFromShowTest extends TestCase
{
    use RefreshDatabase;

    private function listing(User $owner): Listing
    {
        return Listing::create([
            'user_id' => $owner->id, 'title' => 'Mon sac', 'price' => 10,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'description' => 'Un sac',
        ]);
    }

    private function user(string $mail): User
    {
        return User::create(['name' => 'U', 'email' => $mail, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    public function test_le_proprietaire_voit_le_bouton_supprimer(): void
    {
        $owner = $this->user('owner@ex.com');
        $listing = $this->listing($owner);

        $response = $this->actingAs($owner)->get(route('listings.show', $listing));

        $response->assertOk();
        $response->assertSee('Supprimer mon produit', false);
    }

    public function test_un_autre_utilisateur_ne_voit_pas_le_bouton(): void
    {
        $owner = $this->user('owner2@ex.com');
        $visitor = $this->user('visitor@ex.com');
        $listing = $this->listing($owner);

        $response = $this->actingAs($visitor)->get(route('listings.show', $listing));

        $response->assertOk();
        $response->assertDontSee('Supprimer mon produit', false);
    }

    public function test_le_proprietaire_peut_supprimer(): void
    {
        $owner = $this->user('owner3@ex.com');
        $listing = $this->listing($owner);

        $response = $this->actingAs($owner)->delete(route('account.listings.destroy', $listing));

        $response->assertRedirect(route('account.dashboard'));
        $this->assertNull(Listing::find($listing->id));
    }

    public function test_un_autre_utilisateur_ne_peut_pas_supprimer(): void
    {
        $owner = $this->user('owner4@ex.com');
        $visitor = $this->user('visitor2@ex.com');
        $listing = $this->listing($owner);

        $response = $this->actingAs($visitor)->delete(route('account.listings.destroy', $listing));

        $response->assertForbidden();
        $this->assertNotNull(Listing::find($listing->id));
    }
}
