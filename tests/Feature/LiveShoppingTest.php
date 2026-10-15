<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\LiveMessage;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\LiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vente en direct, phase de test : seuls les admins (et testeurs listés)
 * voient le live ; pour tous les autres, rien n'existe.
 */
class LiveShoppingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $membre;

    protected function setUp(): void
    {
        parent::setUp();
        config(['live.ouvert_a_tous' => false, 'live.testeurs' => []]);
        $this->admin = $this->user('cabinet@taochyconsulting.fr', 'Emmanuel');
        $this->membre = $this->user('membre@ex.com', 'Paul');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $email, string $nom): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function article(User $vendeur, string $titre = 'Robe', float $prix = 15): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => $titre, 'description' => 'Description de test assez longue.',
            'price' => $prix, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published', 'territoire' => 'La Réunion',
        ]);
    }

    private function live(User $vendeur, array $articles, string $statut = 'en_direct', bool $visibles = true): LiveSession
    {
        $live = LiveSession::create(['user_id' => $vendeur->id, 'titre' => 'Vide-dressing', 'statut' => $statut]);
        foreach ($articles as $i => $a) {
            $live->articles()->attach($a->id, ['ordre' => $i, 'visible' => $visibles]);
        }

        return $live;
    }

    // --- Invisible pour les autres --------------------------------------------------

    public function test_introuvable_pour_les_membres_et_les_visiteurs(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->get('/live')->assertNotFound();
        $this->get('/live/' . $live->id)->assertNotFound();
        $this->actingAs($this->membre)->get('/live')->assertNotFound();
        $this->actingAs($this->membre)->get('/live/' . $live->id)->assertNotFound();
        $this->actingAs($this->membre)->postJson('/live/' . $live->id . '/messages', ['texte' => 'Coucou'])->assertNotFound();

        $this->actingAs($this->membre)->get('/')->assertDontSee('Ventes en direct');
    }

    public function test_visible_pour_l_admin_avec_le_lien_de_menu(): void
    {
        $this->actingAs($this->admin)->get('/')->assertSee('🔴 Ventes en direct (test)');
        $this->actingAs($this->admin)->get('/live')->assertOk()->assertSee('Mode test');
    }

    public function test_un_testeur_liste_y_a_acces_et_l_ouverture_rend_public(): void
    {
        config(['live.testeurs' => ['Membre@Ex.com']]);
        $this->actingAs($this->membre)->get('/live')->assertOk();

        config(['live.testeurs' => [], 'live.ouvert_a_tous' => true]);
        $this->get('/live')->assertOk();
    }

    // --- Animer un live ---------------------------------------------------------

    public function test_creer_demarrer_epingler_terminer(): void
    {
        $robe = $this->article($this->admin, 'Robe longue', 18);
        $autre = $this->article($this->membre, 'Pas à moi');

        $this->actingAs($this->admin)->post('/live', ['titre' => 'Mon premier live', 'articles' => [$autre->id]])
            ->assertSessionHasErrors('articles.0');

        $this->actingAs($this->admin)->post('/live', ['titre' => 'Mon premier live', 'articles' => [$robe->id]])->assertRedirect();
        $live = LiveSession::sole();
        $this->assertSame('programme', $live->statut);

        $this->actingAs($this->admin)->post("/live/{$live->id}/demarrer")->assertRedirect();
        $this->assertSame('en_direct', $live->fresh()->statut);

        $this->actingAs($this->admin)->postJson("/live/{$live->id}/epingler", ['listing_id' => $robe->id])->assertNoContent();
        $etat = $this->actingAs($this->admin)->getJson("/live/{$live->id}/etat")->json();
        $this->assertSame('Robe longue', $etat['epingle']['titre']);
        $this->assertSame('18,00 €', $etat['epingle']['prix']);
        $this->assertContains('📌 Robe longue — 18,00 €', array_column($etat['messages'], 'texte'));

        $this->actingAs($this->admin)->post("/live/{$live->id}/terminer")->assertRedirect();
        $this->assertSame('termine', $live->fresh()->statut);
    }

    public function test_seul_le_vendeur_anime_son_live(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $live = $this->live($this->admin, [$this->article($this->admin)], 'programme');

        $this->actingAs($this->membre)->post("/live/{$live->id}/demarrer")->assertForbidden();
        $this->actingAs($this->membre)->postJson("/live/{$live->id}/epingler", ['listing_id' => 1])->assertForbidden();
    }

    // --- Chat et modération -------------------------------------------------------

    public function test_chat_masquer_et_exclure(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->membre)->postJson("/live/{$live->id}/messages", ['texte' => 'Quelle taille ?'])->assertNoContent();
        $message = LiveMessage::where('type', 'message')->sole();
        $this->assertSame(['Quelle taille ?'], array_column($this->getJson("/live/{$live->id}/etat")->json('messages'), 'texte'));

        $this->actingAs($this->admin)->postJson("/live/{$live->id}/messages/{$message->id}/exclure")->assertNoContent();

        $this->assertTrue($message->fresh()->masque);
        $this->actingAs($this->membre)->getJson("/live/{$live->id}/etat")->assertJson(['exclu' => true]);
        $this->actingAs($this->membre)->postJson("/live/{$live->id}/messages", ['texte' => 'Re'])->assertForbidden();
    }

    public function test_compteur_de_spectateurs(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->membre)->getJson("/live/{$live->id}/etat");
        $this->actingAs($this->admin)->getJson("/live/{$live->id}/etat");

        $this->assertSame(1, LiveService::spectateurs($live), 'Le vendeur ne se compte pas.');
        $this->assertSame(1, $live->fresh()->spectateurs_max);

        Carbon::setTestNow(now()->addMinute());
        $this->assertSame(0, LiveService::spectateurs($live), 'Parti depuis plus de 30 s.');
    }

    // --- Achat pendant le live ----------------------------------------------------

    public function test_un_seul_acheteur_a_la_fois(): void
    {
        $robe = $this->article($this->admin);
        $live = $this->live($this->admin, [$robe]);
        $autre = $this->user('autre@ex.com', 'Léa');

        $this->assertTrue(LiveService::reserver($live, $robe, $this->membre));
        $this->assertFalse(LiveService::reserver($live, $robe, $autre), 'Déjà réservé par Paul.');
        $this->assertTrue(LiveService::reservePourUnAutre($robe, $autre));
        $this->assertFalse(LiveService::reservePourUnAutre($robe, $this->membre));
        $this->assertFalse(LiveService::reserver($live, $robe, $this->admin), 'Le vendeur ne s’achète pas.');

        // Paiement bloqué pour Léa pendant la réservation.
        $this->actingAs($autre)->get(route('checkout.show', $robe))
            ->assertRedirect(route('listings.show', $robe))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'réservé'));

        // Au bout de 5 minutes, l'article est libre.
        Carbon::setTestNow(now()->addMinutes(6));
        $this->assertTrue(LiveService::reserver($live, $robe, $autre));
    }

    public function test_acheter_depuis_le_live_mene_au_paiement(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $robe = $this->article($this->admin);
        $live = $this->live($this->admin, [$robe]);

        $this->actingAs($this->membre)->post("/live/{$live->id}/acheter/{$robe->id}")
            ->assertRedirect(route('checkout.show', $robe));

        $this->assertContains('🔥 Paul est en train d’acheter « Robe »', LiveMessage::pluck('body')->all());
    }

    // --- Vidéo (LiveKit) -----------------------------------------------------------

    private function decoder(string $jeton): array
    {
        [$entete, $corps, $signature] = explode('.', $jeton);
        $attendue = rtrim(strtr(base64_encode(hash_hmac('sha256', $entete . '.' . $corps, 'secret-de-test', true)), '+/', '-_'), '=');
        $this->assertSame($attendue, $signature, 'Signature HS256 valide.');

        return json_decode(base64_decode(strtr($corps, '-_', '+/')), true);
    }

    public function test_acces_video_vendeur_et_spectateur(): void
    {
        config(['live.livekit' => ['url' => 'wss://swapiles.livekit.cloud', 'key' => 'APIcle', 'secret' => 'secret-de-test'], 'live.testeurs' => ['membre@ex.com']]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $vendeur = $this->actingAs($this->admin)->getJson("/live/{$live->id}/jeton")->assertOk()->json();
        $this->assertTrue($vendeur['publier']);
        $this->assertSame('wss://swapiles.livekit.cloud', $vendeur['url']);
        $grants = $this->decoder($vendeur['jeton']);
        $this->assertSame('APIcle', $grants['iss']);
        $this->assertSame('membre-' . $this->admin->id, $grants['sub']);
        $this->assertSame('swapiles-live-' . $live->id, $grants['video']['room']);
        $this->assertTrue($grants['video']['canPublish']);

        $spectateur = $this->actingAs($this->membre)->getJson("/live/{$live->id}/jeton")->assertOk()->json();
        $this->assertFalse($spectateur['publier']);
        $grants = $this->decoder($spectateur['jeton']);
        $this->assertFalse($grants['video']['canPublish'], 'Un spectateur ne peut pas filmer.');
        $this->assertTrue($grants['video']['hidden']);
        $this->assertGreaterThan(time(), $grants['exp']);
    }

    public function test_pas_de_video_hors_direct_ni_sans_configuration(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)], 'programme');

        config(['live.livekit' => ['url' => null, 'key' => null, 'secret' => null]]);
        $this->actingAs($this->admin)->getJson("/live/{$live->id}/jeton")->assertStatus(503);

        config(['live.livekit' => ['url' => 'wss://x', 'key' => 'k', 'secret' => 's']]);
        $this->actingAs($this->admin)->getJson("/live/{$live->id}/jeton")->assertStatus(409);
        auth()->logout();
        $this->getJson("/live/{$live->id}/jeton")->assertNotFound();
    }

    public function test_camera_et_micro_autorises_seulement_sur_le_live(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $page = $this->actingAs($this->admin)->get("/live/{$live->id}")->assertOk();
        $this->assertStringContainsString('camera=(self)', $page->headers->get('Permissions-Policy'));
        $page->assertSee('livekit-client@2.5.7', false)->assertSee('Activer ma caméra');

        $this->assertStringContainsString('camera=()', $this->get('/')->headers->get('Permissions-Policy'));
    }

    public function test_page_du_live_chargee_en_entier_et_plein_ecran(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin, 'Sac en cuir')]);

        $this->actingAs($this->admin)->get("/live/{$live->id}")->assertOk()
            // Chargement complet : la vidéo démarre et la caméra est autorisée.
            ->assertSee('<meta name="turbo-visit-control" content="reload">', false)
            ->assertSee('fixed inset-0 z-[60]', false)
            ->assertSee('data-coeurs-zone', false)
            ->assertSee('Sac en cuir');
    }

    public function test_revenir_au_live_apres_l_avoir_quitte(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $robe = $this->article($this->admin);
        $live = $this->live($this->admin, [$robe]);

        $this->actingAs($this->membre)->get('/')->assertDontSee('Revenir au live');

        // Achat depuis le live : la page de paiement propose d'y revenir.
        $this->actingAs($this->membre)->post("/live/{$live->id}/acheter/{$robe->id}");
        $this->assertSame($live->id, session('live_retour'));
        $this->actingAs($this->membre)->get('/')->assertSee('Revenir au live')->assertSee('/live/' . $live->id, false);

        // Pas sur le live lui-même.
        $this->actingAs($this->membre)->get("/live/{$live->id}")->assertDontSee('Revenir au live');

        // Live terminé : le bouton disparaît.
        $live->update(['statut' => 'termine']);
        $this->actingAs($this->membre)->get('/')->assertDontSee('Revenir au live');
        $this->assertNull(session('live_retour'));
    }

    public function test_en_arrivant_on_voit_les_derniers_messages(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)]);
        for ($i = 1; $i <= 40; $i++) {
            LiveService::message($live, $this->admin, "Message {$i}");
        }

        $messages = $this->actingAs($this->admin)->getJson("/live/{$live->id}/etat")->json('messages');
        $this->assertCount(30, $messages);
        $this->assertSame('Message 11', $messages[0]['texte']);
        $this->assertSame('Message 40', $messages[29]['texte']);

        $suite = $this->actingAs($this->admin)->getJson("/live/{$live->id}/etat?apres=" . $messages[9]['id'])->json('messages');
        $this->assertCount(20, $suite);
        $this->assertSame('Message 21', $suite[0]['texte']);
    }

    public function test_liste_des_lives_en_vignettes(): void
    {
        $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->admin)->get('/live')->assertOk()
            ->assertSee('Vide-dressing')
            ->assertSee('aspect-[3/4]', false)
            ->assertSee('LIVE');
    }

    // --- Articles masqués puis dévoilés -------------------------------------------

    public function test_articles_masques_puis_devoiles_par_le_vendeur(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $robe = $this->article($this->admin, 'Robe rouge', 20);
        $sac = $this->article($this->admin, 'Sac', 30);

        // Un nouveau live : tout est masqué au départ.
        $this->actingAs($this->admin)->post('/live', ['titre' => 'Mon live', 'articles' => [$robe->id, $sac->id]]);
        $live = LiveSession::sole();
        $live->update(['statut' => 'en_direct']);

        $this->assertSame([], $this->actingAs($this->membre)->getJson("/live/{$live->id}/etat")->json('articles'));
        $this->actingAs($this->membre)->post("/live/{$live->id}/acheter/{$robe->id}")->assertNotFound();

        // Le vendeur dévoile la robe : elle apparaît, avec un message dans le chat.
        $this->actingAs($this->membre)->postJson("/live/{$live->id}/articles/{$robe->id}/visibilite", ['visible' => true])->assertForbidden();
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/articles/{$robe->id}/visibilite", ['visible' => true])->assertOk();

        $etat = $this->actingAs($this->membre)->getJson("/live/{$live->id}/etat")->json();
        $this->assertSame(['Robe rouge'], array_column($etat['articles'], 'titre'));
        $this->assertContains('🛍️ Nouvel article : Robe rouge — 20,00 €', array_column($etat['messages'], 'texte'));
        $this->actingAs($this->membre)->post("/live/{$live->id}/acheter/{$robe->id}")->assertRedirect(route('checkout.show', $robe));

        // Mettre le sac en avant le rend visible aussi.
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/epingler", ['listing_id' => $sac->id])->assertNoContent();
        $this->assertCount(2, $this->actingAs($this->membre)->getJson("/live/{$live->id}/etat")->json('articles'));

        // Le cacher à nouveau retire aussi la mise en avant.
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/articles/{$sac->id}/visibilite", ['visible' => false])->assertOk();
        $this->assertNull($live->fresh()->article_epingle_id);
    }

    // --- Partage ---------------------------------------------------------------------

    public function test_partage_avec_lien_d_invitation(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->membre)->get("/live/{$live->id}")->assertOk()
            ->assertSee('data-partager', false)
            ->assertSee('?de=' . $this->membre->id, false);

        // Un ami arrive par le lien du membre : on le salue une seule fois.
        config(['live.ouvert_a_tous' => true]);
        auth()->logout();
        $this->get("/live/{$live->id}?de={$this->membre->id}");
        $this->get("/live/{$live->id}?de={$this->membre->id}");
        $this->assertSame(1, LiveMessage::where('body', '👋 Un invité de Paul vient de rejoindre le live')->count());
    }

    // --- « Me prévenir » et notifications ---------------------------------------------

    public function test_me_prevenir_puis_rappel_et_demarrage(): void
    {
        config(['live.testeurs' => ['membre@ex.com']]);
        \Illuminate\Support\Facades\Notification::fake();
        $suiveur = $this->user('suiveur@ex.com', 'Lina');
        $curieux = $this->user('curieux@ex.com', 'Hugo');
        \Illuminate\Support\Facades\DB::table('seller_follows')->insert([
            ['follower_id' => $suiveur->id, 'seller_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()],
            ['follower_id' => $this->membre->id, 'seller_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $live = $this->live($this->admin, [$this->article($this->admin)], 'programme');
        $live->update(['debut_prevu_at' => now()->addHour()]);

        // Le membre demande à être prévenu.
        $this->actingAs($this->membre)->get('/live')->assertSee('Me prévenir du début du live');
        $this->actingAs($this->membre)->post("/live/{$live->id}/rappel")->assertRedirect();
        $this->assertTrue($live->abonnes()->whereKey($this->membre->id)->exists());
        $this->actingAs($this->admin)->post("/live/{$live->id}/rappel")->assertForbidden();

        // Trop tôt : pas de rappel.
        $this->assertSame(0, \App\Support\LiveNotifications::envoyerRappels());

        // 15 min avant : un rappel, une seule fois.
        Carbon::setTestNow(now()->addMinutes(46));
        $this->artisan('live:rappels')->assertSuccessful();
        $this->artisan('live:rappels')->assertSuccessful();
        $this->assertSame(1, \App\Models\Notification::where('type', 'live_soon')->count());
        $this->assertSame($this->membre->id, \App\Models\Notification::where('type', 'live_soon')->value('user_id'));

        // Démarrage : l'inscrit (notification + e-mail). Le suiveur, en phase de
        // test, n'a pas accès au live : rien. Le curieux non plus.
        $this->actingAs($this->admin)->post("/live/{$live->id}/demarrer");
        $this->assertSame(1, \App\Models\Notification::where('type', 'live_started')->where('user_id', $this->membre->id)->count());
        \Illuminate\Support\Facades\Notification::assertSentTo($this->membre, \App\Notifications\LiveCommenceNotification::class);
        $this->assertSame(0, \App\Models\Notification::whereIn('user_id', [$suiveur->id, $curieux->id])->count());
        // Pas de double notification pour l'inscrit qui suit aussi le dressing.
        $this->assertSame(0, \App\Models\Notification::where('type', 'live_started_suivi')->where('user_id', $this->membre->id)->count());

        // Live ouvert à tous : le suiveur est prévenu (sans e-mail).
        config(['live.ouvert_a_tous' => true]);
        $autre = $this->live($this->admin, [$this->article($this->admin, 'Jupe')], 'programme');
        $this->actingAs($this->admin)->post("/live/{$autre->id}/demarrer");
        $this->assertSame(1, \App\Models\Notification::where('type', 'live_started_suivi')->where('user_id', $suiveur->id)->count());
        \Illuminate\Support\Facades\Notification::assertNotSentTo($suiveur, \App\Notifications\LiveCommenceNotification::class);
    }

    public function test_notification_de_live_jamais_retardee_au_matin(): void
    {
        config(['push.silence.debut' => 22, 'push.silence.fin' => 8]);
        $nuit = Carbon::parse('2026-10-15 23:30', 'Indian/Reunion');

        $this->assertSame('ignorer', \App\Support\PushPolicy::decider($this->membre, 'live_started', $nuit)['action']);
        $this->assertSame('differer', \App\Support\PushPolicy::decider($this->membre, 'favorite_reminder', $nuit)['action']);
        $this->assertSame('envoyer', \App\Support\PushPolicy::decider($this->membre, 'live_started', Carbon::parse('2026-10-15 18:00', 'Indian/Reunion'))['action']);
    }

    public function test_reglage_live_cache_aux_membres_sans_acces_n_est_pas_coupe(): void
    {
        $propre = \App\Support\NotificationPreferences::nettoyer([
            'messages' => ['affiche' => '1', 'push' => '1'],
            'conseils' => ['affiche' => '1'],
        ]);

        $this->assertTrue($propre['lives']['push'], 'Catégorie non affichée : réglage par défaut conservé.');
        $this->assertFalse($propre['conseils']['push'], 'Catégorie affichée et décochée : coupée.');
    }

    // --- Enregistrement et replay -----------------------------------------------------

    private function configurerReplay(): void
    {
        config([
            'live.livekit' => ['url' => 'wss://swapiles.livekit.cloud', 'key' => 'APIcle', 'secret' => 'secret-de-test'],
            'live.replay' => ['cle' => 'k', 'secret' => 's', 'bucket' => 'lives', 'region' => 'auto', 'endpoint' => 'https://r2.exemple', 'url_publique' => 'https://replays.exemple'],
        ]);
    }

    public function test_enregistrement_puis_replay_publie_sur_le_profil(): void
    {
        $this->configurerReplay();
        config(['live.testeurs' => ['membre@ex.com']]);
        \Illuminate\Support\Facades\Http::fake([
            '*/twirp/livekit.Egress/StartRoomCompositeEgress' => \Illuminate\Support\Facades\Http::response(['egress_id' => 'EG_abc123']),
            '*/twirp/livekit.Egress/StopEgress' => \Illuminate\Support\Facades\Http::response(['egress_id' => 'EG_abc123']),
        ]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->membre)->postJson("/live/{$live->id}/enregistrer")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/enregistrer")->assertOk()->assertJson(['enregistre' => true]);
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/enregistrer")->assertOk();

        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertSent(function ($requete) use ($live) {
            return str_starts_with($requete->url(), 'https://swapiles.livekit.cloud/twirp/')
                && $requete['room_name'] === 'swapiles-live-' . $live->id
                && $requete['preset'] === 'PORTRAIT_H264_720P_30'
                && $requete['file_outputs'][0]['s3']['bucket'] === 'lives'
                && str_starts_with($requete->header('Authorization')[0], 'Bearer ');
        });

        $live->refresh();
        $this->assertSame('EG_abc123', $live->enregistrement_id);
        $this->assertStringStartsWith('https://replays.exemple/lives/live-' . $live->id . '-', $live->replayUrl());

        // Fin du live : l'enregistrement est arrêté.
        $this->actingAs($this->admin)->post("/live/{$live->id}/terminer");
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), 'StopEgress') && $r['egress_id'] === 'EG_abc123');

        // Pas encore publié : le vendeur voit le replay, les autres non.
        $this->actingAs($this->admin)->get("/live/{$live->id}")->assertSee('Garder le replay de votre live ?')->assertSee($live->replayUrl(), false);
        $this->actingAs($this->membre)->get("/live/{$live->id}")->assertDontSee($live->replayUrl(), false);
        $this->actingAs($this->membre)->get('/profil/' . $this->admin->id)->assertDontSee('🎬 Replays');

        // Publication.
        $this->actingAs($this->membre)->post("/live/{$live->id}/replay", ['publier' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post("/live/{$live->id}/replay", ['publier' => 1])->assertRedirect();
        $this->actingAs($this->membre)->get("/live/{$live->id}")->assertSee($live->replayUrl(), false);
        // Onglet dédié, après « Annonces ».
        $this->actingAs($this->membre)->get('/profil/' . $this->admin->id)->assertSee('🎬 Replays')->assertSee('tab=replays', false);
        $this->actingAs($this->membre)->get('/profil/' . $this->admin->id . '?tab=replays')->assertSee('Vide-dressing')->assertSee('1 replay de live');
        $this->actingAs($this->membre)->get('/live')->assertSee('🎬 Replays');

        // Membres sans accès au live : rien sur le profil.
        $autre = $this->user('autre@ex.com', 'Zoé');
        $this->actingAs($autre)->get('/profil/' . $this->admin->id)->assertDontSee('🎬 Replays');
    }

    public function test_replay_refuse_a_la_fin_est_supprime_et_le_fichier_efface(): void
    {
        $this->configurerReplay();
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 204)]);
        $live = $this->live($this->admin, [$this->article($this->admin)], 'termine');
        $live->update(['enregistrement_id' => 'EG_1', 'replay_chemin' => 'lives/live-1-abc.mp4']);

        // « Non, supprimer » : le replay disparaît tout de suite.
        $this->actingAs($this->admin)->post("/live/{$live->id}/replay", ['publier' => 0])->assertRedirect();
        $live->refresh();
        $this->assertNotNull($live->replay_supprime_at);
        $this->assertNull($live->replayUrl());
        $this->actingAs($this->admin)->get("/live/{$live->id}")->assertSee('supprimé définitivement')->assertDontSee('lives/live-1-abc.mp4', false);

        // Le fichier est effacé du stockage (requête DELETE signée).
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->method() === 'DELETE'
            && $r->url() === 'https://r2.exemple/lives/lives/live-1-abc.mp4'
            && str_starts_with($r->header('Authorization')[0], 'AWS4-HMAC-SHA256 Credential=k/'));

        // On repasse pendant 30 min (LiveKit peut finir l'envoi après), puis c'est terminé.
        $this->artisan('live:nettoyer-replays')->assertSuccessful();
        $this->assertNull($live->fresh()->replay_fichier_efface_at);
        Carbon::setTestNow(now()->addMinutes(31));
        $this->artisan('live:nettoyer-replays')->assertSuccessful();
        $this->assertNotNull($live->fresh()->replay_fichier_efface_at);
    }

    public function test_replay_garde_a_vie_sauf_suppression_par_le_vendeur(): void
    {
        $this->configurerReplay();
        config(['live.testeurs' => ['membre@ex.com']]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('', 204)]);
        $live = $this->live($this->admin, [$this->article($this->admin)], 'termine');
        $live->update(['replay_chemin' => 'lives/live-2.mp4']);

        $this->actingAs($this->admin)->post("/live/{$live->id}/replay", ['publier' => 1]);

        // Un an plus tard, toujours là.
        Carbon::setTestNow(now()->addYear());
        $this->actingAs($this->membre)->get('/profil/' . $this->admin->id)->assertSee('🎬 Replays');

        // Seul le vendeur peut le supprimer.
        $this->actingAs($this->membre)->post("/live/{$live->id}/replay/supprimer")->assertForbidden();
        $this->actingAs($this->admin)->post("/live/{$live->id}/replay/supprimer")->assertRedirect();
        $this->actingAs($this->membre)->get('/profil/' . $this->admin->id)->assertDontSee('🎬 Replays');
        $this->actingAs($this->membre)->get('/live')->assertDontSee('🎬 Replays');
    }

    public function test_signature_s3_conforme_a_l_exemple_officiel_aws(): void
    {
        // Exemple « GET Object » de la documentation AWS Signature v4 (S3).
        $autorisation = \App\Support\LiveEnregistrement::signatureS3('GET', '/test.txt', [
            'host' => 'examplebucket.s3.amazonaws.com',
            'range' => 'bytes=0-9',
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => '20130524T000000Z',
        ], 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1', '20130524T000000Z');

        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41',
            $autorisation
        );
    }

    public function test_replay_rejoue_chat_coeurs_et_article_au_bon_moment(): void
    {
        $this->configurerReplay();
        config(['live.testeurs' => ['membre@ex.com'], 'live.replay_decalage_secondes' => 3]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['egress_id' => 'EG_1'])]);
        $robe = $this->article($this->admin, 'Robe rouge', 20);
        $live = $this->live($this->admin, [$robe]);

        Carbon::setTestNow('2026-10-18 18:00:00');
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/enregistrer")->assertOk();

        Carbon::setTestNow('2026-10-18 18:00:13'); // 10 s de vidéo (13 − 3 de démarrage)
        $this->actingAs($this->membre)->postJson("/live/{$live->id}/messages", ['texte' => 'Elle taille comment ?'])->assertNoContent();
        $this->postJson("/live/{$live->id}/coeur");
        $this->postJson("/live/{$live->id}/coeur");

        Carbon::setTestNow('2026-10-18 18:01:03'); // 60 s
        $this->actingAs($this->admin)->postJson("/live/{$live->id}/epingler", ['listing_id' => $robe->id])->assertNoContent();
        $this->actingAs($this->admin)->post("/live/{$live->id}/terminer");
        $live->refresh()->update(['replay_publie_at' => now()]);

        $d = LiveService::donneesReplay($live->fresh());
        $question = collect($d['messages'])->firstWhere('texte', 'Elle taille comment ?');
        $this->assertSame(10, $question['t']);
        $epingle = collect($d['messages'])->firstWhere('type', 'epingle');
        $this->assertSame(60, $epingle['t']);
        $this->assertSame($robe->id, $epingle['article']);
        $this->assertSame([['t' => 10, 'n' => 2]], $d['coeurs']);
        $this->assertSame('Robe rouge', $d['articles'][$robe->id]['titre']);

        $this->actingAs($this->membre)->get("/live/{$live->id}")->assertOk()
            ->assertSee('▶ REPLAY', false)->assertSee('data-replay-video', false)
            ->assertSee('data-replay-donnees', false)->assertSee('Elle taille comment', false);
    }

    public function test_plus_de_confirmation_native_sur_le_live(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $page = $this->actingAs($this->admin)->get("/live/{$live->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('confirm(', $page, 'window.confirm fige la caméra sur iPhone.');
        $this->assertStringContainsString('data-confirmer="Terminer le live pour tout le monde ?"', $page);
    }

    public function test_sans_stockage_rien_n_est_enregistre(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        config(['live.livekit' => ['url' => 'wss://x', 'key' => 'k', 'secret' => 's'], 'live.replay.cle' => null]);
        $live = $this->live($this->admin, [$this->article($this->admin)]);

        $this->actingAs($this->admin)->postJson("/live/{$live->id}/enregistrer")->assertOk()->assertJson(['enregistre' => false]);
        \Illuminate\Support\Facades\Http::assertNothingSent();

        $this->actingAs($this->admin)->post("/live/{$live->id}/terminer");
        $this->actingAs($this->admin)->get("/live/{$live->id}")->assertSee('pas été enregistré');
    }

    // --- Admin --------------------------------------------------------------------

    public function test_l_admin_peut_couper_un_live(): void
    {
        $live = $this->live($this->admin, [$this->article($this->admin)]);
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Filament\Resources\LiveSessions\Pages\ListLiveSessions::class)
            ->assertSee('Vide-dressing')
            ->callTableAction('couper', $live, ['raison' => 'Test'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('termine', $live->fresh()->statut);
        $this->assertSame('Test', $live->fresh()->coupe_raison);
    }
}
