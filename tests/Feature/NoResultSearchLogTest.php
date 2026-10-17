<?php

namespace Tests\Feature;

use App\Support\SearchLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 17 : journalisation des recherches sans résultat (logique isolée).
 */
class NoResultSearchLogTest extends TestCase
{
    use RefreshDatabase;

    private string $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';

    public function test_une_recherche_sans_resultat_est_journalisee(): void
    {
        $term = SearchLogger::record('  PS5  Pro ', 0, null, $this->ua);

        $this->assertSame('PS5  Pro', trim('PS5  Pro'));
        $this->assertNotNull($term);
        $this->assertDatabaseHas('search_no_results', ['term' => 'ps5 pro']);
    }

    public function test_une_recherche_avec_resultat_n_est_pas_journalisee(): void
    {
        $term = SearchLogger::record('sac', 5, null, $this->ua);

        $this->assertNull($term);
        $this->assertDatabaseCount('search_no_results', 0);
    }

    public function test_une_recherche_vide_n_est_pas_journalisee(): void
    {
        SearchLogger::record('   ', 0, null, $this->ua);

        $this->assertDatabaseCount('search_no_results', 0);
    }

    public function test_les_bots_ne_sont_pas_journalises(): void
    {
        SearchLogger::record('iphone', 0, null, ''); // UA vide = bot

        $this->assertDatabaseCount('search_no_results', 0);
    }

    public function test_toutes_les_recherches_sont_journalisees_et_introuvables_seulement_si_absent_partout(): void
    {
        $vendeuse = \App\Models\User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        \App\Models\Listing::create([
            'user_id' => $vendeuse->id, 'title' => 'Robe longue fleurie', 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'Martinique',
        ]);

        // Vide à cause d'un filtre (autre île) : journalisée, mais pas « introuvable ».
        $this->assertSame('robe', SearchLogger::record('robe', 0, null, $this->ua, 'v1', 'La Réunion'));
        // Avec résultats.
        SearchLogger::record('Robe', 3, null, $this->ua, 'v2');
        // Nom de catégorie : existe forcément.
        SearchLogger::record('femme', 0, null, $this->ua, 'v3');
        // Vraiment absent.
        SearchLogger::record('trampoline', 0, null, $this->ua, 'v4');

        $this->assertSame(4, \App\Models\SearchLog::count());
        $this->assertSame(2, \App\Models\SearchLog::where('term', 'robe')->count());
        $this->assertSame(['trampoline'], \App\Models\SearchNoResult::pluck('term')->all());
    }

    public function test_page_admin_a_deux_onglets(): void
    {
        $admin = \App\Models\User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        SearchLogger::record('robe', 4, null, $this->ua, 'v1');
        SearchLogger::record('trampoline', 0, null, $this->ua, 'v2');

        $this->actingAs($admin)->get('/admin/no-result-searches')->assertOk()
            ->assertSee('Toutes les recherches')->assertSee('Introuvables sur la plateforme')->assertSee('« robe »', false);
        $this->get('/admin/no-result-searches?onglet=introuvables')->assertOk()->assertSee('« trampoline »', false);
    }

    public function test_la_page_de_recherche_journalise_une_seule_fois(): void
    {
        $this->withHeader('User-Agent', $this->ua)->get(route('search', ['q' => 'trampoline']))->assertOk();
        $this->withHeader('User-Agent', $this->ua)->get(route('search', ['q' => 'trampoline', 'page' => 2]))->assertOk();

        $this->assertSame(1, \App\Models\SearchLog::count());
    }
}

