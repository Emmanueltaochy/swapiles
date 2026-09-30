<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordre de la page d'accueil.
 *
 * Il fallait faire défiler quatre écrans avant de voir une seule annonce :
 * chiffres, bloc de réassurance, appel au dépôt, bannière, « pourquoi nous »,
 * « comment ça marche »… Les annonces doivent venir en premier, comme sur
 * toutes les marketplaces grand public.
 */
class HomepageOrderTest extends TestCase
{
    use RefreshDatabase;

    private function annonce(string $titre): Listing
    {
        $vendeur = User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => $titre,
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
        ]);
    }

    public function test_les_annonces_apparaissent_avant_les_blocs_d_explication(): void
    {
        $this->annonce('Robe fleurie');

        $html = $this->get('/')->assertOk()->getContent();

        $premieresAnnonces = strpos($html, 'Annonces à');
        $reassurance = strpos($html, 'Paiement sécurisé Swap');
        $pourquoi = strpos($html, 'Pourquoi choisir');

        $this->assertNotFalse($premieresAnnonces, 'Le fil d’annonces est introuvable.');
        $this->assertNotFalse($reassurance);
        $this->assertNotFalse($pourquoi);

        $this->assertLessThan($reassurance, $premieresAnnonces, 'Les annonces doivent précéder le bloc de réassurance.');
        $this->assertLessThan($pourquoi, $premieresAnnonces, 'Les annonces doivent précéder « Pourquoi choisir ».');
    }

    public function test_les_categories_arrivent_juste_apres_la_recherche(): void
    {
        $this->annonce('Robe fleurie');

        $html = $this->get('/')->assertOk()->getContent();

        $recherche = strpos($html, 'Que recherches-tu');
        // « Nouveautés » n'apparaît que dans la barre de catégories.
        $categories = strpos($html, 'Nouveautés');
        $reassurance = strpos($html, 'Paiement sécurisé Swap');

        $this->assertNotFalse($categories);
        $this->assertLessThan($categories, $recherche);
        $this->assertLessThan($reassurance, $categories, 'Les catégories doivent précéder les blocs d’explication.');
    }

    public function test_les_chiffres_ne_precedent_plus_les_annonces(): void
    {
        $this->annonce('Robe fleurie');

        $html = $this->get('/')->assertOk()->getContent();

        $chiffres = strpos($html, 'membres inscrits');
        $annonces = strpos($html, 'Annonces à');

        $this->assertNotFalse($chiffres);
        $this->assertLessThan($chiffres, $annonces, 'Les compteurs ne doivent plus repousser les annonces.');
    }

    public function test_l_accueil_reste_fonctionnel_sans_aucune_annonce(): void
    {
        $this->get('/')->assertOk()->assertSee('Que recherches-tu', false);
    }
}
