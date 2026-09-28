<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationPreferences;
use App\Support\PushPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 1. Couverture du push : certains e-mails partaient sans notification interne,
 *    donc sans notification sur le téléphone (annonce vue, offres).
 * 2. Fichiers permettant aux liens du site d'ouvrir directement l'application.
 */
class PushCoverageAndDeepLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['push.fcm.project_id' => 'swap-iles']);
    }

    private function membre(): User
    {
        return User::create([
            'name' => 'Membre',
            'email' => 'm' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function annonce(User $vendeur): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => 'Robe fleurie',
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
        ]);
    }

    public function test_une_annonce_consultee_declenche_aussi_une_notification(): void
    {
        Queue::fake();

        $vendeur = $this->membre();
        $annonce = $this->annonce($vendeur);

        $this->get(route('listings.show', $annonce))->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $vendeur->id,
            'type' => 'listing_viewed',
        ]);
        Queue::assertPushed(SendPushBroadcast::class);
    }

    public function test_l_annonce_consultee_reste_soumise_aux_garde_fous(): void
    {
        // Ce type ne doit PAS être traité comme transactionnel : sans ça, il
        // échapperait au plafond quotidien et aux heures de silence.
        $this->assertSame('animation', PushPolicy::niveau('listing_viewed'));
        $this->assertSame('favoris', NotificationPreferences::categorieDuType('listing_viewed'));
    }

    public function test_une_offre_recue_declenche_une_notification_au_vendeur(): void
    {
        Queue::fake();

        $vendeur = $this->membre();
        $acheteur = $this->membre();
        $annonce = $this->annonce($vendeur);

        $this->actingAs($acheteur)->post(route('offers.store', $annonce), [
            'amount' => 15,
            'message' => 'Je suis intéressé.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $vendeur->id,
            'type' => 'offer_received',
        ]);
    }

    public function test_une_offre_part_toujours_meme_la_nuit(): void
    {
        // Une offre engage de l'argent : jamais retenue ni plafonnée.
        $this->assertSame('transactionnel', PushPolicy::niveau('offer_received'));
        $this->assertNull(NotificationPreferences::categorieDuType('offer_received'));
    }

    public function test_le_fichier_android_est_servi_quand_l_empreinte_est_fournie(): void
    {
        config([
            'deeplinks.android.package' => 'com.swapiles.app',
            'deeplinks.android.sha256' => ['AA:BB:CC'],
        ]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'com.swapiles.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB:CC');
    }

    public function test_le_fichier_android_n_est_pas_servi_sans_empreinte(): void
    {
        // Un fichier incomplet ferait échouer la vérification au lieu de rester neutre.
        config(['deeplinks.android.sha256' => []]);

        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_le_fichier_apple_est_servi_quand_les_identifiants_sont_fournis(): void
    {
        config([
            'deeplinks.ios.team_id' => 'Z68H8V9222',
            'deeplinks.ios.bundle_id' => 'com.swapiles.app',
        ]);

        $reponse = $this->get('/.well-known/apple-app-site-association');

        $reponse->assertOk()
            ->assertJsonPath('applinks.details.0.appID', 'Z68H8V9222.com.swapiles.app');

        // L'administration ne doit jamais ouvrir l'application.
        $this->assertContains('NOT /admin/*', $reponse->json('applinks.details.0.paths'));
    }

    public function test_le_fichier_apple_n_est_pas_servi_sans_identifiants(): void
    {
        config(['deeplinks.ios.team_id' => null]);

        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
    }
}
