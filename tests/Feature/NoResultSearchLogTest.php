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
}
