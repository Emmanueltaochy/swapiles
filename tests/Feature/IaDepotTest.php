<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\IaDepot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** IA dépôt : masquée sans clé, remplit les champs, garde-fous (limite par jour, budget). */
class IaDepotTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        return User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    /** Faux client Anthropic : renvoie la fiche donnée, sans appel réseau. */
    private function fauxClient(array $fiche, ?string $stopReason = 'end_turn'): void
    {
        $reponse = (object) [
            'stopReason' => $stopReason,
            'usage' => (object) ['inputTokens' => 6000, 'outputTokens' => 800, 'cacheCreationInputTokens' => 0, 'cacheReadInputTokens' => 0],
            'content' => [(object) ['type' => 'text', 'text' => json_encode($fiche)]],
        ];
        $this->app->instance('ia_depot.client', (object) ['messages' => new class($reponse) {
            public array $appels = [];

            public function __construct(private object $reponse) {}

            public function create(...$args): object
            {
                $this->appels[] = $args;

                return $this->reponse;
            }
        }]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('robe.jpg', 1600, 1200);
    }

    public function test_masquee_sans_cle(): void
    {
        config()->set('ia_depot.cle', null);
        $membre = $this->membre();

        $this->assertFalse(IaDepot::disponible());
        $this->actingAs($membre)->get(route('account.listings.create'))->assertOk()->assertDontSee('Remplir la fiche avec l’IA', false);
        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()]])->assertNotFound();
    }

    public function test_remplit_les_champs_et_note_le_cout(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $membre = $this->membre();
        $this->fauxClient([
            'titre' => 'Robe fleurie Zara taille M', 'description' => 'Robe légère à fleurs, portée deux fois.',
            'categorie' => 'femme/vetements/robes', 'etat' => 'Très bon état', 'marque' => 'Zara', 'taille' => 'M',
            'prix' => 12, 'poids_g' => 300,
        ]);

        $this->actingAs($membre)->get(route('account.listings.create'))->assertOk()->assertSee('Remplir la fiche avec l’IA', false);

        $champs = $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo(), $this->photo()]])
            ->assertOk()->json('champs');
        $this->assertSame('femme', $champs['categorie_1']);
        $this->assertSame('vetements', $champs['categorie_2']);
        $this->assertSame('robes', $champs['categorie_3']);
        $this->assertSame('Très bon état', $champs['etat']);
        $this->assertSame(12, $champs['prix']);

        $usage = DB::table('ia_depot_usages')->first();
        $this->assertSame(2, (int) $usage->photos);
        $this->assertSame('ok', $usage->statut);
        // 6 000 jetons lus à 0,10 $ + 800 écrits à 0,50 $ par million = 0,001 $.
        $this->assertEqualsWithDelta(0.001, (float) $usage->cout_usd, 0.000001);
        $this->assertSame(1, IaDepot::bilan()['appels']);
    }

    public function test_valeurs_inventees_ecartees(): void
    {
        $c = IaDepot::nettoyer(['titre' => 'Objet', 'categorie' => 'femme/inexistant/x', 'etat' => 'Comme neuf', 'prix' => -5, 'poids_g' => 99999]);
        $this->assertSame('femme', $c['categorie_1']);
        $this->assertNull($c['categorie_2']);
        $this->assertNull($c['etat']);
        $this->assertNull($c['prix']);
        $this->assertNull($c['poids_g']);
    }

    public function test_limite_par_jour_et_budget_mensuel(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        config()->set('ia_depot.limite_par_jour', 1);
        $membre = $this->membre();
        $this->fauxClient(['titre' => 'T', 'description' => 'D', 'categorie' => '', 'etat' => '', 'marque' => '', 'taille' => '', 'prix' => 5, 'poids_g' => 100]);

        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()]])->assertOk();
        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()]])->assertStatus(429);

        // Budget du mois atteint : le bouton disparaît pour tout le monde.
        config()->set('ia_depot.budget_mensuel_usd', 0.0005);
        $this->assertFalse(IaDepot::disponible());
    }

    public function test_refus_de_l_ia(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $membre = $this->membre();
        $this->fauxClient([], 'refusal');

        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()]])
            ->assertStatus(422)->assertJsonFragment(['message' => 'L’IA n’a pas pu analyser ces photos. Remplis la fiche à la main.']);
        $this->assertSame('refus', DB::table('ia_depot_usages')->value('statut'));
    }

    public function test_page_admin(): void
    {
        $admin = $this->membre();
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();

        $this->actingAs($admin)->get('/admin/ia-depot')->assertOk()->assertSee('Masqué');
    }

    public function test_journal_admin_membre_et_annonce(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $membre = $this->membre();
        $this->fauxClient(['titre' => 'Robe fleurie', 'description' => 'D', 'categorie' => '', 'etat' => '', 'marque' => '', 'taille' => '', 'prix' => 12, 'poids_g' => 300]);

        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()], 'jeton' => 'jeton-abc'])->assertOk();
        $this->assertSame('Robe fleurie · 12 €', DB::table('ia_depot_usages')->value('resultat'));

        // Pas encore publiée : le journal le dit ; une fois publiée, l'annonce est reliée par le jeton du formulaire.
        $this->assertNull(IaDepot::journal()[0]['annonce_id']);
        $annonce = Listing::create(['user_id' => $membre->id, 'title' => 'Ma robe à fleurs', 'description' => 'Description de test assez longue.', 'price' => 12, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'submission_token' => 'jeton-abc']);
        $ligne = IaDepot::journal()[0];
        $this->assertSame($annonce->id, (int) $ligne['annonce_id']);
        $this->assertSame('Lea', $ligne['membre']);

        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get('/admin/ia-depot')->assertOk()
            ->assertSee('Dernières utilisations')->assertSee('Lea')->assertSee('Ma robe à fleurs')->assertSee('Robe fleurie · 12 €');
    }

    public function test_journal_retrouve_l_annonce_publiee_apres_un_rechargement(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $membre = $this->membre();
        $this->fauxClient(['titre' => 'Sac en raphia', 'description' => 'D', 'categorie' => '', 'etat' => '', 'marque' => '', 'taille' => '', 'prix' => 15, 'poids_g' => 400]);
        $this->actingAs($membre)->postJson(route('account.listings.ia'), ['images' => [$this->photo()], 'jeton' => 'jeton-avant-rechargement'])->assertOk();

        // Page rechargée avant la publication : le jeton du formulaire a changé.
        $this->travel(20)->minutes();
        $annonce = Listing::create(['user_id' => $membre->id, 'title' => 'Sac raphia', 'description' => 'Description de test assez longue.', 'price' => 15, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'submission_token' => 'jeton-apres-rechargement']);

        $ligne = IaDepot::journal()[0];
        $this->assertSame($annonce->id, (int) $ligne['annonce_id']);
        $this->assertTrue($ligne['annonce_probable']);

        // Une annonce publiée le lendemain n'est pas attribuée à l'IA.
        DB::table('listings')->where('id', $annonce->id)->update(['created_at' => now()->addDay()]);
        $this->assertNull(IaDepot::journal()[0]['annonce_id']);
    }
}
