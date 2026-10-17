<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\RechercheTexte;
use App\Support\SearchLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechercheMotParMotTest extends TestCase
{
    use RefreshDatabase;

    private function annonce(string $titre, string $categorie, string $description = 'Belle pièce en très bon état.'): Listing
    {
        $vendeuse = User::firstOrCreate(['email' => 'dame@ex.com'], ['name' => 'Dame', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        return Listing::create([
            'user_id' => $vendeuse->id, 'title' => $titre, 'description' => $description, 'price' => 8,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => $categorie,
        ]);
    }

    public function test_les_mots_utiles(): void
    {
        $this->assertSame(['jardin', 'plante'], RechercheTexte::mots('Jardin plantes'));
        $this->assertSame(['robe', 'soirée'], RechercheTexte::mots('robe de soirée'));
        $this->assertSame(['jeu', 'ps5'], RechercheTexte::mots('jeux PS5'));
    }

    public function test_jardin_plante_trouve_les_plantes_du_jardin(): void
    {
        $plante = $this->annonce('Plante verte en pot', 'jardin-bricolage');
        $this->annonce('Pelle de jardin', 'jardin-bricolage');
        $this->annonce('Robe fleurie', 'femme');

        $this->withCookie('territoire', 'reunion')->get(route('search', ['q' => 'jardin plante', 'territoire' => '']))
            ->assertOk()->assertSee('Plante verte en pot')->assertDontSee('Pelle de jardin')->assertDontSee('Robe fleurie');
        $this->get(route('search', ['q' => 'plantes', 'territoire' => '']))->assertOk()->assertSee('Plante verte en pot');

        $this->assertTrue(SearchLogger::existeSurLaPlateforme('jardin plante'));
        $this->assertFalse(SearchLogger::existeSurLaPlateforme('trampoline jardin'));
        $this->get(route('search.live', ['q' => 'jardin plante']))->assertOk()->assertSee('Plante verte en pot');
    }
}
