<?php

namespace Tests\Feature;

use App\Jobs\SendListingViewedEmail;
use App\Jobs\SendPushBroadcast;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notifications utiles par défaut, et « Suivre un dressing » comme sur un
 * réseau social.
 *
 *  - Vues et favoris : coupés pour tous (trop de sollicitations), chacun les
 *    active s'il veut. Messages, propositions, échanges : activés d'office.
 *  - Suivre un membre = être prévenu de chacun de ses nouveaux articles
 *    (activé d'office). Le bouton est visible partout où l'on voit le
 *    vendeur, avec son nombre d'abonnés, et chacun peut partager son dressing.
 */
class FollowDressingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['push.fcm.project_id' => 'swap-iles']);
    }

    private function membre(string $nom = 'Membre', ?array $prefs = null): User
    {
        $membre = User::create([
            'name' => $nom,
            'email' => strtolower($nom) . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
            'notification_prefs' => $prefs,
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        return $membre;
    }

    private function annonce(User $vendeur, string $titre = 'Robe fleurie'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => $titre,
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'femme',
        ]);
    }

    // --- Vues et favoris coupés par défaut ----------------------------------

    public function test_une_vue_ne_previent_pas_le_vendeur_par_defaut(): void
    {
        Queue::fake();
        $annonce = $this->annonce($vendeur = $this->membre('Vendeur'));

        $this->get(route('listings.show', $annonce))->assertOk();

        $this->assertDatabaseMissing('notifications', ['user_id' => $vendeur->id, 'type' => 'listing_viewed']);
        Queue::assertNotPushed(SendListingViewedEmail::class);
        Queue::assertNotPushed(SendPushBroadcast::class);
    }

    public function test_un_favori_ne_previent_pas_le_vendeur_par_defaut(): void
    {
        Queue::fake();
        $annonce = $this->annonce($vendeur = $this->membre('Vendeur'));

        $this->actingAs($this->membre('Visiteur'))
            ->postJson(route('account.favorites.toggle', $annonce))
            ->assertOk()
            ->assertJson(['favorited' => true]);

        $this->assertDatabaseMissing('notifications', ['user_id' => $vendeur->id, 'type' => 'favorite_added']);
        Queue::assertNotPushed(SendPushBroadcast::class);
    }

    public function test_le_vendeur_qui_active_les_vues_est_prevenu(): void
    {
        Queue::fake();
        $vendeur = $this->membre('Vendeur', ['vues' => ['push' => true, 'email' => false]]);

        $this->get(route('listings.show', $this->annonce($vendeur)))->assertOk();

        $this->assertDatabaseHas('notifications', ['user_id' => $vendeur->id, 'type' => 'listing_viewed']);
    }

    public function test_l_email_de_vue_verifie_le_bon_reglage(): void
    {
        // Il vérifiait le réglage « photo manquante » : couper les vues
        // n'arrêtait pas l'e-mail.
        $source = file_get_contents(app_path('Jobs/SendListingViewedEmail.php'));

        $this->assertStringContainsString("accepteNotification('listing_viewed', 'email')", $source);
        $this->assertStringNotContainsString("'listing_needs_photo'", $source);
    }

    public function test_la_page_de_reglages_affiche_les_nouveaux_defauts(): void
    {
        $html = $this->actingAs($this->membre())
            ->get(route('account.notifications.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Désactivé par défaut', $html);
        $this->assertMatchesRegularExpression('#name="notification_prefs\[messages\]\[push\]"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#name="notification_prefs\[vendeurs_suivis\]\[push\]"[^>]*checked#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="notification_prefs\[vues\]\[push\]"[^>]*checked#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="notification_prefs\[favoris\]\[push\]"[^>]*checked#', $html);
    }

    public function test_la_migration_remet_vues_et_favoris_a_zero_pour_tous(): void
    {
        $ancien = $this->membre('Ancien');
        DB::table('users')->where('id', $ancien->id)->update(['notification_prefs' => json_encode([
            'favoris' => ['push' => true, 'email' => true],
            'messages' => ['push' => true, 'email' => false],
        ])]);

        $seulementFavoris = $this->membre('Favoris');
        DB::table('users')->where('id', $seulementFavoris->id)->update(['notification_prefs' => json_encode([
            'favoris' => ['push' => true, 'email' => true],
        ])]);

        $migration = require database_path('migrations/2026_10_07_120000_reset_views_and_favorites_notification_prefs.php');
        $migration->up();

        $ancien->refresh();
        $this->assertSame(['messages' => ['push' => true, 'email' => false]], $ancien->notification_prefs);
        $this->assertFalse($ancien->accepteNotification('favorite_added', 'push'));
        // Son choix sur les messages est conservé.
        $this->assertFalse($ancien->accepteNotification('message_received', 'email'));

        $seulementFavoris->refresh();
        $this->assertNull($seulementFavoris->notification_prefs);
        $this->assertFalse($seulementFavoris->accepteNotification('listing_viewed', 'push'));
        $this->assertTrue($seulementFavoris->accepteNotification('message_received', 'push'));
    }

    // --- Suivre un dressing -------------------------------------------------

    public function test_suivre_puis_ne_plus_suivre(): void
    {
        $vendeur = $this->membre('Vendeur');
        $fan = $this->membre('Fan');

        $this->actingAs($fan)
            ->postJson(route('account.seller-follow.toggle', $vendeur))
            ->assertOk()
            ->assertJson(['following' => true, 'count' => 1]);

        $this->assertTrue($fan->suit($vendeur));

        $this->actingAs($fan)
            ->postJson(route('account.seller-follow.toggle', $vendeur))
            ->assertOk()
            ->assertJson(['following' => false, 'count' => 0]);

        $this->assertFalse($fan->suit($vendeur));
    }

    public function test_un_abonne_est_prevenu_d_office_quand_le_dressing_publie(): void
    {
        Queue::fake();
        $vendeur = $this->membre('Vendeur');
        $fan = $this->membre('Fan');
        $fan->followedSellers()->attach($vendeur->id);

        $annonce = $this->annonce($vendeur);
        $annonce->update(['status' => 'draft']);

        $this->actingAs($vendeur)->patch(route('account.listings.publish', $annonce))->assertRedirect();

        $this->assertDatabaseHas('notifications', ['user_id' => $fan->id, 'type' => 'seller_published_listing']);
        // Aucun réglage enregistré : la notification sonne quand même.
        Queue::assertPushed(SendPushBroadcast::class);
    }

    public function test_la_page_annonce_propose_de_suivre_le_vendeur(): void
    {
        $vendeur = $this->membre('Vendeur');
        $vendeur->followers()->attach($this->membre('Fan1')->id);
        $vendeur->followers()->attach($this->membre('Fan2')->id);
        $annonce = $this->annonce($vendeur);

        $html = $this->actingAs($this->membre('Visiteur'))
            ->get(route('listings.show', $annonce))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-suivre-url="' . route('account.seller-follow.toggle', $vendeur) . '"', $html);
        $this->assertStringContainsString('data-abonnes-de="' . $vendeur->id . '">2 abonnés', $html);
        $this->assertStringContainsString('Voir tout son dressing', $html);
        $this->assertSame([], $this->boutonsDansUnLien($html));
    }

    public function test_un_visiteur_voit_le_bouton_suivre_qui_mene_a_la_connexion(): void
    {
        $vendeur = $this->membre('Vendeur');

        foreach ([route('listings.show', $this->annonce($vendeur)), route('profiles.show', $vendeur)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-suivre-connexion="' . route('login') . '"', $html, $url);
            $this->assertSame([], $this->boutonsDansUnLien($html), $url);
        }
    }

    public function test_le_vendeur_ne_peut_pas_se_suivre_lui_meme(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = $this->annonce($vendeur);

        $html = $this->actingAs($vendeur)->get(route('listings.show', $annonce))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-suivre-url=', $html);

        $this->actingAs($vendeur)
            ->postJson(route('account.seller-follow.toggle', $vendeur))
            ->assertForbidden();
    }

    public function test_le_profil_affiche_les_abonnes_et_invite_a_suivre(): void
    {
        $vendeur = $this->membre('Vendeur');
        $vendeur->followers()->attach($this->membre('Fan')->id);

        $html = $this->actingAs($this->membre('Visiteur'))
            ->get(route('profiles.show', $vendeur))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-abonnes-de="' . $vendeur->id . '">1 abonné<', $html);
        $this->assertStringContainsString('data-suivre-url=', $html);
        $this->assertStringContainsString('data-invitation-suivre', $html);
        // L'ancien bouton et son script sont remplacés par le bouton commun.
        $this->assertStringNotContainsString('follow-seller-btn', $html);
    }

    public function test_mon_profil_propose_de_partager_mon_dressing(): void
    {
        $vendeur = $this->membre('Vendeur');

        $html = $this->actingAs($vendeur)->get(route('profiles.show', $vendeur))->assertOk()->getContent();

        $this->assertStringContainsString('data-partager-dressing', $html);
        $this->assertStringContainsString('utm_campaign=dressing_' . $vendeur->id, $html);
        $this->assertStringNotContainsString('data-suivre-url=', $html);
        $this->assertStringNotContainsString('data-invitation-suivre', $html);
    }

    public function test_la_fenetre_annonce_publiee_annonce_les_abonnes_prevenus(): void
    {
        $vendeur = $this->membre('Vendeur');
        $vendeur->followers()->attach($this->membre('Fan1')->id);
        $vendeur->followers()->attach($this->membre('Fan2')->id);
        $annonce = $this->annonce($vendeur);

        $html = $this->actingAs($vendeur)
            ->withSession(['just_published' => true])
            ->get(route('listings.show', $annonce))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2 abonnés</strong>', $html);
        $this->assertStringContainsString('viennent d\'être prévenus', $html);
    }

    public function test_le_tableau_de_bord_montre_mon_dressing(): void
    {
        $vendeur = $this->membre('Vendeur');
        $vendeur->followers()->attach($this->membre('Fan')->id);

        $this->actingAs($vendeur)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('Mon dressing · 1 abonné', false);
    }

    public function test_le_script_de_suivi_est_charge_sur_toutes_les_pages(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script defer data-turbo-track="reload"\s+src="[^"]*/js/follow\.js#', $html);

        $js = file_get_contents(public_path('js/follow.js'));
        $this->assertMatchesRegularExpression('/catch\(function \(\) \{\s*peindre\(bouton, avant\)/', $js, 'Un échec remet l’état précédent.');
        $this->assertStringContainsString('data.jeton', $js, 'Jeton périmé : un nouvel essai.');
    }

    /** Un bouton dans un lien est du HTML invalide : le clic ouvrirait le lien. */
    private function boutonsDansUnLien(string $html): array
    {
        $html = preg_replace('#<script\b.*?</script>#is', '', $html);
        preg_match_all('#<a\b[^>]*>.*?</a\s*>#is', $html, $liens);

        return array_values(array_filter($liens[0], fn ($lien) => preg_match('#<button\b[^>]*data-suivre#i', $lien)));
    }
}
