<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeau « Télécharger l'appli » sur le site.
 *
 * Sur téléphone seulement, vers le bon store (App Store sur iPhone, Google
 * Play sur Android), jamais dans l'application elle-même, et jamais pour un
 * membre qui utilise déjà l'appli.
 */
class AppDownloadBannerTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        $membre = User::create([
            'name' => 'Marie', 'email' => 'm' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        return $membre;
    }

    public function test_un_visiteur_recoit_le_bandeau_cache_avec_les_deux_stores(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Caché au départ : le script décide (téléphone, pas dans l'appli…).
        $this->assertMatchesRegularExpression('#<div data-bandeau-appli hidden\s#', $html);
        $this->assertStringContainsString('data-lien-ios="https://apps.apple.com/app/swap%C3%AEles/id6809002783"', $html);
        $this->assertStringContainsString('data-lien-android="https://play.google.com/store/apps/details?id=com.swapiles.app&amp;referrer=', $html);
        $this->assertMatchesRegularExpression('#<script defer data-turbo-track="reload"\s+src="[^"]*/js/app-banner\.js#', $html);
    }

    public function test_un_membre_qui_utilise_l_appli_ne_le_recoit_pas(): void
    {
        $membre = $this->membre();
        $membre->forceFill(['appli_vue_at' => now()->subDays(3)])->save();

        $this->actingAs($membre)->get('/')->assertOk()->assertDontSee('data-bandeau-appli', false);
    }

    public function test_un_telephone_enregistre_pour_les_notifications_compte_aussi(): void
    {
        $membre = $this->membre();
        DeviceToken::create(['user_id' => $membre->id, 'token' => 'jeton-' . uniqid(), 'platform' => 'android', 'last_seen_at' => now()->subDays(10)]);

        $this->actingAs($membre)->get('/')->assertOk()->assertDontSee('data-bandeau-appli', false);
    }

    public function test_un_membre_qui_n_a_plus_ouvert_l_appli_depuis_longtemps_le_revoit(): void
    {
        $membre = $this->membre();
        $membre->forceFill(['appli_vue_at' => now()->subDays(120)])->save();

        $this->actingAs($membre)->get('/')->assertOk()->assertSee('data-bandeau-appli', false);
    }

    public function test_l_appli_signale_qu_elle_est_utilisee(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)->post(route('app.seen'))->assertNoContent();
        $this->assertNotNull($membre->fresh()->appli_vue_at);
        $this->assertTrue($membre->fresh()->utiliseLAppli());

        // Visiteur non connecté : rien à enregistrer, pas d'erreur.
        auth()->logout();
        $this->post(route('app.seen'))->assertNoContent();

        $push = file_get_contents(public_path('js/push.js'));
        $this->assertStringContainsString("fetch('/appli/vue'", $push);
        $this->assertStringContainsString("document.querySelector('meta[name=\"swp-connecte\"]')", $push);
    }

    public function test_le_script_choisit_le_bon_store_et_reste_discret(): void
    {
        $js = file_get_contents(public_path('js/app-banner.js'));

        $this->assertStringContainsString("if (/android/i.test(ua)) return 'android';", $js);
        $this->assertStringContainsString("if (/iPhone|iPad|iPod/i.test(ua)) return 'ios';", $js);
        // iPad récent, qui se présente comme un Mac.
        $this->assertStringContainsString('navigator.maxTouchPoints > 1', $js);
        // Jamais dans l'application, ni sur ordinateur, ni 30 jours après fermeture.
        $this->assertStringContainsString('if (dansLAppli() || !os || fermeRecemment())', $js);
        $this->assertStringContainsString('var DUREE = 30 * 24 * 3600 * 1000;', $js);
    }
}
