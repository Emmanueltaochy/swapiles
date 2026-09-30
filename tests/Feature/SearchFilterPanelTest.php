<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panneau de filtres de la recherche.
 *
 * Les filtres occupaient sept rangées et la moitié de l'écran avant le premier
 * produit. Ils tiennent désormais derrière un bouton sur téléphone, tout en
 * restant affichés sur grand écran. Deux choses ne doivent jamais régresser :
 * la navigation par catégorie reste visible (c'est le chemin principal), et le
 * compteur du bouton dit combien de filtres sont actifs.
 */
class SearchFilterPanelTest extends TestCase
{
    use RefreshDatabase;

    private function annonce(string $n1 = 'mode', ?string $n2 = null): Listing
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
        ]);
    }

    public function test_le_panneau_de_filtres_et_son_bouton_existent(): void
    {
        $this->annonce();

        $reponse = $this->get(route('search'));

        $reponse->assertOk()
            ->assertSee('id="panneau-filtres"', false)
            ->assertSee('data-filtres-ouvrir', false)
            ->assertSee('data-filtres-fermer', false)
            ->assertSee('Voir les résultats', false);
    }

    public function test_le_panneau_est_replie_sur_telephone_et_deplie_sur_grand_ecran(): void
    {
        $this->annonce();

        $reponse = $this->get(route('search'));

        // « hidden lg:block » : caché sur téléphone, colonne ordinaire au-delà.
        $reponse->assertOk()->assertSee('hidden lg:block fixed inset-0', false);
    }

    public function test_sans_filtre_actif_le_bouton_n_affiche_aucun_compteur(): void
    {
        $this->annonce();

        $html = $this->get(route('search', ['q' => 'article']))->assertOk()->getContent();

        // Le bouton reste neutre : ni fond plein, ni lien « Effacer ».
        $this->assertStringNotContainsString('data-filtres-effacer', $html);
    }

    public function test_les_filtres_actifs_sont_comptes_sur_le_bouton(): void
    {
        $this->annonce();

        $html = $this->get(route('search', [
            'listing_type' => 'achat',
            'etat' => 'Bon état',
            'min_price' => 5,
        ]))->assertOk()->getContent();

        // Trois filtres posés, hors recherche texte et hors catégories.
        $this->assertStringContainsString('>3</span>', $html);
        $this->assertStringContainsString('data-filtres-effacer', $html);
    }

    public function test_les_categories_ne_sont_pas_enfermees_dans_le_panneau(): void
    {
        $this->annonce('mode', 'chaussures');

        $html = $this->get(route('search', ['category' => 'mode']))->assertOk()->getContent();

        $positionNavigation = strpos($html, 'Fil d\'Ariane des catégories');
        $positionPanneau = strpos($html, 'id="panneau-filtres"');

        $this->assertNotFalse($positionNavigation, 'Le fil d\'Ariane des catégories a disparu.');
        $this->assertNotFalse($positionPanneau);
        $this->assertLessThan(
            $positionPanneau,
            $positionNavigation,
            'La navigation par catégorie doit rester hors du panneau, toujours visible.'
        );
    }

    public function test_les_pastilles_rapides_sont_proposees(): void
    {
        $this->annonce();

        $html = $this->get(route('search'))->assertOk()->getContent();

        foreach (['Dons', 'Paiement sécurisé', 'Négociable', 'Échange', 'Neuf', 'Prix croissant'] as $pastille) {
            $this->assertStringContainsString($pastille, $html, "Pastille manquante : {$pastille}");
        }
    }

    public function test_une_pastille_applique_son_filtre(): void
    {
        $this->annonce();

        $html = $this->get(route('search'))->assertOk()->getContent();

        $this->assertStringContainsString(route('search', ['listing_type' => 'don']), $html);
        $this->assertStringContainsString(route('search', ['payment' => ['cb']]), $html);
    }

    public function test_une_pastille_active_propose_de_se_retirer(): void
    {
        $this->annonce();

        // Filtre « Dons » pose : la pastille doit renvoyer vers la recherche
        // sans ce filtre, pour qu'un second clic l'enleve.
        $html = $this->get(route('search', ['listing_type' => 'don']))->assertOk()->getContent();

        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString(route('search'), $html);
    }

    public function test_une_pastille_conserve_les_autres_filtres(): void
    {
        $this->annonce('mode');

        // On navigue dans une categorie : la pastille ne doit pas la perdre.
        $html = $this->get(route('search', ['category' => 'mode']))->assertOk()->getContent();

        // e() : dans un attribut HTML, « & » est ecrit « &amp; ».
        $this->assertStringContainsString(
            e(route('search', ['category' => 'mode', 'listing_type' => 'don'])),
            $html,
            'Une pastille doit s\'ajouter aux filtres en place, pas les remplacer.'
        );
    }

    public function test_les_pastilles_paiement_s_additionnent(): void
    {
        $this->annonce();

        // Un paiement deja choisi : la pastille d'un autre doit proposer les deux.
        $html = $this->get(route('search', ['payment' => ['cash']]))->assertOk()->getContent();

        $this->assertStringContainsString(
            e(route('search', ['payment' => ['cash', 'cb']])),
            $html,
            'Le choix « Paiement securise » doit s\'ajouter a « Especes », pas le remplacer.'
        );
    }

    public function test_la_barre_de_filtres_ne_colle_plus_sous_l_entete(): void
    {
        $this->annonce();

        $vue = file_get_contents(resource_path('views/search.blade.php'));

        // L'entete est collante en permanence : une seconde barre collante au
        // meme endroit glissait dessous et devenait invisible.
        $this->assertStringNotContainsString('border-gray-200 sticky top-0 z-30', $vue);
    }

    public function test_le_lien_effacer_conserve_la_recherche_texte(): void
    {
        $this->annonce();

        $html = $this->get(route('search', [
            'q' => 'robe',
            'listing_type' => 'don',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString(route('search', ['q' => 'robe']), $html);
    }
}
