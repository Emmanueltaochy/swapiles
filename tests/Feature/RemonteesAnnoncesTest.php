<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Support\ConseilsPrix;
use App\Support\Remontees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Remontées d'annonces : les anciennes annonces ne restent pas oubliées en bas
 * de la liste (baisse de prix, rotation), sans bouton de remontée manuelle ;
 * conseil de prix au vendeur après 14 jours de vues sans acheteur.
 */
class RemonteesAnnoncesTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendeur = $this->membre('lea@ex.com');
    }

    private function membre(string $email, bool $actif = true): User
    {
        $u = User::create(['name' => 'Lea', 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        if ($actif) {
            DB::table('presences')->insert([
                'cle' => md5($email), 'ip_hash' => md5('ip' . $email), 'user_id' => $u->id, 'territoire' => 'La Réunion',
                'debut_at' => now()->subDay(), 'last_seen_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $u;
    }

    private function annonce(array $plus = [], ?User $vendeur = null, int $joursAge = 0): Listing
    {
        $annonce = Listing::create($plus + [
            'user_id' => ($vendeur ?? $this->vendeur)->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 30, 'currency' => 'EUR', 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'category_level1' => 'femme', 'category_level2' => 'vetements', 'category_level3' => 'robes',
        ]);
        $annonce->images()->create(['url' => 'https://example.com/1.jpg', 'order' => 0]);
        if ($joursAge > 0) {
            $annonce->forceFill(['created_at' => now()->subDays($joursAge)])->saveQuietly();
        }

        return $annonce->fresh();
    }

    public function test_baisse_de_10_pour_cent_remonte_l_annonce(): void
    {
        $annonce = $this->annonce(['price' => 18], joursAge: 20);

        $this->assertTrue(Remontees::apresBaisseDePrix($annonce, 20), '20 € → 18 € = -10 % : remontée.');
        $annonce->refresh();
        $this->assertNotNull($annonce->remonte_at);
        $this->assertSame(1, DB::table('listing_remontees')->where('listing_id', $annonce->id)->where('motif', 'prix')->count());
    }

    public function test_petite_baisse_hausse_ou_remontee_recente_ne_remontent_pas(): void
    {
        $annonce = $this->annonce(['price' => 19], joursAge: 20);
        $this->assertFalse(Remontees::apresBaisseDePrix($annonce, 20), '-5 % ne suffit pas.');

        $annonce->forceFill(['price' => 25])->saveQuietly();
        $this->assertFalse(Remontees::apresBaisseDePrix($annonce, 20), 'Une hausse ne remonte pas.');

        $annonce->forceFill(['price' => 10, 'prix_remonte_at' => now()->subDays(5)])->saveQuietly();
        $this->assertFalse(Remontees::apresBaisseDePrix($annonce->fresh(), 20), 'Déjà remontée il y a 5 jours.');

        $annonce->forceFill(['prix_remonte_at' => now()->subDays(15)])->saveQuietly();
        $this->assertTrue(Remontees::apresBaisseDePrix($annonce->fresh(), 20), 'Après 2 semaines, de nouveau possible.');

        $masquee = $this->annonce(['price' => 10, 'status' => 'draft']);
        $this->assertFalse(Remontees::apresBaisseDePrix($masquee, 20), 'Une annonce masquée ne remonte pas.');
    }

    public function test_le_vendeur_qui_baisse_son_prix_voit_son_annonce_en_tete(): void
    {
        $ancienne = $this->annonce(['price' => 30, 'title' => 'Ancienne robe'], joursAge: 20);
        $this->annonce(['title' => 'Robe toute neuve'])->forceFill(['created_at' => now()->subHour()])->saveQuietly();

        $reponse = $this->actingAs($this->vendeur)->put(route('account.listings.update', $ancienne), [
            'title' => 'Ancienne robe', 'description' => 'Description de test assez longue.', 'price' => 25,
            'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'femme', 'category_level2' => 'vetements', 'category_level3' => 'robes',
            'payment_hand' => '1', 'pickup_city' => 'Saint-Paul', 'pickup_postal_code' => '97460',
        ]);
        $reponse->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'repasse en tête'));

        $titres = $this->get('/')->assertOk()->viewData('listings')->pluck('title')->all();
        $this->assertSame(['Ancienne robe', 'Robe toute neuve'], $titres, 'L’annonce au prix baissé passe devant.');
    }

    public function test_la_recherche_suit_aussi_le_classement(): void
    {
        $ancienne = $this->annonce(['title' => 'Ancienne robe'], joursAge: 20);
        $this->annonce(['title' => 'Robe toute neuve'])->forceFill(['created_at' => now()->subHour()])->saveQuietly();
        Remontees::remonter($ancienne, 'rotation');

        $titres = $this->get('/recherche?territoire=La+R%C3%A9union')->assertOk()->viewData('listings')->pluck('title')->all();
        $this->assertSame(['Ancienne robe', 'Robe toute neuve'], $titres);
    }

    public function test_rotation_une_ancienne_pour_quatre_nouvelles(): void
    {
        $anciennes = collect([30, 25, 20, 15, 10, 9, 8])->map(fn ($j) => $this->annonce(joursAge: $j));
        foreach (range(1, 8) as $i) {
            $this->annonce();
        }

        // 8 nouvelles dans l'heure → exactement 2 anciennes.
        $this->assertSame(2, Remontees::rotation(fn () => 0.99));
        $remontees = Listing::whereNotNull('remonte_at')->pluck('id');
        $this->assertCount(2, $remontees);
        $this->assertTrue($remontees->every(fn ($id) => $anciennes->pluck('id')->contains($id)), 'Seules des anciennes sont remontées.');

        // Les remontées ne repassent pas avant une semaine : les suivantes prennent leur tour.
        Remontees::rotation(fn () => 0.99);
        $this->assertCount(4, Listing::whereNotNull('remonte_at')->pluck('id')->unique());
    }

    public function test_rotation_rien_sans_nouvelles_et_ignore_vendeurs_inactifs(): void
    {
        $this->annonce(joursAge: 30);
        $this->assertSame(0, Remontees::rotation(fn () => 0.0), 'Pas de nouvelles annonces : rien ne bouge.');

        // Seule l'annonce d'un vendeur parti depuis longtemps est disponible.
        Listing::query()->delete();
        $absent = $this->membre('absent@ex.com', actif: false);
        $this->annonce(vendeur: $absent, joursAge: 30);
        foreach (range(1, 4) as $i) {
            $this->annonce();
        }
        $this->assertSame(0, Remontees::rotation(fn () => 0.0), 'Un vendeur absent ne répondrait pas : pas remis en avant.');
    }

    public function test_rotation_une_semaine_minimum(): void
    {
        $this->annonce(joursAge: 3);
        foreach (range(1, 4) as $i) {
            $this->annonce();
        }
        $this->assertSame(0, Remontees::rotation(fn () => 0.0), 'Une annonce de 3 jours n’est pas « ancienne ».');
    }

    public function test_conseil_de_prix_avec_prix_reperes(): void
    {
        // Prix repères : les robes se vendent autour de 11 € (6, 8, 10, 12, 14 et la sienne à 40).
        $autre = $this->membre('autre@ex.com');
        foreach ([6, 8, 10, 12, 14] as $p) {
            $this->annonce(['price' => $p], vendeur: $autre);
        }
        $chere = $this->annonce(['price' => 40, 'views_count' => 120], joursAge: 15);

        $this->assertSame(1, ConseilsPrix::envoyer());

        $n = Notification::where('user_id', $this->vendeur->id)->where('type', 'listing_price_advice')->sole();
        $this->assertStringContainsString('120 vues', $n->message);
        $this->assertStringContainsString('autour de 11 €', $n->message);
        $this->assertSame(route('account.listings.edit', $chere, absolute: false), $n->url);
        $this->assertNotNull($chere->fresh()->conseil_prix_at);

        // Une seule fois : rien de plus au passage suivant.
        $this->assertSame(0, ConseilsPrix::envoyer());
    }

    public function test_conseil_astuces_quand_le_prix_est_deja_bon(): void
    {
        $this->annonce(['price' => 10, 'views_count' => 50], joursAge: 15);
        $this->assertSame(1, ConseilsPrix::envoyer());

        $n = Notification::where('type', 'listing_price_advice')->sole();
        $this->assertStringContainsString('Astuces', $n->message);
        $this->assertStringContainsString('Colissimo', $n->message);
    }

    public function test_conseil_garde_fous(): void
    {
        $this->annonce(['views_count' => 10], joursAge: 20);            // pas assez de vues
        $this->annonce(['views_count' => 200], joursAge: 5);            // trop récente
        $this->annonce(['views_count' => 200, 'listing_type' => 'don', 'price' => 0], joursAge: 20); // don
        $this->assertSame(0, ConseilsPrix::envoyer());

        // Deux annonces éligibles du même vendeur : un seul conseil (la plus vue).
        $a = $this->annonce(['views_count' => 80], joursAge: 20);
        $b = $this->annonce(['views_count' => 90], joursAge: 20);
        $this->assertSame(1, ConseilsPrix::envoyer());
        $this->assertNotNull($b->fresh()->conseil_prix_at);
        $this->assertNull($a->fresh()->conseil_prix_at);

        // Et pas d'autre conseil pour ce vendeur la même semaine.
        $this->assertSame(0, ConseilsPrix::envoyer());
    }

    public function test_temps_en_premiere_page_et_page_admin(): void
    {
        foreach (range(1, Remontees::PAGE_ACCUEIL) as $i) {
            $a = $this->annonce();
            $a->forceFill(['created_at' => now()->subHours($i * 2)])->saveQuietly();
        }

        $pages = Remontees::tempsPremierePage();
        $this->assertEqualsWithDelta(48, $pages['La Réunion']['heures'], 0.2, 'La 24e annonce a 48 h.');
        $this->assertNull($pages['Martinique']['heures']);

        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/admin/visibilite-annonces')->assertOk()->assertSee('Temps en première page')->assertSee('2 jours');
    }
}
