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

    public function test_un_frigo_range_dans_femme_est_reclasse(): void
    {
        // Le cas reel : le formulaire n'offrait que Femme / Homme / Enfant, donc
        // le vendeur d'un refrigerateur a pris le rayon qui existait. Sa
        // categorie est « valide » et pourtant fausse.
        $annonce = $this->annonce('Réfrigérateur 300 litres', 'femme', 'vetements');

        $this->assertTrue(CategoryClassifier::dejaRangee($annonce));

        $decision = CategoryClassifier::decider($annonce);
        $this->assertSame('reclasser', $decision['action']);

        CategoryAudit::ranger();

        $this->assertSame('maison', $annonce->fresh()->category_level1);
        $this->assertSame('electromenager', $annonce->fresh()->category_level2);
    }

    public function test_une_vraie_robe_rangee_dans_femme_ne_bouge_pas(): void
    {
        $annonce = $this->annonce('Robe longue fleurie', 'femme', 'vetements');

        $this->assertSame('laisser', CategoryClassifier::decider($annonce)['action']);

        CategoryAudit::ranger();

        $this->assertSame('femme', $annonce->fresh()->category_level1);
        $this->assertSame('vetements', $annonce->fresh()->category_level2);
    }

    public function test_la_description_ne_suffit_pas_a_deplacer_une_annonce_rangee(): void
    {
        // « Robe légère… je vends aussi mon frigo » : le titre nomme l'objet,
        // la description raconte autour. Sans cette regle, toutes les robes
        // dont la description cite un autre objet partiraient au mauvais rayon.
        $annonce = $this->annonce('Robe légère', 'femme', 'vetements');
        $annonce->description = 'Très jolie. Je vends aussi mon réfrigérateur.';
        $annonce->save();

        $this->assertSame('laisser', CategoryClassifier::decider($annonce)['action']);

        CategoryAudit::ranger();

        $this->assertSame('femme', $annonce->fresh()->category_level1);
    }

    public function test_la_description_sauve_une_annonce_rangee_nulle_part(): void
    {
        // Ici le pire qui puisse arriver est qu'elle reste introuvable : elle
        // l'est deja. On accepte donc un signal plus faible.
        $annonce = $this->annonce('Super affaire', 'categorie-inventee');
        $annonce->description = 'Un aquarium de 60 litres complet.';
        $annonce->save();

        CategoryAudit::ranger();

        $this->assertSame('animaux', $annonce->fresh()->category_level1);
    }

    public function test_le_rangement_est_annulable(): void
    {
        $annonce = $this->annonce('Réfrigérateur 300 litres', 'femme', 'vetements');

        CategoryAudit::ranger();
        $this->assertSame('maison', $annonce->fresh()->category_level1);
        $this->assertSame(1, CategoryAudit::nombreDeplacees());

        $remises = CategoryAudit::annuler();

        $this->assertSame(1, $remises);
        $this->assertSame('femme', $annonce->fresh()->category_level1);
        $this->assertSame('vetements', $annonce->fresh()->category_level2);
        $this->assertNull($annonce->fresh()->category_avant);
    }

    public function test_deux_rangements_de_suite_gardent_la_place_d_origine(): void
    {
        $annonce = $this->annonce('Réfrigérateur 300 litres', 'femme', 'vetements');

        CategoryAudit::ranger();
        CategoryAudit::ranger();
        CategoryAudit::annuler();

        $this->assertSame('femme', $annonce->fresh()->category_level1);
    }

    public function test_le_rangement_compte_les_deux_familles(): void
    {
        $this->annonce('Réfrigérateur 300 litres', 'femme', 'vetements');
        $this->annonce('iPhone 12 en bon état', 'categorie-inventee');

        $bilan = CategoryAudit::ranger();

        $this->assertSame(1, $bilan['reclassees']);
        $this->assertSame(1, $bilan['rangees']);
    }

    public function test_l_apercu_ne_modifie_rien(): void
    {
        $annonce = $this->annonce('Canapé d\'angle convertible', 'femme', 'vetements');

        $apercu = CategoryAudit::apercu();

        $this->assertSame(1, $apercu['aReclasser']->count());
        $this->assertSame('femme', $annonce->fresh()->category_level1);
    }

    /**
     * Les neuf propositions fausses relevées sur l'aperçu réel. Chacune doit
     * désormais laisser l'annonce en place.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('faussesPropositions')]
    public function test_les_faux_deplacements_releves_sont_corriges(string $titre, string $n1, string $n2): void
    {
        $annonce = $this->annonce($titre, $n1, $n2);

        $this->assertSame(
            'laisser',
            CategoryClassifier::decider($annonce)['action'],
            "« {$titre} » ne doit plus quitter {$n1} › {$n2}."
        );
    }

    public static function faussesPropositions(): array
    {
        return [
            // « velours » contient « velo » : sans limite de mot, un sac en
            // velours partait au rayon Vélos.
            'sac en velours' => ['Vend sac a dos en velours', 'femme', 'accessoires'],
            'montre connectée' => ['Protection montre connectée', 'femme', 'accessoires'],
            'montre connectée homme' => ['Protection montre connectée', 'homme', 'accessoires'],
            // Des chaussures d'enfant restent là où un parent les cherche.
            'chaussures de rando enfant' => ['Chaussures de randonnée', 'enfant', 'chaussures-enfants'],
            'piscine à balles' => ['Piscine à balles', 'enfant', 'jeux-enfant'],
            'vélo enfant' => ['Vend vélo enfant', 'enfant', 'jeux-enfant'],
            // « artisanat » et « voilage » ne doivent pas battre « sac » et « robe ».
            'sac artisanat' => ['Sac artisanat malgache', 'femme', 'accessoires'],
            'robe voilage' => ['Robe à pois femme voilage T36', 'femme', 'vetements'],
            'maillot de foot' => ['DIVERS MAILLOT DE FOOT', 'homme', 'vetements'],
        ];
    }

    public function test_les_limites_de_mots_sont_respectees(): void
    {
        // « velo » reconnaît « velos » mais pas « velours ».
        $this->assertNull(CategoryClassifier::classerDepuisTitre($this->annonce('Vend sac a dos en velours')));
        $this->assertNotNull(CategoryClassifier::classerDepuisTitre($this->annonce('Deux velos adultes')));
    }

    /**
     * Le garde-fou ne doit pas tout figer : les vrais objets mal rangés
     * continuent de bouger.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('vraisDeplacements')]
    public function test_les_objets_vraiment_mal_ranges_bougent_toujours(string $titre, string $attendu): void
    {
        $annonce = $this->annonce($titre, 'femme', 'vetements');
        $decision = CategoryClassifier::decider($annonce);

        $this->assertSame('reclasser', $decision['action'], "« {$titre} » doit être reclassé.");
        $this->assertSame($attendu, $decision['place'][0]);
    }

    public static function vraisDeplacements(): array
    {
        return [
            'frigo' => ['Réfrigérateur 300 litres', 'maison'],
            'iPhone' => ['iPhone 13 128 Go', 'high-tech'],
            'canapé' => ['Canapé 3 places', 'maison'],
            'perceuse' => ['Perceuse visseuse Bosch', 'jardin-bricolage'],
            'scooter' => ['Scooter 50cc', 'auto-moto'],
            'aquarium' => ['Aquarium 60 litres', 'animaux'],
            'surf' => ['Planche de surf 6 pieds', 'sport-loisirs'],
            'manga' => ['Lot de mangas One Piece', 'culture-loisirs'],
            'poussette' => ['Poussette canne pliante', 'enfant'],
        ];
    }

    public function test_un_enfant_peut_etre_range_a_l_interieur_d_enfant(): void
    {
        // Le garde-fou enfant empêche de SORTIR du rayon, pas d'y ranger mieux.
        $annonce = $this->annonce('Poussette canne pliante', 'enfant', 'jeux-enfant');
        $decision = CategoryClassifier::decider($annonce);

        $this->assertSame('reclasser', $decision['action']);
        $this->assertSame(['enfant', 'puericulture', 'poussettes'], $decision['place']);
    }

    public function test_un_frigo_pour_enfant_reste_quand_meme_dans_enfant(): void
    {
        // Choix assumé : mieux vaut un objet au mauvais rayon qu'un objet
        // disparu du rayon où le parent le cherche.
        $annonce = $this->annonce('Réfrigérateur chambre enfant', 'enfant', 'jeux-enfant');

        $this->assertSame('laisser', CategoryClassifier::decider($annonce)['action']);
    }

    /**
     * Les trois propositions fausses du second aperçu réel.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('piegesDuSecondApercu')]
    public function test_le_mot_precis_bat_le_mot_large(string $titre, string $n1, string $n2): void
    {
        $annonce = $this->annonce($titre, 'femme', 'accessoires');
        $decision = CategoryClassifier::decider($annonce);

        $this->assertNotNull($decision['place'], "« {$titre} » n'est plus reconnu.");
        $this->assertSame([$n1, $n2], array_slice($decision['place'], 0, 2), "Mauvais rayon pour « {$titre} ».");
    }

    public static function piegesDuSecondApercu(): array
    {
        return [
            // « tapis » est declare en Decoration : il avalait le tapis de course.
            'tapis de course' => ['Tapis de course', 'sport-loisirs', 'fitness-musculation'],
            'tapis de yoga' => ['Tapis de yoga épais', 'sport-loisirs', 'fitness-musculation'],
            // ... mais un vrai tapis de salon reste en Decoration.
            'tapis de salon' => ['Tapis de salon', 'maison', 'decoration'],
            // « canape » est declare en Meubles : il avalait le jete de canape.
            'jeté canapé' => ['Jeté Canapé', 'maison', 'linge-de-maison'],
            'canapé' => ['Canapé 3 places', 'maison', 'meubles'],
        ];
    }

    public function test_des_crampons_restent_des_chaussures(): void
    {
        // Meme logique qu'un maillot de foot : c'est de l'habillement.
        $annonce = $this->annonce('Crampon nike', 'homme', 'chaussures-homme');

        $this->assertSame('laisser', CategoryClassifier::decider($annonce)['action']);
    }

    public function test_les_mots_trop_larges_ont_ete_retires(): void
    {
        // « pince » attrapait une pince a cheveux, « bd » un boulevard.
        $this->assertNull(CategoryClassifier::classerDepuisTitre($this->annonce('Pince à cheveux dorée')));
        $this->assertNull(CategoryClassifier::classerDepuisTitre($this->annonce('Robe vendue bd Gaulle')));

        // Les vrais outils restent reconnus.
        $this->assertNotNull(CategoryClassifier::classerDepuisTitre($this->annonce('Pince coupante neuve')));
    }

    public function test_les_mots_non_reconnus_remontent(): void
    {
        $this->annonce('Paréo traditionnel', 'femme', 'accessoires');
        $this->annonce('Paréo en coton', 'femme', 'accessoires');

        $mots = collect(CategoryAudit::motsNonReconnus());

        $this->assertSame(2, $mots->firstWhere('mot', 'pareo')['total'] ?? null);
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
