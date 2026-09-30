<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'arbre des catégories, source unique.
 *
 * Il vivait en dur dans le JavaScript du formulaire de dépôt. Le menu de
 * navigation et la recherche ne pouvaient donc pas le lire : les
 * sous-catégories n'étaient visibles qu'en train de publier une annonce.
 */
class CategoryNavigationSourceTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        return User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_l_arbre_porte_les_trois_categories_du_formulaire(): void
    {
        $this->assertSame(['femme', 'homme', 'enfant'], array_keys(Categories::ARBRE));

        foreach (Categories::niveau1() as $categorie) {
            $this->assertNotEmpty($categorie['label']);
            $this->assertNotEmpty($categorie['emoji']);
        }
    }

    public function test_chaque_categorie_a_des_sous_categories_et_des_types(): void
    {
        foreach (array_keys(Categories::ARBRE) as $niveau1) {
            $sous = Categories::sousCategories($niveau1);
            $this->assertNotEmpty($sous, "Aucune sous-catégorie pour {$niveau1}.");

            foreach (array_keys($sous) as $niveau2) {
                $this->assertNotEmpty(
                    Categories::typesArticle($niveau1, $niveau2),
                    "Aucun type d'article pour {$niveau1} / {$niveau2}."
                );
            }
        }
    }

    public function test_les_libelles_sont_lisibles_a_tous_les_niveaux(): void
    {
        $this->assertSame('Femme', Categories::label('femme'));
        $this->assertSame('Vêtements', Categories::label('vetements'));
        $this->assertSame('Jeans, pantalons, shorts', Categories::label('jeans-pantalons-shorts'));

        // Les annonces anciennes portent parfois une majuscule.
        $this->assertSame('Femme', Categories::label('Femme'));

        // Une clé inconnue ne doit pas faire planter l'affichage.
        $this->assertNull(Categories::label('categorie-inventee'));
    }

    public function test_une_categorie_inconnue_ne_casse_rien(): void
    {
        $this->assertSame([], Categories::sousCategories('inexistante'));
        $this->assertSame([], Categories::sousCategories(null));
        $this->assertSame([], Categories::typesArticle('femme', 'inexistante'));
    }

    public function test_le_formulaire_de_depot_lit_le_meme_arbre(): void
    {
        $html = $this->actingAs($this->membre())
            ->get(route('account.listings.create'))->assertOk()->getContent();

        // L'arbre est injecté tel quel : le vendeur choisit exactement ce que
        // l'acheteur peut parcourir.
        $this->assertStringContainsString('jeans-pantalons-shorts', $html);
        $this->assertStringContainsString('puericulture', $html);
    }

    public function test_le_menu_deplie_les_sous_categories(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'id="menu-mobile"'));
        $menu = substr($menu, 0, strpos($menu, '<main>'));

        foreach (['Femme', 'Homme', 'Enfant'] as $categorie) {
            $this->assertStringContainsString($categorie, $menu, "Catégorie manquante : {$categorie}");
        }

        // Les sous-catégories, invisibles jusqu'ici hors du formulaire de dépôt.
        $this->assertStringContainsString('Puériculture', $menu);
        $this->assertStringContainsString('Jeux / jouets', $menu);

        // Chaque sous-catégorie mène à la recherche filtrée sur les deux niveaux.
        $this->assertStringContainsString(
            e(route('search', ['category' => 'femme', 'category_level2' => 'chaussures'])),
            $menu
        );
    }

    public function test_la_recherche_ouvre_sur_les_categories(): void
    {
        $html = $this->get(route('search'))->assertOk()->getContent();

        // La rangée de catégories est toujours là, même sans catégorie choisie :
        // c'est le chemin principal, il passe avant les filtres.
        foreach (['Femme', 'Homme', 'Enfant'] as $categorie) {
            $this->assertStringContainsString($categorie, $html);
        }

        $positionCategories = strpos($html, e(route('search', ['category' => 'femme'])));
        $positionFiltres = strpos($html, 'data-filtres-ouvrir');

        $this->assertNotFalse($positionCategories, 'La rangée de catégories a disparu.');
        $this->assertLessThan(
            $positionFiltres,
            $positionCategories,
            'Les catégories doivent précéder les filtres : on cherche « une robe », pas « un don négociable ».'
        );
    }

    public function test_une_categorie_sans_annonce_reste_navigable(): void
    {
        // Rien en « homme » : la pastille doit quand même être proposée.
        Listing::create([
            'user_id' => $this->membre()->id,
            'title' => 'Robe',
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'femme',
            'category_level2' => 'vetements',
            'category_level3' => 'robes',
        ]);

        $html = $this->get(route('search'))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('search', ['category' => 'homme'])), $html);
    }
}
