<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Défilement infini des listes d'articles.
 *
 * La page suivante est chargée toute seule à l'approche du bas
 * (public/js/infinite-scroll.js). Le serveur doit donc fournir, dans chaque
 * page, l'adresse de la suivante, et un ordre STABLE : des annonces de même
 * date (imports, publications groupées) pouvaient sinon apparaître sur deux
 * pages, ou sur aucune.
 */
class InfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendeur = User::create([
            'name' => 'Vendeuse',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    /** Crée N annonces publiées, TOUTES à la même date (pire cas). */
    private function annonces(int $nombre): void
    {
        for ($i = 1; $i <= $nombre; $i++) {
            $annonce = Listing::create([
                'user_id' => $this->vendeur->id,
                'title' => 'Article ' . $i,
                'description' => 'Description de test suffisamment longue.',
                'price' => 10 + $i,
                'currency' => 'EUR',
                'listing_type' => 'achat',
                'status' => 'published',
                'territoire' => 'La Réunion',
                'category_level1' => 'femme',
            ]);
            $annonce->images()->create(['url' => 'https://example.com/' . $i . '.jpg', 'order' => 0]);
        }

        DB::table('listings')->update(['created_at' => '2026-10-01 10:00:00']);
    }

    /** Identifiants des annonces affichées dans la grille à défilement infini. */
    private function idsDeLaGrille(string $html): array
    {
        preg_match('#<div[^>]*data-defilement-infini[^>]*>(.*?)<div class="mt-(?:8|10)" data-pagination>#s', $html, $grille);
        $this->assertNotEmpty($grille, 'Grille à défilement infini introuvable.');
        preg_match_all('#href="[^"]*/annonce/(\d+)"#', $grille[1], $ids);

        return array_values(array_unique($ids[1]));
    }

    private function pageSuivante(string $html): string
    {
        preg_match('#data-defilement-infini data-page-suivante="([^"]*)"#', $html, $m);
        $this->assertNotEmpty($m, 'Adresse de la page suivante absente.');

        return html_entity_decode($m[1]);
    }

    public static function pages(): array
    {
        return [
            'recherche' => ['/recherche?territoire=La+R%C3%A9union', 48],
            'recherche par prix' => ['/recherche?sort=price_asc', 48],
            'accueil' => ['/', 24],
            'île' => ['/iles/la-reunion', 24],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_les_pages_s_enchainent_sans_doublon_ni_oubli(string $url, int $parPage): void
    {
        $total = $parPage * 2 + 5;
        $this->annonces($total);

        $vus = [];
        $adresse = $url;
        $tours = 0;

        while ($adresse !== '' && $tours < 5) {
            $html = $this->get($adresse, ['X-Swp-Infini' => '1'])->assertOk()->getContent();
            $vus = array_merge($vus, $this->idsDeLaGrille($html));
            $adresse = $this->pageSuivante($html);
            $tours++;
        }

        $this->assertSame(3, $tours, 'Trois pages attendues, la dernière sans suite.');
        $this->assertCount($total, $vus, 'Chaque annonce apparaît une fois.');
        $this->assertCount($total, array_unique($vus), 'Aucune annonce en double d’une page à l’autre.');
    }

    public function test_le_dressing_d_un_membre_defile_aussi(): void
    {
        $this->annonces(30);

        $html = $this->get(route('profiles.show', $this->vendeur))->assertOk()->getContent();

        $this->assertStringContainsString('page=2', $this->pageSuivante($html));
        $this->assertStringContainsString('data-pagination', $html);
    }

    public function test_la_derniere_page_n_annonce_pas_de_suite(): void
    {
        $this->annonces(3);

        $html = $this->get('/recherche')->assertOk()->getContent();

        $this->assertSame('', $this->pageSuivante($html));
    }

    public function test_le_chargement_de_la_suite_n_est_pas_compte_comme_une_page_vue(): void
    {
        $this->annonces(60);

        $this->get('/recherche?page=2', ['X-Swp-Infini' => '1'])->assertOk();
        $this->assertSame(0, DB::table('analytics_events')->count());

        $this->get('/recherche')->assertOk();
        $this->assertSame(1, DB::table('analytics_events')->count());
    }

    public function test_le_script_est_charge_sur_toutes_les_pages(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script defer data-turbo-track="reload"\s+src="[^"]*/js/infinite-scroll\.js#', $html);
    }

    public function test_le_script_garde_les_garde_fous(): void
    {
        $js = file_get_contents(public_path('js/infinite-scroll.js'));

        // Bas de page atteignable : bouton après quelques pages automatiques.
        $this->assertMatchesRegularExpression('/var AUTO_MAX = \d+;/', $js);
        // Jamais deux fois la même carte.
        $this->assertStringContainsString('if (!cle || deja[cle]) return;', $js);
        // Réseau coupé : la main revient au visiteur, rien n'est perdu.
        $this->assertMatchesRegularExpression('/\.catch\(function \(\) \{.*?bouton\.hidden = false;/s', $js);
        // Coupure : pas de nouvel essai à chaque mouvement de doigt.
        $this->assertStringContainsString('if (actif && !enPause && pages < AUTO_MAX && procheDuBas()) charger();', $js);
        // Défilement rapide qui saute le bas de liste : la suite arrive quand même
        // (position vérifiée à chaque défilement, pas d'IntersectionObserver).
        $this->assertStringContainsString("window.addEventListener('scroll', surveiller, options);", $js);
        // Pagination classique gardée dans la page (moteurs de recherche), seulement masquée.
        $this->assertStringContainsString('pagination.hidden = true', $js);
        // Retour arrière : la page restaurée est re-préparée.
        $this->assertStringContainsString("document.addEventListener('turbo:load', initialiser);", $js);
    }
}
