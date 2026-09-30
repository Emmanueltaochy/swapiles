<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation par catégorie sur la recherche.
 *
 * Les trois niveaux existaient, mais les deux derniers étaient enfouis dans
 * « Plus de filtres » : personne ne les trouvait, alors que c'est le chemin
 * principal pour parcourir le catalogue.
 */
class SearchCategoryNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function annonce(string $n1, ?string $n2 = null, ?string $n3 = null): Listing
    {
        $vendeur = User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => 'Article ' . uniqid(),
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => $n1,
            'category_level2' => $n2,
            'category_level3' => $n3,
        ]);
    }

    public function test_les_sous_categories_apparaissent_des_qu_une_categorie_est_choisie(): void
    {
        $this->annonce('femme', 'robes', 'robe-longue');
        $this->annonce('femme', 'chaussures', 'baskets');

        $html = $this->get(route('search', ['category' => 'femme']))->assertOk()->getContent();

        // Les deux sous-catégories doivent être proposées, en clair.
        $this->assertStringContainsString('Robes', $html);
        $this->assertStringContainsString('Chaussures', $html);

        // Et le fil d'Ariane doit permettre de remonter.
        $this->assertStringContainsString('Toutes catégories', $html);
    }

    public function test_le_troisieme_niveau_apparait_apres_la_sous_categorie(): void
    {
        $this->annonce('femme', 'robes', 'robe-longue');
        $this->annonce('femme', 'robes', 'robe-courte');

        $html = $this->get(route('search', [
            'category' => 'femme',
            'category_level2' => 'robes',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Robe longue', $html);
        $this->assertStringContainsString('Robe courte', $html);
    }

    public function test_le_fil_d_ariane_conserve_les_autres_filtres(): void
    {
        $this->annonce('femme', 'robes', 'robe-longue');

        $html = $this->get(route('search', [
            'category' => 'femme',
            'category_level2' => 'robes',
            'etat' => 'Très bon état',
        ]))->assertOk()->getContent();

        // Remonter d'un niveau ne doit pas perdre le filtre « état ».
        preg_match_all('/href="([^"]*category=femme[^"]*)"/', $html, $m);
        $this->assertNotEmpty($m[1], 'Aucun lien de catégorie trouvé.');

        $avecEtat = array_filter($m[1], fn ($href) => str_contains($href, 'etat='));
        $this->assertNotEmpty(
            $avecEtat,
            'Les liens de catégorie doivent conserver les autres filtres.'
        );
    }

    public function test_aucune_navigation_parasite_sans_categorie_choisie(): void
    {
        $this->annonce('femme', 'robes', 'robe-longue');

        $html = $this->get(route('search'))->assertOk()->getContent();

        // Tant qu'aucune catégorie n'est choisie, pas de fil d'Ariane.
        $this->assertStringNotContainsString('Toutes catégories</a>', $html);
    }

    public function test_le_filtrage_par_sous_categorie_fonctionne(): void
    {
        $robe = $this->annonce('femme', 'robes', 'robe-longue');
        $basket = $this->annonce('femme', 'chaussures', 'baskets');

        $html = $this->get(route('search', [
            'category' => 'femme',
            'category_level2' => 'robes',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($robe->title, $html);
        $this->assertStringNotContainsString($basket->title, $html);
    }
}
