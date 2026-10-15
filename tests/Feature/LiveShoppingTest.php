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

    private function live(User $vendeur, array $articles, string $statut = 'en_direct'): LiveSession
    {
        $live = LiveSession::create(['user_id' => $vendeur->id, 'titre' => 'Vide-dressing', 'statut' => $statut]);
        foreach ($articles as $i => $a) {
            $live->articles()->attach($a->id, ['ordre' => $i]);
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
