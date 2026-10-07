<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationPreferences;
use App\Support\PushPolicy;
use App\Support\Recommandations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * « Recommandé pour vous » : d'après ce que le membre ouvre et met en
 * favori, envoyé de temps en temps (le soir, tous les 4 jours au plus).
 */
class RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Une date fixe, le matin à La Réunion : l'envoi du soir se teste en avançant l'heure.
        Carbon::setTestNow(Carbon::parse('2026-10-08 09:00:00', 'Indian/Reunion'));
        Cache::flush();
        Queue::fake();
        config(['push.fcm.project_id' => 'swap-iles']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom = 'Membre', string $ile = 'La Réunion'): User
    {
        $membre = User::create([
            'name' => $nom,
            'email' => strtolower($nom) . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => $ile,
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        return $membre;
    }

    private function annonce(User $vendeur, array $champs = []): Listing
    {
        $annonce = Listing::create(array_merge([
            'user_id' => $vendeur->id,
            'title' => 'Article',
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'femme',
            'category_level2' => 'robes',
        ], $champs));

        $annonce->images()->create(['url' => 'https://example.com/' . $annonce->id . '.jpg', 'order' => 0]);

        return $annonce;
    }

    private function vue(User $membre, Listing $annonce, int $fois = 1): void
    {
        for ($i = 0; $i < $fois; $i++) {
            Recommandations::noterConsultation($membre, $annonce);
        }
    }

    /** Un membre qui aime les robes Zara en M (2 favoris + 1 visite). */
    private function amatriceDeRobes(): array
    {
        $acheteuse = $this->membre('Acheteuse');
        $autre = $this->membre('Ancienne');

        foreach (['Robe Zara fleurie', 'Robe Zara longue'] as $titre) {
            $acheteuse->favorites()->attach($this->annonce($autre, ['title' => $titre, 'marque' => 'Zara', 'taille' => 'M'])->id);
        }
        $this->vue($acheteuse, $this->annonce($autre, ['title' => 'Robe H&M', 'marque' => 'H&M', 'taille' => 'M']));

        return [$acheteuse, $this->membre('Vendeuse')];
    }

    // --- Ce que le membre regarde --------------------------------------------

    public function test_une_vraie_visite_est_notee_mais_pas_un_prechargement(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = $this->annonce($vendeur);
        $visiteur = $this->membre('Visiteur');

        $this->actingAs($visiteur)->get(route('listings.show', $annonce), ['X-Sec-Purpose' => 'prefetch'])->assertOk();
        $this->assertDatabaseCount('listing_consultations', 0);

        $this->actingAs($visiteur)->get(route('listings.show', $annonce))->assertOk();
        $this->actingAs($visiteur)->get(route('listings.show', $annonce))->assertOk();

        $this->assertDatabaseHas('listing_consultations', ['user_id' => $visiteur->id, 'listing_id' => $annonce->id, 'vues' => 2]);
    }

    public function test_la_vue_signalee_par_une_page_prechargee_est_notee(): void
    {
        $annonce = $this->annonce($this->membre('Vendeur'));
        $visiteur = $this->membre('Visiteur');

        $this->actingAs($visiteur)->post(route('listings.view', $annonce))->assertNoContent();

        $this->assertDatabaseHas('listing_consultations', ['user_id' => $visiteur->id, 'listing_id' => $annonce->id]);
    }

    public function test_le_vendeur_et_les_visiteurs_anonymes_ne_sont_pas_notes(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = $this->annonce($vendeur);

        $this->get(route('listings.show', $annonce))->assertOk();
        $this->actingAs($vendeur)->get(route('listings.show', $annonce))->assertOk();

        $this->assertDatabaseCount('listing_consultations', 0);
    }

    // --- Le choix des articles -----------------------------------------------

    public function test_sans_historique_suffisant_rien_n_est_recommande(): void
    {
        $membre = $this->membre('Curieux');
        $this->vue($membre, $this->annonce($this->membre('Vendeur')));
        $this->annonce($this->membre('Autre'));

        $this->assertTrue(Recommandations::pour($membre)->isEmpty());
    }

    public function test_recommande_des_articles_qui_ressemblent_a_ses_gouts(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();

        $parfaite = $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $proche = $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango', 'marque' => 'Mango', 'taille' => 'S']);
        $horsSujet = $this->annonce($vendeuse, ['title' => 'Perceuse', 'category_level1' => 'maison', 'category_level2' => 'bricolage']);

        $ids = Recommandations::pour($acheteuse)->pluck('id');

        $this->assertSame($parfaite->id, $ids->first(), 'La robe Zara en M passe en tête.');
        $this->assertContains($proche->id, $ids);
        $this->assertNotContains($horsSujet->id, $ids);
    }

    public function test_ne_recommande_jamais_ce_qui_n_a_pas_de_sens(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();

        $exclues = [
            'autre île' => $this->annonce($vendeuse, ['territoire' => 'Martinique']),
            'sa propre annonce' => $this->annonce($acheteuse),
            'déjà en favori' => tap($this->annonce($vendeuse), fn ($a) => $acheteuse->favorites()->attach($a->id)),
            'déjà ouverte' => tap($this->annonce($vendeuse), fn ($a) => $this->vue($acheteuse, $a)),
            'vendue' => $this->annonce($vendeuse, ['status' => 'sold']),
            'trop ancienne' => tap($this->annonce($vendeuse), fn ($a) => DB::table('listings')->where('id', $a->id)->update(['created_at' => now()->subDays(60)])),
        ];

        $bloquee = $this->membre('Bloquee');
        $acheteuse->blockedUsers()->attach($bloquee->id);
        $exclues['vendeuse bloquée'] = $this->annonce($bloquee);

        $bannie = $this->membre('Bannie');
        $bannie->forceFill(['is_banned' => true])->save();
        $exclues['vendeuse bannie'] = $this->annonce($bannie);

        $sansPhoto = Listing::create($this->annonce($vendeuse)->only(['user_id', 'title', 'description', 'price', 'currency', 'listing_type', 'status', 'territoire', 'category_level1', 'category_level2']));
        $exclues['sans photo'] = $sansPhoto;

        $ids = Recommandations::pour($acheteuse, 50)->pluck('id');

        foreach ($exclues as $raison => $annonce) {
            $this->assertNotContains($annonce->id, $ids, $raison);
        }
    }

    public function test_pas_plus_de_deux_articles_du_meme_vendeur(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();

        for ($i = 0; $i < 5; $i++) {
            $this->annonce($vendeuse, ['title' => 'Robe ' . $i, 'marque' => 'Zara', 'taille' => 'M']);
        }

        $this->assertCount(2, Recommandations::pour($acheteuse));
    }

    // --- L'envoi : de temps en temps -----------------------------------------

    private function aLaReunion(int $heure): void
    {
        Carbon::setTestNow(Carbon::parse("2026-10-08 {$heure}:10:00", 'Indian/Reunion'));
    }

    public function test_le_soir_une_selection_est_envoyee(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $robe = $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M', 'price' => 15]);
        $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango']);
        $this->aLaReunion(19);

        $this->artisan('recommandations:envoyer')->assertSuccessful();

        $notif = Notification::where('user_id', $acheteuse->id)->where('type', 'recommandations')->sole();
        $this->assertSame('/pour-vous', $notif->url);
        $this->assertStringContainsString('« Robe Zara neuve » à 15 € et 1 autre article', $notif->message);
        $this->assertDatabaseHas('recommandations', ['user_id' => $acheteuse->id, 'listing_id' => $robe->id]);
        $this->assertNotNull($acheteuse->fresh()->recommandations_envoyees_at);
        Queue::assertPushed(SendPushBroadcast::class);
    }

    public function test_rien_ne_part_en_dehors_du_creneau_du_soir(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango']);
        $this->aLaReunion(11);

        $this->artisan('recommandations:envoyer')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['type' => 'recommandations']);
    }

    public function test_le_creneau_suit_l_heure_de_l_ile_du_membre(): void
    {
        // 19 h à La Réunion = 11 h en Martinique : trop tôt pour elle.
        $martiniquaise = $this->membre('Martiniquaise', 'Martinique');
        $this->aLaReunion(19);

        $this->assertSame('America/Martinique', PushPolicy::fuseau($martiniquaise));
        $this->assertSame(11, (int) now()->setTimezone(PushPolicy::fuseau($martiniquaise))->format('G'));
    }

    public function test_pas_plus_d_un_envoi_tous_les_quatre_jours(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango']);
        $this->aLaReunion(19);
        $this->artisan('recommandations:envoyer');

        // De nouveaux articles le lendemain : toujours rien avant 4 jours.
        $this->annonce($this->membre('Vendeuse3'), ['title' => 'Robe Zara bis', 'marque' => 'Zara', 'taille' => 'M']);
        $this->annonce($this->membre('Vendeuse4'), ['title' => 'Robe Zara ter', 'marque' => 'Zara', 'taille' => 'M']);
        Carbon::setTestNow(now()->addDay());
        $this->artisan('recommandations:envoyer');
        $this->assertSame(1, Notification::where('type', 'recommandations')->count());

        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('recommandations:envoyer');
        $this->assertSame(2, Notification::where('type', 'recommandations')->count());

        // Le second envoi ne repropose jamais les articles du premier.
        $this->assertSame(
            DB::table('recommandations')->where('user_id', $acheteuse->id)->count(),
            DB::table('recommandations')->where('user_id', $acheteuse->id)->distinct()->count('listing_id')
        );
    }

    public function test_une_selection_trop_maigre_n_est_pas_envoyee(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $this->aLaReunion(19);

        $this->artisan('recommandations:envoyer');

        $this->assertDatabaseMissing('notifications', ['type' => 'recommandations']);
        $this->assertNull($acheteuse->fresh()->recommandations_envoyees_at);
    }

    public function test_le_membre_peut_couper_les_suggestions(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $acheteuse->forceFill(['notification_prefs' => ['recommandations' => ['push' => false, 'email' => false]]])->save();
        $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango']);
        $this->aLaReunion(19);

        $this->artisan('recommandations:envoyer');

        $this->assertDatabaseMissing('notifications', ['type' => 'recommandations']);
    }

    public function test_activee_par_defaut_sur_mobile_sans_e_mail_et_plafonnee(): void
    {
        $membre = $this->membre();

        $this->assertTrue($membre->accepteNotification('recommandations', 'push'));
        $this->assertSame('recommandations', NotificationPreferences::categorieDuType('recommandations'));
        $this->assertFalse(NotificationPreferences::aLeCanal('recommandations', 'email'));
        $this->assertFalse(NotificationPreferences::defauts()['recommandations']['email']);
        // Animation : heures de silence et plafond quotidien s'appliquent.
        $this->assertSame('animation', PushPolicy::niveau('recommandations'));

        $html = $this->actingAs($membre)->get(route('account.notifications.preferences'))->assertOk()->getContent();
        $this->assertStringContainsString('Recommandé pour vous', $html);
        $this->assertMatchesRegularExpression('#name="notification_prefs\[recommandations\]\[push\]"[^>]*checked#', $html);
        $this->assertStringNotContainsString('name="notification_prefs[recommandations][email]"', $html);
    }

    public function test_la_commande_tourne_chaque_heure(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'recommandations:envoyer'));

        $this->assertCount(1, $events);
        $this->assertSame('5 * * * *', $events->first()->expression);
    }

    // --- La page « Pour vous » -----------------------------------------------

    public function test_la_page_pour_vous_montre_la_selection(): void
    {
        [$acheteuse, $vendeuse] = $this->amatriceDeRobes();
        $robe = $this->annonce($vendeuse, ['title' => 'Robe Zara neuve', 'marque' => 'Zara', 'taille' => 'M']);
        $this->annonce($this->membre('Vendeuse2'), ['title' => 'Robe Mango']);
        $this->aLaReunion(19);
        $this->artisan('recommandations:envoyer');

        // Elle a ouvert la robe depuis : elle reste dans sa sélection.
        $this->vue($acheteuse, $robe);

        $this->actingAs($acheteuse)
            ->get(route('account.recommendations'))
            ->assertOk()
            ->assertSee('data-recommandations', false)
            ->assertSee('Robe Zara neuve')
            ->assertSee('Robe Mango');
    }

    public function test_la_page_pour_vous_explique_quand_elle_est_vide(): void
    {
        $this->actingAs($this->membre())
            ->get(route('account.recommendations'))
            ->assertOk()
            ->assertSee('data-recommandations-vide', false)
            ->assertSee('Votre sélection arrive bientôt');
    }

    public function test_la_page_est_dans_le_menu(): void
    {
        $this->actingAs($this->membre())
            ->get('/')
            ->assertOk()
            ->assertSee('href="' . route('account.recommendations') . '"', false);
    }
}
