<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\SearchNoResult;
use App\Models\User;
use App\Support\Categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « femme », « high-tech », « jardin-bricolage » dans les recherches sans
 * résultat de l'administration.
 *
 * Ce n'étaient pas des recherches tapées : le fil d'Ariane des annonces (lu
 * par Google) pointait vers une recherche de TEXTE « femme », qui ne trouve
 * rien, au lieu de la catégorie Femme. Des milliers de visites tombaient sur
 * « Aucune annonce trouvée » et noyaient les vraies demandes.
 */
class CategoryNameSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_nom_de_categorie_cherche_ouvre_la_categorie(): void
    {
        $this->get('/recherche?q=femme')->assertRedirect(route('search', ['category' => 'femme']))->assertStatus(301);
        $this->get('/recherche?q=High-tech')->assertRedirect(route('search', ['category' => 'high-tech']));
        $this->get('/recherche?q=jardin-bricolage')->assertRedirect(route('search', ['category' => 'jardin-bricolage']));

        // Les autres réglages sont gardés.
        $this->get('/recherche?q=Femme&territoire=Martinique')
            ->assertRedirect(route('search', ['territoire' => 'Martinique', 'category' => 'femme']));
    }

    public function test_une_vraie_recherche_reste_une_recherche(): void
    {
        $this->get('/recherche?q=robe+femme')->assertOk();
        $this->get('/recherche?q=veste+etanche')->assertOk();
        // Catégorie déjà choisie : on cherche le texte dedans.
        $this->get('/recherche?q=femme&category=enfant')->assertOk();
    }

    public function test_ces_visites_ne_sont_plus_comptees_comme_recherches_sans_resultat(): void
    {
        $this->get('/recherche?q=femme');

        $this->assertSame(0, SearchNoResult::count());
    }

    public function test_tous_les_noms_de_categories_sont_reconnus(): void
    {
        foreach (Categories::niveau1() as $cle => $categorie) {
            $this->assertSame($cle, Categories::niveau1DepuisTexte($cle), $cle);
            $this->assertSame($cle, Categories::niveau1DepuisTexte($categorie['label']), $categorie['label']);
            $this->assertSame($cle, Categories::niveau1DepuisTexte(mb_strtoupper($categorie['label'])), $categorie['label']);
        }

        $this->assertNull(Categories::niveau1DepuisTexte('lit enfant'));
        $this->assertNull(Categories::niveau1DepuisTexte(''));
    }

    public function test_le_fil_d_ariane_pour_google_pointe_vers_la_categorie(): void
    {
        $vendeur = User::create(['name' => 'V', 'email' => 'v' . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $annonce = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe fleurie', 'description' => 'Description de test suffisamment longue.',
            'price' => 20, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published',
            'territoire' => 'La Réunion', 'category_level1' => 'femme',
        ]);

        $html = $this->get(route('listings.show', $annonce))->assertOk()->getContent();

        preg_match('#"@type":"BreadcrumbList".*?\]#s', $html, $fil);
        $this->assertNotEmpty($fil);
        $this->assertStringContainsString('"name":"Femme"', $fil[0]);
        $this->assertStringContainsString('recherche?category=femme', $fil[0]);
        $this->assertStringNotContainsString('?q=', $fil[0]);
    }

    public function test_l_historique_est_nettoye_des_noms_de_categories(): void
    {
        foreach (['femme', 'high-tech', 'jardin-bricolage', 'veste etanche', 'lit enfant'] as $terme) {
            SearchNoResult::create(['term' => $terme, 'raw_term' => $terme, 'created_at' => now()]);
        }

        $migration = require database_path('migrations/2026_10_11_200000_remove_category_names_from_no_result_searches.php');
        $migration->up();

        $this->assertSame(['lit enfant', 'veste etanche'], SearchNoResult::orderBy('term')->pluck('term')->all());
    }
}
