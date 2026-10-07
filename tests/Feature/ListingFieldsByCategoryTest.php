<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Categories;
use App\Support\Etat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Des champs logiques selon le rayon.
 *
 * Le formulaire de dépôt était pensé pour les vêtements : « Taille », « Neuf
 * avec étiquette », « Location vêtement »… Pour un frigo ou un livre, rien de
 * cela n'avait de sens. La colonne « taille » porte maintenant la
 * caractéristique utile du rayon, et disparaît quand rien ne s'y prête.
 */
class ListingFieldsByCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(): User
    {
        $vendeur = User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $vendeur->forceFill(['email_verified_at' => now()])->save();

        return $vendeur;
    }

    private function annonce(User $vendeur, array $attributs): Listing
    {
        return Listing::create(array_merge([
            'user_id' => $vendeur->id,
            'title' => 'Article',
            'description' => 'Description de test suffisamment longue.',
            'price' => 50,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
        ], $attributs));
    }

    public function test_chaque_rayon_a_une_fiche_complete(): void
    {
        foreach (Categories::fichesPourJavascript() as $cle => $fiche) {
            $this->assertContains($fiche['etat'], ['textile', 'objet'], "Fiche {$cle}");
            $this->assertIsBool($fiche['location'], "Fiche {$cle}");
            $this->assertNotSame('', $fiche['titre'], "Fiche {$cle}");

            foreach (['taille', 'marque'] as $champ) {
                if ($fiche[$champ] !== null) {
                    $this->assertNotSame('', $fiche[$champ]['label'], "Fiche {$cle} / {$champ}");
                    $this->assertNotSame('', $fiche[$champ]['exemple'], "Fiche {$cle} / {$champ}");
                }
            }
        }
    }

    public function test_les_vetements_gardent_le_formulaire_d_origine(): void
    {
        $fiche = Categories::fiche('femme', 'vetements');

        $this->assertSame('Taille', $fiche['taille']['label']);
        $this->assertSame('textile', $fiche['etat']);
        $this->assertTrue($fiche['location']);
    }

    public function test_des_chaussures_demandent_une_pointure(): void
    {
        $this->assertSame('Pointure', Categories::fiche('homme', 'chaussures-homme')['taille']['label']);
        $this->assertSame('Pointure', Categories::fiche('enfant', 'chaussures-enfants')['taille']['label']);
    }

    public function test_un_frigo_demande_des_dimensions_et_pas_de_location(): void
    {
        $fiche = Categories::fiche('maison', 'electromenager');

        $this->assertSame('Dimensions / capacité', $fiche['taille']['label']);
        $this->assertSame('objet', $fiche['etat']);
        $this->assertFalse($fiche['location']);
    }

    public function test_un_livre_demande_un_auteur_et_pas_de_taille(): void
    {
        $fiche = Categories::fiche('culture-loisirs', 'livres');

        $this->assertNull($fiche['taille']);
        $this->assertSame('Auteur', $fiche['marque']['label']);
    }

    public function test_l_etat_d_un_objet_ne_parle_pas_d_etiquette(): void
    {
        $this->assertSame('Neuf, sous emballage', Etat::libelle('Neuf avec étiquette', 'high-tech', 'telephonie'));
        $this->assertSame('Neuf, déballé', Etat::libelle('Neuf sans étiquette', 'maison', 'electromenager'));

        // Un vêtement garde ses mots, et les autres états ne changent pas.
        $this->assertSame('Neuf avec étiquette', Etat::libelle('Neuf avec étiquette', 'femme', 'vetements'));
        $this->assertSame('Très bon état', Etat::libelle('Très bon état', 'high-tech', 'telephonie'));
    }

    public function test_le_filtre_neuf_trouve_toujours_les_objets(): void
    {
        // Seul le LIBELLÉ change : la valeur stockée reste celle que le
        // filtre « Neuf » de la recherche connaît.
        $vendeur = $this->vendeur();
        $this->annonce($vendeur, ['title' => 'Réfrigérateur neuf', 'etat' => 'Neuf avec étiquette', 'category_level1' => 'maison', 'category_level2' => 'electromenager']);

        $this->get(route('search', ['etat' => 'Neuf avec étiquette']))
            ->assertOk()
            ->assertSee('Réfrigérateur neuf');
    }

    public function test_le_formulaire_d_un_livre_s_adapte(): void
    {
        $vendeur = $this->vendeur();
        $livre = $this->annonce($vendeur, ['title' => 'Mangas', 'category_level1' => 'culture-loisirs', 'category_level2' => 'livres']);

        $html = $this->actingAs($vendeur)->get(route('account.listings.edit', $livre))->assertOk()->getContent();

        // « Auteur » au lieu de « Marque ».
        $this->assertMatchesRegularExpression('/<label for="marque" data-champ-label[^>]*>Auteur<\/label>/', $html);

        // Pas de taille pour un livre : masquée ET désactivée (non envoyée).
        $this->assertMatchesRegularExpression('/<div data-champ="taille"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/<input id="taille"[^>]*disabled/', $html);

        // Pas de location, et l'état parle d'emballage.
        $this->assertMatchesRegularExpression('/<option[^>]*data-option-location[^>]*hidden disabled/', $html);
        $this->assertStringContainsString('Neuf, sous emballage', $html);
    }

    public function test_le_formulaire_d_un_vetement_ne_change_pas(): void
    {
        $vendeur = $this->vendeur();
        $robe = $this->annonce($vendeur, ['title' => 'Robe', 'category_level1' => 'femme', 'category_level2' => 'vetements']);

        $html = $this->actingAs($vendeur)->get(route('account.listings.edit', $robe))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<label for="taille" data-champ-label[^>]*>Taille<\/label>/', $html);
        $this->assertStringContainsString('>Neuf avec étiquette</option>', $html);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*data-option-location[^>]*hidden/', $html);
    }

    public function test_la_page_d_un_frigo_parle_de_dimensions(): void
    {
        $vendeur = $this->vendeur();
        $frigo = $this->annonce($vendeur, [
            'title' => 'Réfrigérateur combiné',
            'taille' => '60 x 180 cm',
            'etat' => 'Neuf sans étiquette',
            'category_level1' => 'maison',
            'category_level2' => 'electromenager',
        ]);

        $this->get(route('listings.show', $frigo))
            ->assertOk()
            ->assertSee('Dimensions / capacité')
            // Plus de majuscules forcées : « 60 X 180 CM » était faux.
            ->assertSee('60 x 180 cm')
            ->assertDontSee('60 X 180 CM')
            ->assertSee('Neuf, déballé')
            ->assertDontSee('Neuf sans étiquette');
    }

    public function test_une_taille_de_vetement_reste_en_majuscules(): void
    {
        $vendeur = $this->vendeur();
        $robe = $this->annonce($vendeur, ['taille' => 'm', 'category_level1' => 'femme', 'category_level2' => 'vetements']);

        $this->assertSame('M', $robe->tailleAffichee());
    }

    public function test_le_resume_des_cartes_n_a_plus_de_point_orphelin(): void
    {
        $vendeur = $this->vendeur();
        $sansTaille = $this->annonce($vendeur, ['etat' => 'Bon état', 'marque' => 'Seb', 'category_level1' => 'maison', 'category_level2' => 'cuisine-arts-de-la-table']);

        // Avant : « · Bon état · Seb ».
        $this->assertSame('Bon état · Seb', $sansTaille->resumeCaracteristiques());
    }
}
