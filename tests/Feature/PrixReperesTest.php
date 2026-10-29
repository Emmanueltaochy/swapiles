<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\PrixReperes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Prix repères au dépôt : à combien se vendent les articles de la même catégorie sur Swap'Îles. */
class PrixReperesTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendeur = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    private function robes(array $prix, string $ile = 'La Réunion', array $plus = []): void
    {
        foreach ($prix as $p) {
            Listing::create($plus + [
                'user_id' => $this->vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
                'price' => $p, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => $ile,
                'category_level1' => 'femme', 'category_level2' => 'vetements', 'category_level3' => 'robes',
            ]);
        }
    }

    public function test_fourchette_du_milieu_sur_l_ile(): void
    {
        $this->robes([4, 6, 8, 10, 12, 15, 40]);
        // Autres îles et dons ignorés pour La Réunion.
        $this->robes([100, 100, 100, 100, 100], 'Martinique');
        $this->robes([0, 0], 'La Réunion', ['listing_type' => 'don']);

        $r = PrixReperes::pour('femme', 'vetements', 'robes', 'La Réunion');

        $this->assertSame(['bas' => 7, 'median' => 10, 'haut' => 14, 'nombre' => 7, 'libelle' => 'Robes', 'ile' => 'La Réunion'], $r);
    }

    public function test_pas_assez_sur_l_ile_toutes_les_iles(): void
    {
        $this->robes([10, 12]);
        $this->robes([8, 9, 30], 'Guadeloupe');

        $r = PrixReperes::pour('femme', 'vetements', 'robes', 'La Réunion');

        $this->assertNull($r['ile']);
        $this->assertSame(5, $r['nombre']);
        $this->assertSame(10, $r['median']);
    }

    public function test_trop_peu_d_annonces_rien(): void
    {
        $this->robes([10, 12, 14]);

        $this->assertNull(PrixReperes::pour('femme', 'vetements', 'robes', 'La Réunion'));
        $this->assertNull(PrixReperes::pour(null, null, null));
    }

    public function test_route_et_page_de_depot(): void
    {
        $this->robes([5, 8, 10, 12, 20]);

        $this->actingAs($this->vendeur)
            ->getJson(route('account.listings.prix-reperes', ['c1' => 'femme', 'c2' => 'vetements', 'c3' => 'robes', 'territoire' => 'La Réunion']))
            ->assertOk()->assertJsonPath('reperes.median', 10)->assertJsonPath('reperes.libelle', 'Robes');

        $this->actingAs($this->vendeur)->get(route('account.listings.create'))
            ->assertOk()->assertSee('id="price-reperes"', false);
    }

    public function test_reponse_de_l_ia_contient_les_reperes(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $this->robes([5, 8, 10, 12, 20]);
        $reponse = (object) [
            'stopReason' => 'end_turn',
            'usage' => (object) ['inputTokens' => 6000, 'outputTokens' => 800, 'cacheCreationInputTokens' => 0, 'cacheReadInputTokens' => 0],
            'content' => [(object) ['type' => 'text', 'text' => json_encode([
                'titre' => 'Robe fleurie', 'description' => 'D', 'categorie' => 'femme/vetements/robes', 'etat' => '', 'marque' => '', 'taille' => '', 'prix' => 25, 'poids_g' => 300,
            ])]],
        ];
        $this->app->instance('ia_depot.client', (object) ['messages' => new class($reponse) {
            public function __construct(private object $reponse) {}

            public function create(...$args): object
            {
                return $this->reponse;
            }
        }]);

        $this->actingAs($this->vendeur)
            ->postJson(route('account.listings.ia'), ['images' => [UploadedFile::fake()->image('robe.jpg', 800, 600)], 'territoire' => 'La Réunion'])
            ->assertOk()
            ->assertJsonPath('champs.prix', 25)
            ->assertJsonPath('reperes.bas', 8)
            ->assertJsonPath('reperes.haut', 12);
    }
}
