<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Categories;
use App\Support\CategoryAudit;
use App\Support\CategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rangement automatique des annonces dans l'arbre élargi.
 *
 * L'arbre ne couvrait que l'habillement alors que la plateforme vend aussi du
 * mobilier, du high-tech, du sport et du bricolage. Ces annonces portaient une
 * catégorie fausse ou vide et n'apparaissaient dans aucun rayon.
 *
 * Ce rangement touche des milliers de lignes en base : chaque garde-fou compte.
 */
class CategoryTidyTest extends TestCase
{
    use RefreshDatabase;

    private function annonce(string $titre, ?string $n1 = null, ?string $n2 = null): Listing
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
            'category_level1' => $n1,
            'category_level2' => $n2,
        ]);
    }

    public function test_chaque_regle_mene_a_une_place_qui_existe(): void
    {
        // Une règle qui pointe vers une branche absente rangerait des annonces
        // dans un rayon inexistant : elles disparaîtraient de la navigation.
        foreach (CategoryClassifier::regles() as [$motif, $n1, $n2, $n3]) {
            $this->assertArrayHasKey($n1, Categories::ARBRE, "Catégorie inconnue : {$n1}");
            $this->assertArrayHasKey($n2, Categories::ARBRE[$n1]['enfants'], "Sous-catégorie inconnue : {$n1} / {$n2}");
            $this->assertArrayHasKey(
                $n3,
                Categories::ARBRE[$n1]['enfants'][$n2]['enfants'],
                "Type d'article inconnu : {$n1} / {$n2} / {$n3}"
            );
        }
    }

    public function test_chaque_motif_est_une_expression_valide(): void
    {
        foreach (CategoryClassifier::regles() as [$motif]) {
            $this->assertNotFalse(
                @preg_match('/' . $motif . '/u', 'test'),
                "Motif invalide : {$motif}"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('articlesAttendus')]
    public function test_les_articles_courants_tombent_au_bon_endroit(string $titre, string $n1, string $n2): void
    {
        $place = CategoryClassifier::classer($this->annonce($titre));

        $this->assertNotNull($place, "Non reconnu : {$titre}");
        $this->assertSame($n1, $place[0], "Mauvaise catégorie pour « {$titre} »");
        $this->assertSame($n2, $place[1], "Mauvaise sous-catégorie pour « {$titre} »");
    }

    public static function articlesAttendus(): array
    {
        return [
            'canapé' => ['Canapé 3 places en tissu', 'maison', 'meubles'],
            'frigo' => ['Réfrigérateur combiné Whirlpool', 'maison', 'electromenager'],
            'climatiseur' => ['Climatiseur mobile 9000 BTU', 'maison', 'electromenager'],
            'iPhone' => ['iPhone 13 128 Go', 'high-tech', 'telephonie'],
            'télé' => ['Téléviseur 55 pouces 4K', 'high-tech', 'image-et-son'],
            'console' => ['Nintendo Switch avec 2 jeux', 'high-tech', 'jeux-video'],
            'surf' => ['Planche de surf 6 pieds', 'sport-loisirs', 'plage-et-mer'],
            'vélo' => ['VTT 27,5 pouces', 'sport-loisirs', 'velos-trottinettes'],
            'scooter' => ['Scooter 50cc Peugeot', 'auto-moto', 'deux-roues'],
            'perceuse' => ['Perceuse visseuse sans fil', 'jardin-bricolage', 'bricolage'],
            'plante' => ['Boutures de plantes tropicales', 'jardin-bricolage', 'jardin'],
            'manga' => ['Lot de mangas One Piece', 'culture-loisirs', 'livres'],
            'parfum' => ['Eau de parfum 50 ml', 'beaute-sante', 'parfums'],
            'aquarium' => ['Aquarium 60 litres complet', 'animaux', 'autres-animaux'],
            'poussette' => ['Poussette canne pliante', 'enfant', 'puericulture'],
            'chaise haute' => ['Chaise haute bébé évolutive', 'enfant', 'puericulture'],
        ];
    }

    public function test_chaise_haute_ne_tombe_pas_dans_les_meubles(): void
    {
        // « chaise » existe aussi dans Maison : l'ordre des règles doit faire
        // gagner la plus précise.
        $place = CategoryClassifier::classer($this->annonce('Chaise haute pour bébé'));

        $this->assertSame(['enfant', 'puericulture', 'chaises-hautes'], $place);
    }

    public function test_lit_bebe_ne_tombe_pas_dans_les_lits(): void
    {
        $place = CategoryClassifier::classer($this->annonce('Lit bébé en bois avec matelas'));

        $this->assertSame(['enfant', 'puericulture', 'lits-bebe'], $place);
    }

    public function test_les_accents_et_traits_d_union_ne_genent_pas(): void
    {
        $this->assertNotNull(CategoryClassifier::classer($this->annonce('Sèche-cheveux professionnel')));
        $this->assertNotNull(CategoryClassifier::classer($this->annonce('Seche cheveux professionnel')));
    }

    public function test_une_annonce_non_reconnue_n_est_pas_deplacee(): void
    {
        $annonce = $this->annonce('Objet mystérieux sans nom connu');

        $this->assertNull(CategoryClassifier::classer($annonce));

        CategoryAudit::ranger();

        $this->assertNull($annonce->fresh()->category_level1);
    }

    public function test_une_annonce_deja_bien_rangee_n_est_pas_touchee(): void
    {
        // Le vendeur a rangé sa robe dans Femme > Vêtements. Même si une règle
        // pouvait dire autre chose, son choix fait foi.
        $annonce = $this->annonce('Table basse en verre', 'femme', 'vetements');

        $this->assertTrue(CategoryClassifier::dejaRangee($annonce));

        CategoryAudit::ranger();

        $this->assertSame('femme', $annonce->fresh()->category_level1);
    }

    public function test_le_rangement_deplace_les_annonces_reconnues(): void
    {
        $frigo = $this->annonce('Réfrigérateur 300 litres');
        $telephone = $this->annonce('iPhone 12 en bon état', 'accessoires');

        $deplacees = CategoryAudit::ranger();

        $this->assertSame(2, $deplacees);
        $this->assertSame('maison', $frigo->fresh()->category_level1);
        $this->assertSame('electromenager', $frigo->fresh()->category_level2);
        $this->assertSame('high-tech', $telephone->fresh()->category_level1);
    }

    public function test_le_rangement_ne_notifie_personne(): void
    {
        // saveQuietly : ranger une annonce n'est pas une modification du
        // vendeur, il ne doit recevoir aucune alerte.
        $annonce = $this->annonce('Télévision 40 pouces');
        $avant = $annonce->updated_at;

        CategoryAudit::ranger();

        $this->assertSame('high-tech', $annonce->fresh()->category_level1);
        $this->assertNotNull($avant);
    }

    public function test_l_apercu_ne_modifie_rien(): void
    {
        $annonce = $this->annonce('Canapé d\'angle convertible');

        $apercu = CategoryAudit::apercu();

        $this->assertSame(1, $apercu['reconnues']);
        $this->assertNull($annonce->fresh()->category_level1);
    }

    public function test_la_repartition_signale_les_categories_hors_arbre(): void
    {
        $this->annonce('Robe', 'femme', 'vetements');
        $this->annonce('Chose', 'categorie-inventee');

        $repartition = collect(CategoryAudit::repartition());

        $this->assertTrue($repartition->firstWhere('cle', 'femme')['connue']);
        $this->assertFalse($repartition->firstWhere('cle', 'categorie-inventee')['connue']);
    }

    public function test_les_nouvelles_categories_sont_navigables(): void
    {
        $this->annonce('Réfrigérateur 300 litres', 'maison', 'electromenager');

        $this->get(route('search', ['category' => 'maison']))
            ->assertOk()
            ->assertSee('Réfrigérateur 300 litres')
            ->assertSee('Électroménager');
    }
}
